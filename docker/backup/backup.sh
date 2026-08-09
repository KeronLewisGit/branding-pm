#!/usr/bin/env bash
#
# Nightly database backup.
#
# Runs in a container built from the SAME mysql image as the server, so
# mysqldump is always version-matched. Dumping from the application container
# would mean installing a client there and keeping its version in step, and it
# would tie backups to PHP being healthy — a backup that only runs when the
# app is well is the one you find missing when the app is not.
#
# Writes to /backups, which is bind-mounted to ./storage/backups on the host.
# That is deliberate: the database lives in a named Docker volume, and
# `docker compose down -v` destroys named volumes. A backup inside one would
# be destroyed by the same command that destroys the thing it protects.
#
# Usage:
#   backup.sh once     take one backup and exit (also what you run by hand)
#   backup.sh loop     sleep until BACKUP_TIME, back up, repeat (the service)

set -euo pipefail

BACKUP_DIR=${BACKUP_DIR:-/backups}
BACKUP_TIME=${BACKUP_TIME:-02:30}
RETENTION_DAYS=${BACKUP_RETENTION_DAYS:-14}
DB_NAME=${DB_DATABASE:-branding_pm}
DB_HOST=${DB_HOST:-mysql}

# storage/app — signatures and fault photos, mounted read-only. The database
# references these by path: restore it without them and every approved run
# loses the signature that made it approvable. A database backup alone is not
# the audit record.
APP_DATA=${APP_DATA:-/appdata}

log() {
    printf '%s  %s\n' "$(date '+%Y-%m-%d %H:%M:%S')" "$*"
}

fail() {
    log "FAILED: $*"
    exit 1
}

take_backup() {
    mkdir -p "$BACKUP_DIR"

    local stamp file tmp bytes
    stamp=$(date '+%Y-%m-%d_%H%M%S')
    file="${BACKUP_DIR}/${DB_NAME}-${stamp}.sql.gz"
    tmp="${file}.partial"

    log "dumping ${DB_NAME} -> $(basename "$file")"

    # --single-transaction: a consistent snapshot without locking the tables,
    #   so the shop floor can keep working through the backup (InnoDB only,
    #   which is every table here).
    # --routines/--triggers/--events: schema objects a data-only dump loses.
    # --no-tablespaces: avoids needing the PROCESS privilege.
    # Written to .partial first, then renamed — an interrupted dump must never
    # be mistaken for a good one by the pruning below or by a tired human at
    # two in the morning.
    if ! mysqldump \
        --host="$DB_HOST" \
        --user=root \
        --password="$MYSQL_ROOT_PASSWORD" \
        --single-transaction \
        --quick \
        --routines \
        --triggers \
        --events \
        --no-tablespaces \
        --default-character-set=utf8mb4 \
        "$DB_NAME" 2>/tmp/dump.err | gzip -9 > "$tmp"
    then
        rm -f "$tmp"
        fail "mysqldump: $(tr '\n' ' ' < /tmp/dump.err)"
    fi

    # gzip -t reads the whole stream: catches a dump truncated by a full disk,
    # which is the way these usually die.
    gzip -t "$tmp" 2>/dev/null || { rm -f "$tmp"; fail "the dump did not survive its own integrity check"; }

    bytes=$(stat -c %s "$tmp")

    # A dump of a live database is hundreds of KB. Anything tiny means
    # mysqldump wrote an error page and exited 0, or the database is empty —
    # either way it is not a backup and must not silently replace yesterday's.
    if [ "$bytes" -lt 20000 ]; then
        rm -f "$tmp"
        fail "dump is only ${bytes} bytes — refusing to keep it"
    fi

    mv "$tmp" "$file"
    sha256sum "$file" | awk '{print $1}' > "${file}.sha256"

    log "wrote $(basename "$file") (${bytes} bytes)"

    # Files AFTER the database, and the order matters.
    #
    # The dump is a snapshot of the moment it started. Every signature path in
    # it already existed then, and nothing in this system deletes a signature,
    # so archiving the files afterwards guarantees each of those paths is in
    # the archive. A file created in between is an extra the database does not
    # know about — harmless.
    #
    # The other order breaks it: files first, then the database, and a run
    # signed in between leaves the dump referencing a signature the archive
    # does not have. That is a broken audit record, and it would only be
    # discovered on the day somebody tried to restore.
    take_files || log "the database dump is safe; the file archive above is not"

    prune
}

take_files() {
    if [ ! -d "$APP_DATA" ]; then
        log "no ${APP_DATA} mounted — skipping the file archive"
        return 0
    fi

    local stamp file tmp bytes count
    stamp=$(date '+%Y-%m-%d_%H%M%S')
    file="${BACKUP_DIR}/storage-${stamp}.tar.gz"
    tmp="${file}.partial"

    count=$(find "$APP_DATA" -type f ! -name '.gitignore' | wc -l)

    log "archiving ${count} file(s) from ${APP_DATA} -> $(basename "$file")"

    if ! tar -czf "$tmp" -C "$APP_DATA" . 2>/tmp/tar.err; then
        rm -f "$tmp"
        log "FAILED: tar: $(tr '\n' ' ' < /tmp/tar.err)"
        return 1
    fi

    # Read the archive back: catches truncation by a full disk, which is how
    # these die. `tar -t` walks every header, not just the gzip envelope.
    if ! tar -tzf "$tmp" > /dev/null 2>&1; then
        rm -f "$tmp"
        log "FAILED: the file archive did not survive its own integrity check"
        return 1
    fi

    # No size floor here, unlike the database dump: early in a pilot there
    # genuinely may be almost no signatures yet, and refusing an honest small
    # archive would mean no file backup at all for the first few weeks. The
    # count is logged instead, so a drop to zero is visible.
    mv "$tmp" "$file"
    sha256sum "$file" | awk '{print $1}' > "${file}.sha256"

    bytes=$(stat -c %s "$file")
    log "wrote $(basename "$file") (${bytes} bytes, ${count} file(s))"
}

prune() {
    local removed archives

    removed=$(find "$BACKUP_DIR" -maxdepth 1 -name "${DB_NAME}-*.sql.gz" -mtime "+${RETENTION_DAYS}" -print -delete | wc -l)

    # The file archives age out on the same schedule: a dump without the
    # signatures of its own night is not a complete record, so keeping one
    # without the other buys nothing.
    archives=$(find "$BACKUP_DIR" -maxdepth 1 -name 'storage-*.tar.gz' -mtime "+${RETENTION_DAYS}" -print -delete | wc -l)

    # Orphaned checksums and any interrupted dumps from a killed container.
    find "$BACKUP_DIR" -maxdepth 1 -name '*.sha256' -mtime "+${RETENTION_DAYS}" -delete
    find "$BACKUP_DIR" -maxdepth 1 -name '*.partial' -mtime +1 -delete

    if [ "$((removed + archives))" -gt 0 ]; then
        log "pruned ${removed} dump(s) and ${archives} file archive(s) older than ${RETENTION_DAYS} days"
    fi
}

seconds_until() {
    local target now next
    now=$(date +%s)
    target=$(date -d "today ${BACKUP_TIME}" +%s 2>/dev/null) || fail "BACKUP_TIME='${BACKUP_TIME}' is not a time (want HH:MM)"

    if [ "$target" -le "$now" ]; then
        next=$(date -d "tomorrow ${BACKUP_TIME}" +%s)
    else
        next=$target
    fi

    echo $((next - now))
}

case "${1:-loop}" in
    once)
        take_backup
        ;;
    loop)
        log "backup service started — nightly at ${BACKUP_TIME}, keeping ${RETENTION_DAYS} days"
        while true; do
            wait_for=$(seconds_until)
            log "next backup in $((wait_for / 3600))h $(((wait_for % 3600) / 60))m"
            sleep "$wait_for"

            # A failed backup must not kill the service — tomorrow's attempt is
            # still worth making, and the staleness check reports the gap.
            take_backup || log "continuing despite the failure above"
        done
        ;;
    *)
        echo "usage: $0 [once|loop]" >&2
        exit 64
        ;;
esac
