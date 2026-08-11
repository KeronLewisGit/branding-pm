<#
    Build an upload-ready bundle for Hostinger (or any Apache/LiteSpeed host).

        powershell -ExecutionPolicy Bypass -File scripts\package-for-hostinger.ps1

    Produces build/branding-pm-hostinger-<date>.zip plus an .env template to
    fill in. Unzip it above `public_html`, point the domain's document root at
    its `public` directory, then run composer and artisan over SSH — see
    docs/DEPLOYMENT.md §13.

    What it deliberately leaves out, and why each one matters:

    - `vendor/` — installed on the server with `composer install --no-dev`.
      It also cannot simply be copied from here: this project keeps vendor in
      a Docker named volume rather than on the host, and the copy in it holds
      the dev dependencies (Pest, Faker, Breeze) that must never reach a
      production host.
    - `bootstrap/cache/*` — a cached config file is the classic way a Docker
      database host follows the code to production and is then impossible to
      override from `.env`.
    - `storage/` contents — logs, backups, sessions and the signature images.
      The empty skeleton ships so the framework has its directories; the data
      does not, because it belongs to the machine it was written on.
    - `docker/`, `docker-compose.yml`, `scripts/` — the local runtime and a
      Windows launcher. Neither means anything on LiteSpeed.
    - `tests/`, `phpunit.xml`, `node_modules/` — build and check tooling.
    - `.env` — never. The template is written separately.

    `public/build` IS included: it is gitignored, so a clone on the server has
    no compiled CSS or JS, and building on shared hosting is a poor idea.
#>

[CmdletBinding()]
param(
    [string] $OutputDir = 'build',
    [switch] $SkipAssetBuild
)

$ErrorActionPreference = 'Stop'

$Root = Split-Path -Parent $PSScriptRoot
Set-Location $Root

function Step { param([string] $m) Write-Host "  $m" -ForegroundColor Cyan }
function Ok   { param([string] $m) Write-Host "  $m" -ForegroundColor Green }
function Bad  { param([string] $m) Write-Host "  $m" -ForegroundColor Red }

Write-Host ''
Write-Host '  Packaging Branding PM for Hostinger' -ForegroundColor White
Write-Host '  ===================================' -ForegroundColor DarkGray
Write-Host ''

# ---------------------------------------------------------------------------
# Preflight — the two things whose absence is silent until it is live
# ---------------------------------------------------------------------------
if (-not (Test-Path (Join-Path $Root 'public\.htaccess'))) {
    Bad 'public/.htaccess is missing. On LiteSpeed the home page loads and every other route 404s.'
    exit 1
}
Ok 'public/.htaccess present'

if (-not $SkipAssetBuild) {
    Step 'Building assets (npm run build)...'
    $prev = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    npm run build
    $built = $LASTEXITCODE -eq 0
    $ErrorActionPreference = $prev

    if (-not $built) { Bad 'npm run build failed.'; exit 1 }
}

if (-not (Test-Path (Join-Path $Root 'public\build\manifest.json'))) {
    Bad 'public/build/manifest.json is missing — the server would render an unstyled page.'
    exit 1
}
Ok 'compiled assets present'

# ---------------------------------------------------------------------------
# Stage
# ---------------------------------------------------------------------------
$stamp = Get-Date -Format 'yyyy-MM-dd-HHmm'
$outDirFull = Join-Path $Root $OutputDir
$stage = Join-Path $outDirFull "stage-$stamp"

New-Item -ItemType Directory -Force -Path $stage | Out-Null

# Whole directories that travel as-is.
$includeDirs = @('app', 'bootstrap', 'config', 'database', 'lang', 'public', 'resources', 'routes')
$includeFiles = @('artisan', 'composer.json', 'composer.lock', 'package.json', 'package-lock.json',
                  'vite.config.js', 'tailwind.config.js', 'postcss.config.js', 'CHANGELOG.md', 'README.md')

Step 'Copying application...'
foreach ($d in $includeDirs) {
    $src = Join-Path $Root $d
    if (Test-Path $src) { Copy-Item -Recurse -Force $src (Join-Path $stage $d) }
}
foreach ($f in $includeFiles) {
    $src = Join-Path $Root $f
    if (Test-Path $src) { Copy-Item -Force $src (Join-Path $stage $f) }
}
Copy-Item -Recurse -Force (Join-Path $Root 'docs') (Join-Path $stage 'docs')

# Anything that came along inside those directories and must not ship.
Step 'Removing local state...'
$strip = @(
    'bootstrap\cache\*',
    'public\hot',
    'public\storage'
)
foreach ($pattern in $strip) {
    $p = Join-Path $stage $pattern
    if (Test-Path $p) { Remove-Item -Recurse -Force $p }
}

# Symlinks, separately and by attribute.
#
# `public/storage` is a symlink to a path INSIDE the container, so on this
# host it is a broken reparse point: Test-Path returns false for it, the strip
# above silently skips it, and the archiver then dies on "the file cannot be
# accessed by the system". `storage:link` recreates it on the server anyway,
# which is why it has no business in the bundle.
Get-ChildItem -Recurse -Force $stage |
    Where-Object { $_.Attributes -band [IO.FileAttributes]::ReparsePoint } |
    ForEach-Object {
        Step "  dropping symlink $($_.Name)"
        [IO.Directory]::Delete($_.FullName, $true) 2>$null
        if (Test-Path -LiteralPath $_.FullName) { [IO.File]::Delete($_.FullName) }
    }

# ---------------------------------------------------------------------------
# storage/ — skeleton only
# ---------------------------------------------------------------------------
# Rebuilt from scratch rather than copied and pruned: copying it first would
# carry every signature image and log line into the staging directory, and a
# missed pattern there ships plant data to a shared host.
Step 'Rebuilding the storage skeleton...'
$storageDirs = @(
    'storage\app\public',
    'storage\backups',
    'storage\framework\cache\data',
    'storage\framework\sessions',
    'storage\framework\views',
    'storage\logs'
)
# bootstrap/cache belongs in this list too. Its CONTENTS must not ship (a
# cached config would carry the Docker database host to production), but the
# DIRECTORY must: the archive stores files only, so stripping the contents
# removed the folder entirely and Laravel refused to boot with "The
# bootstrap/cache directory must be present and writable." Found by extracting
# the bundle and running artisan, not by reading the file list.
$skeletonDirs = $storageDirs + @('bootstrap\cache')

foreach ($d in $skeletonDirs) {
    New-Item -ItemType Directory -Force -Path (Join-Path $stage $d) | Out-Null
    # Keep the directory in the archive, and keep its contents out of git on
    # the far side too.
    Set-Content -Path (Join-Path $stage "$d\.gitignore") -Value "*`n!.gitignore" -Encoding utf8
}

# ---------------------------------------------------------------------------
# The .env template
# ---------------------------------------------------------------------------
Step 'Writing .env.hostinger...'
$envTemplate = @'
# Branding PM — Hostinger (Cloud Startup / shared LiteSpeed)
#
# Rename to .env on the server, fill in every <angled> value, then:
#
#   php artisan key:generate
#   php artisan migrate --force --seed
#   php artisan storage:link
#   php artisan config:cache && php artisan route:cache && php artisan view:cache
#
# Re-run those three :cache commands after EVERY change to this file. A cached
# config ignores .env entirely.

APP_NAME="Branding PM"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://<your-domain>

# Stored UTC, displayed here. Do not change APP_TIMEZONE.
APP_TIMEZONE=UTC
APP_DISPLAY_TIMEZONE=America/Port_of_Spain

APP_LOCALE=en
APP_FALLBACK_LOCALE=en

LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=error

# hPanel -> Databases -> MySQL. Hostinger prefixes both with your account id.
DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=<uXXXXXXX_branding_pm>
DB_USERNAME=<uXXXXXXX_pm>
DB_PASSWORD=<from hPanel>

# No Redis, no resident worker. All three are backed by the database or disk.
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
QUEUE_CONNECTION=database
CACHE_STORE=file
FILESYSTEM_DISK=local
BROADCAST_CONNECTION=log

# HTTPS is on from the start here, so this can be true from day one. It is
# what stops a session cookie crossing the wire in the clear.
SESSION_SECURE_COOKIE=true

# SendGrid. MAIL_USERNAME is the literal word "apikey" for every account; the
# API key itself goes in MAIL_PASSWORD. The from-address must be a verified
# sender or on an authenticated domain, or SendGrid rejects the message.
#
# NOTE: config/mail.php reads MAIL_ENCRYPTION, not MAIL_SCHEME.
MAIL_MAILER=smtp
MAIL_HOST=smtp.sendgrid.net
MAIL_PORT=587
MAIL_USERNAME=apikey
MAIL_PASSWORD=<SG.xxxxxxxx>
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=<no-reply@your-domain>
MAIL_FROM_NAME="${APP_NAME}"

# The first administrator's password. Leave BLANK and the seeder generates one
# and prints it ONCE.
ADMIN_PASSWORD=
ADMIN_EMAIL=<admin@your-domain>

# Kiosk idle drop, seconds. Two minutes suits a shared shop-floor tablet.
CHECKLISTS_KIOSK_IDLE_SECONDS=120

# Leave every BACKUP_OFFSITE_* value unset: that share is on the plant LAN and
# is unreachable from a datacentre. See docs/DEPLOYMENT.md 13.6.
'@
Set-Content -Path (Join-Path $stage '.env.hostinger') -Value $envTemplate -Encoding utf8

# ---------------------------------------------------------------------------
# Zip
# ---------------------------------------------------------------------------
$zip = Join-Path $outDirFull "branding-pm-hostinger-$stamp.zip"
if (Test-Path $zip) { Remove-Item -Force $zip }

# ---------------------------------------------------------------------------
# Written entry by entry, NOT with Compress-Archive.
#
# Windows PowerShell 5.1's Compress-Archive writes Windows path separators
# into the archive. The ZIP specification requires forward slashes, and
# unzipping such a file on Linux does not create directories -- it creates
# files whose names contain literal backslashes, so `public/index.php` arrives
# as a single file called `public\index.php` and nothing resolves. Caught by
# extracting a bundle inside the php container, where unzip warned outright:
# "appears to use backslashes as path separators".
Step 'Compressing (forward-slash entries)...'

Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

$archive = [System.IO.Compression.ZipFile]::Open($zip, [System.IO.Compression.ZipArchiveMode]::Create)
try {
    $stageFull = (Resolve-Path $stage).Path.TrimEnd([char]92)

    $files = Get-ChildItem -Recurse -File -Force $stage |
        Where-Object { -not ($_.Attributes -band [IO.FileAttributes]::ReparsePoint) }

    foreach ($file in $files) {
        $relative = $file.FullName.Substring($stageFull.Length + 1).Replace([char]92, '/')

        [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
            $archive, $file.FullName, $relative,
            [System.IO.Compression.CompressionLevel]::Optimal
        ) | Out-Null
    }
} finally {
    $archive.Dispose()
}

$sizeMb = [math]::Round((Get-Item $zip).Length / 1MB, 1)
$fileCount = (Get-ChildItem -Recurse -File $stage).Count

Remove-Item -Recurse -Force $stage

Write-Host ''
Ok "$zip"
Ok "$fileCount files, $sizeMb MB"
Write-Host ''
Write-Host '  Next, on the server:' -ForegroundColor White
Write-Host '    1. Unzip ABOVE public_html, e.g. ~/domains/<domain>/branding-pm' -ForegroundColor DarkGray
Write-Host '    2. Point the domain document root at that folder''s /public' -ForegroundColor DarkGray
Write-Host '    3. mv .env.hostinger .env  and fill it in' -ForegroundColor DarkGray
Write-Host '    4. composer install --no-dev --optimize-autoloader' -ForegroundColor DarkGray
Write-Host '    5. php artisan key:generate && php artisan migrate --force --seed' -ForegroundColor DarkGray
Write-Host '    6. php artisan storage:link && php artisan config:cache' -ForegroundColor DarkGray
Write-Host '    7. Cron, every minute: php ~/.../artisan schedule:run' -ForegroundColor DarkGray
Write-Host '    8. php artisan security:check' -ForegroundColor DarkGray
Write-Host ''
