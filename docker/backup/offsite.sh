#!/usr/bin/env bash
#
# Copy nightly database dumps to the network share.
#
# Runs as its OWN service, deliberately separate from `backup`. If the file
# server is down, or somebody changes the service account's password, the
# nightly dump must still be taken — a broken share is a reason to have no
# off-site copy, never a reason to have no backup at all.
#
# Every copy is verified by reading the file back FROM THE SHARE and checking
# its SHA-256 against the local one. A network copy that half-succeeds is the
# normal way this goes wrong: the file is there, it is the right name, and it
# is truncated. Comparing sizes would not catch it; comparing the source
# checksum to itself would not either.
#
# Writes /backups/.offsite-status.json after every run so `backup:status` and
# `security:check` can report on it. That file is how the application knows
# anything about the share — the app container deliberately does not mount it,
# because a mount that can fail must not be able to stop the app from booting.

set -uo pipefail

LOCAL_DIR=${LOCAL_DIR:-/backups}
OFFSITE_DIR=${OFFSITE_DIR:-/offsite}
OFFSITE_TIME=${BACKUP_OFFSITE_TIME:-03:30}
RETENTION_DAYS=${BACKUP_OFFSITE_RETENTION_DAYS:-30}
STATUS_FILE="${LOCAL_DIR}/.offsite-status.json"

log() {
    printf '%s  %s\n' "$(date '+%Y-%m-%d %H:%M:%S')" "$*"
}

# $1 copied  $2 verified  $3 failed  $4 message
write_status() {
    cat > "${STATUS_FILE}.partial" <<JSON
{
    "ran_at": "$(date -u '+%Y-%m-%dT%H:%M:%SZ')",
    "destination": "${BACKUP_OFFSITE_LABEL:-network share}",
    "copied": $1,
    "verified": $2,
    "failed": $3,
    "held_offsite": ${4:-0},
    "message": "${5:-ok}"
}
JSON
    mv "${STATUS_FILE}.partial" "$STATUS_FILE"
}

reachable() {
    # A CIFS mount that has dropped still looks like a directory; writing is
    # the only honest test of whether the share is actually there.
    [ -d "$OFFSITE_DIR" ] || return 1
    touch "${OFFSITE_DIR}/.write-probe" 2>/dev/null || return 1
    rm -f "${OFFSITE_DIR}/.write-probe" 2>/dev/null
    return 0
}

replicate() {
    local copied=0 verified=0 failed=0 held=0

    if ! reachable; then
        log "share at ${OFFSITE_DIR} is not writable — nothing copied"
        write_status 0 0 1 0 "share not reachable or not writable"
        return 1
    fi

    # Both halves of a backup: the dump and the signatures it references.
    # Copying only the database would put an incomplete audit record on
    # the share, which is the one place it needs to be complete.
    for src in "$LOCAL_DIR"/*.sql.gz "$LOCAL_DIR"/*.tar.gz; do
        [ -e "$src" ] || continue

        local name dest want got
        name=$(basename "$src")
        dest="${OFFSITE_DIR}/${name}"

        # Already there and intact? Leave it alone — this runs nightly over a
        # fortnight of dumps and must not re-send them all every time.
        if [ -f "$dest" ]; then
            want=$(sha256sum "$src" | awk '{print $1}')
            got=$(sha256sum "$dest" 2>/dev/null | awk '{print $1}')
            if [ "$want" = "$got" ]; then
                continue
            fi
            log "re-copying ${name}: the copy on the share does not match"
        fi

        # Write to a temporary name, verify from the share, then rename. An
        # interrupted transfer must never occupy the real filename, or the
        # check above will treat it as done.
        if ! cp "$src" "${dest}.partial" 2>/dev/null; then
            log "FAILED to copy ${name}"
            rm -f "${dest}.partial" 2>/dev/null
            failed=$((failed + 1))
            continue
        fi

        copied=$((copied + 1))

        want=$(sha256sum "$src" | awk '{print $1}')
        got=$(sha256sum "${dest}.partial" 2>/dev/null | awk '{print $1}')

        if [ "$want" != "$got" ]; then
            log "FAILED verification for ${name} — removing the bad copy"
            rm -f "${dest}.partial" 2>/dev/null
            failed=$((failed + 1))
            continue
        fi

        mv "${dest}.partial" "$dest" 2>/dev/null
        cp "${src}.sha256" "${dest}.sha256" 2>/dev/null || true
        verified=$((verified + 1))
        log "copied and verified ${name}"
    done

    # Off-site keeps a longer history than the host: it is the archive, and it
    # is the copy that survives losing the machine.
    local pruned
    pruned=$(find "$OFFSITE_DIR" -maxdepth 1 \( -name '*.sql.gz' -o -name '*.tar.gz' \) -mtime "+${RETENTION_DAYS}" -print -delete 2>/dev/null | wc -l)
    find "$OFFSITE_DIR" -maxdepth 1 -name '*.sha256' -mtime "+${RETENTION_DAYS}" -delete 2>/dev/null
    find "$OFFSITE_DIR" -maxdepth 1 -name '*.partial' -mtime +1 -delete 2>/dev/null
    [ "$pruned" -gt 0 ] && log "pruned ${pruned} off-site backup(s) older than ${RETENTION_DAYS} days"

    held=$(find "$OFFSITE_DIR" -maxdepth 1 \( -name '*.sql.gz' -o -name '*.tar.gz' \) 2>/dev/null | wc -l)

    if [ "$failed" -gt 0 ]; then
        write_status "$copied" "$verified" "$failed" "$held" "${failed} file(s) failed to copy or verify"
        log "run finished with ${failed} failure(s); ${held} backup(s) now on the share"
        return 1
    fi

    write_status "$copied" "$verified" 0 "$held" "ok"
    log "run finished: ${verified} new, ${held} backup(s) now on the share"
    return 0
}

seconds_until() {
    local now target next
    now=$(date +%s)
    target=$(date -d "today ${OFFSITE_TIME}" +%s 2>/dev/null) || return 1
    if [ "$target" -le "$now" ]; then
        next=$(date -d "tomorrow ${OFFSITE_TIME}" +%s)
    else
        next=$target
    fi
    echo $((next - now))
}

case "${1:-loop}" in
    once)
        replicate
        ;;
    loop)
        log "off-site copier started — nightly at ${OFFSITE_TIME}, keeping ${RETENTION_DAYS} days on the share"
        while true; do
            wait_for=$(seconds_until) || { log "BACKUP_OFFSITE_TIME='${OFFSITE_TIME}' is not a time (want HH:MM)"; exit 64; }
            log "next off-site copy in $((wait_for / 3600))h $(((wait_for % 3600) / 60))m"
            sleep "$wait_for"

            # A failure never stops the service: the share may be back
            # tomorrow, and the status file records the gap either way.
            replicate || log "continuing despite the failure above"
        done
        ;;
    *)
        echo "usage: $0 [once|loop]" >&2
        exit 64
        ;;
esac
