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

    prune
}

prune() {
    local removed
    removed=$(find "$BACKUP_DIR" -maxdepth 1 -name "${DB_NAME}-*.sql.gz" -mtime "+${RETENTION_DAYS}" -print -delete | wc -l)

    # Orphaned checksums and any interrupted dumps from a killed container.
    find "$BACKUP_DIR" -maxdepth 1 -name '*.sha256' -mtime "+${RETENTION_DAYS}" -delete
    find "$BACKUP_DIR" -maxdepth 1 -name '*.partial' -mtime +1 -delete

    if [ "$removed" -gt 0 ]; then
        log "pruned ${removed} backup(s) older than ${RETENTION_DAYS} days"
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
