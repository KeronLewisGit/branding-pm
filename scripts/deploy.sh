#!/usr/bin/env bash
#
# Deploy the current branch on a shared host. Run it over SSH, from anywhere:
#
#     bash scripts/deploy.sh
#
# It is the whole update procedure, in the order that matters, because doing
# these by hand is how a step gets skipped — and the two most-skipped ones
# (rebuilding the caches, and re-running composer when the lock file moved)
# both fail in ways that look like the code is broken rather than the deploy.
#
# PHP: shared hosts routinely default the CLI to an older PHP than the site
# runs. Set PHP_BIN when `php -v` is not the version the app needs:
#
#     PHP_BIN=/opt/alt/php84/usr/bin/php bash scripts/deploy.sh
#
set -euo pipefail

cd "$(dirname "$0")/.."

PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-$(command -v composer || true)}"

say()  { printf '\n\033[36m==> %s\033[0m\n' "$1"; }
ok()   { printf '\033[32m    %s\033[0m\n' "$1"; }
fail() { printf '\033[31m    %s\033[0m\n' "$1" >&2; exit 1; }

# ---------------------------------------------------------------------------
# Preflight
# ---------------------------------------------------------------------------
[ -f .env ] || fail ".env is missing. This is not a configured install."

PHP_VERSION="$("$PHP_BIN" -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
case "$PHP_VERSION" in
    8.4|8.5) ok "PHP $PHP_VERSION" ;;
    *) fail "PHP $PHP_VERSION — this application requires 8.4. Set PHP_BIN to the right binary." ;;
esac

# Boot before taking anything down.
#
# A stale bootstrap/cache/packages.php -- one written while dev dependencies
# were installed, then left behind by `composer install --no-dev` -- names a
# service provider that no longer exists, and the failure is PRE-boot. That
# means artisan cannot rescue it: up, optimize:clear and config:clear all have
# to boot first. Discovering that after `artisan down` leaves the site off with
# no artisan command able to bring it back.
if ! "$PHP_BIN" artisan --version >/dev/null 2>&1; then
    printf '
'
    "$PHP_BIN" artisan --version || true
    printf '
'
    fail "The application does not boot, so there is nothing safe to deploy. If this is a stale package manifest: rm -f bootstrap/cache/packages.php bootstrap/cache/services.php && composer install --no-dev --optimize-autoloader"
fi
ok "application boots"

# A modified TRACKED file means somebody edited the application on the server.
# Pulling over that either fails or silently discards their fix, and both are
# worse than stopping to ask.
#
# Untracked files are ignored here on purpose: they are none of a deploy's
# business, and git refuses by itself if one would actually be overwritten.
# Guarding on them too would block every deploy over a stray note file.
if [ -n "$(git status --porcelain --untracked-files=no)" ]; then
    printf '\n'
    git status --short --untracked-files=no
    fail "The working tree has local changes. Commit, stash or discard them before deploying."
fi

# ---------------------------------------------------------------------------
# Maintenance mode, with a guaranteed way back up
# ---------------------------------------------------------------------------
# Without the trap, a failed migration leaves the site down until somebody
# notices and knows the command.
# `artisan up` is tried first because it is the clean way, and the files are
# removed directly when it cannot run — which is exactly the case that matters,
# since a deploy fails hardest when the application has stopped booting.
restore() {
    "$PHP_BIN" artisan up >/dev/null 2>&1 && return
    rm -f storage/framework/down storage/framework/maintenance.php
    printf '[33m    artisan could not run; maintenance files removed directly.[0m
' >&2
}
trap restore EXIT

say "Maintenance mode"
"$PHP_BIN" artisan down --retry=60 >/dev/null 2>&1 || true

# ---------------------------------------------------------------------------
# Pull
# ---------------------------------------------------------------------------
say "Fetching"
LOCK_BEFORE="$(md5sum composer.lock 2>/dev/null | cut -d' ' -f1 || echo none)"

# --ff-only: a merge commit created on a production server is a branch nobody
# will ever push back, and a conflict here should stop the deploy, not start a
# merge in a shell with no editor.
git pull --ff-only

LOCK_AFTER="$(md5sum composer.lock 2>/dev/null | cut -d' ' -f1 || echo none)"

# ---------------------------------------------------------------------------
# Dependencies — only when the lock actually moved
# ---------------------------------------------------------------------------
if [ "$LOCK_BEFORE" != "$LOCK_AFTER" ]; then
    say "composer.lock changed — installing"
    [ -n "$COMPOSER_BIN" ] || fail "composer not found. Set COMPOSER_BIN."
    "$PHP_BIN" "$COMPOSER_BIN" install --no-dev --optimize-autoloader --no-interaction
else
    ok "composer.lock unchanged — skipping install"
fi

# ---------------------------------------------------------------------------
# Schema
# ---------------------------------------------------------------------------
if ! "$PHP_BIN" artisan --version >/dev/null 2>&1; then
    fail "The application stopped booting after the update. Nothing has been migrated."
fi

say "Migrations"
"$PHP_BIN" artisan migrate --force

# ---------------------------------------------------------------------------
# Caches
# ---------------------------------------------------------------------------
# Cleared before being rebuilt: `config:cache` on top of a stale cache reads
# the OLD cache rather than .env, so a changed setting appears to be ignored.
say "Rebuilding caches"
"$PHP_BIN" artisan optimize:clear >/dev/null
"$PHP_BIN" artisan config:cache >/dev/null
"$PHP_BIN" artisan route:cache >/dev/null
"$PHP_BIN" artisan view:cache >/dev/null
ok "config, route and view caches rebuilt"

# ---------------------------------------------------------------------------
# Writable paths
# ---------------------------------------------------------------------------
# Re-applied every deploy: an unwritable storage/ makes every authenticated
# page 500 at once, because @can() writes the permission cache — and the log
# is usually unwritable too, so nothing says why.
say "Permissions"
chmod -R ug+rwX storage bootstrap/cache
ok "storage and bootstrap/cache writable"

# storage:link is a no-op when the link exists, and the one thing people
# forget after moving an install.
"$PHP_BIN" artisan storage:link >/dev/null 2>&1 || true

# ---------------------------------------------------------------------------
# Up, then verify
# ---------------------------------------------------------------------------
say "Back up"
"$PHP_BIN" artisan up >/dev/null
trap - EXIT
ok "live"

say "Verifying"
"$PHP_BIN" artisan security:check || true

printf '\n\033[32m    Deployed: %s\033[0m\n\n' "$(git log -1 --format='%h %s')"
