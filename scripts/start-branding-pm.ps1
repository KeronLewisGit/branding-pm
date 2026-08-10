<#
    Start Branding PM and open it.

    This is what the desktop icon runs. It exists because starting the system
    otherwise means opening a terminal and remembering a compose command, and
    on the morning somebody else has to do it that is one step too many.

    What it does, in order:

      1. Start Docker Desktop if it is not already running, and wait for the
         engine to answer. A cold Docker Desktop takes 30-60s, which is longer
         than anybody will wait at a black window, so it says so.
      2. `docker compose up -d`, including the tls profile when one is
         configured.
      3. Poll /up until the application actually answers. Compose returning is
         not the same as Laravel being ready: MySQL still has to come up and
         php-fpm has to accept a request, and opening the browser before then
         shows a connection error that reads like a broken install.
      4. Open the kiosk in its own window.

    Run it by hand with -NoBrowser to start the stack without opening anything.
#>

[CmdletBinding()]
param(
    [switch] $NoBrowser,

    # Seconds to wait for the application to answer before giving up.
    [int] $TimeoutSeconds = 180
)

$ErrorActionPreference = 'Stop'

# The repository root, resolved from this script's own location so the
# shortcut works regardless of where it is invoked from.
$Root = Split-Path -Parent $PSScriptRoot

# ---------------------------------------------------------------------------
# Running native executables from PowerShell 5.1
# ---------------------------------------------------------------------------
# `docker ... 2>&1` looks like the obvious way to keep the console quiet, and
# it is a trap here. In Windows PowerShell 5.1 redirecting a native command's
# stderr wraps every line in an ErrorRecord, which with $ErrorActionPreference
# = 'Stop' THROWS -- even when the exe exited 0. Both `docker info` and
# `docker compose up` write routine progress to stderr, so the first version of
# this script decided a perfectly healthy Docker was dead and offered to start
# it again.
#
# So: never redirect a native command's stderr. Drop to 'Continue' around the
# call, let stderr go to the console where a real error is worth seeing, and
# judge success by $LASTEXITCODE alone.
function Invoke-Native {
    param(
        [Parameter(Mandatory)] [scriptblock] $Command,
        [switch] $Quiet
    )

    $previous = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    try {
        if ($Quiet) { & $Command | Out-Null } else { & $Command }
        return $LASTEXITCODE -eq 0
    } finally {
        $ErrorActionPreference = $previous
    }
}

function Write-Step { param([string] $Message) Write-Host "  $Message" -ForegroundColor Cyan }
function Write-Ok   { param([string] $Message) Write-Host "  $Message" -ForegroundColor Green }
function Write-Bad  { param([string] $Message) Write-Host "  $Message" -ForegroundColor Red }

Write-Host ''
Write-Host '  Branding PM' -ForegroundColor White
Write-Host '  ===========' -ForegroundColor DarkGray
Write-Host ''

# ---------------------------------------------------------------------------
# The port and address come from .env, so this script never disagrees with the
# application about where it lives.
# ---------------------------------------------------------------------------
$AppPort = '8088'
$EnvFile = Join-Path $Root '.env'

if (Test-Path $EnvFile) {
    $portLine = Select-String -Path $EnvFile -Pattern '^\s*APP_PORT\s*=\s*(\S+)' -ErrorAction SilentlyContinue | Select-Object -First 1
    if ($portLine) { $AppPort = $portLine.Matches[0].Groups[1].Value.Trim('"').Trim("'") }
} else {
    Write-Bad '.env not found. Copy .env.example to .env first.'
    Read-Host '  Press Enter to close'
    exit 1
}

$AppUrl   = "http://localhost:$AppPort"
$KioskUrl = "$AppUrl/kiosk"

# ---------------------------------------------------------------------------
# 1. Docker engine
# ---------------------------------------------------------------------------
Write-Step 'Checking Docker...'

$engineUp = Invoke-Native { docker info --format '{{.ServerVersion}}' } -Quiet

if (-not $engineUp) {
    $desktop = 'C:\Program Files\Docker\Docker\Docker Desktop.exe'

    if (-not (Test-Path $desktop)) {
        Write-Bad 'Docker Desktop is not installed at the usual place, and the engine is not running.'
        Read-Host '  Press Enter to close'
        exit 1
    }

    Write-Step 'Starting Docker Desktop (this takes up to a minute on a cold start)...'
    Start-Process -FilePath $desktop | Out-Null

    $waited = 0
    while (-not $engineUp -and $waited -lt 120) {
        Start-Sleep -Seconds 3
        $waited += 3
        $engineUp = Invoke-Native { docker info --format '{{.ServerVersion}}' } -Quiet
    }

    if (-not $engineUp) {
        Write-Bad 'Docker did not start. Open Docker Desktop and try again.'
        Read-Host '  Press Enter to close'
        exit 1
    }
}

Write-Ok 'Docker is running.'

# ---------------------------------------------------------------------------
# 2. The stack
# ---------------------------------------------------------------------------
Set-Location $Root

# Only ask for the tls profile when an address is actually configured for it.
# Starting it without one leaves Caddy holding a certificate for nothing.
$profiles = @()
$tlsLine = Select-String -Path $EnvFile -Pattern '^\s*TLS_SITE_ADDRESS\s*=\s*(\S+)' -ErrorAction SilentlyContinue | Select-Object -First 1
if ($tlsLine) { $profiles += 'tls' }

Write-Step 'Starting services...'

$started = if ($profiles.Count -gt 0) {
    Invoke-Native { docker compose --profile tls up -d }
} else {
    Invoke-Native { docker compose up -d }
}

if (-not $started) {
    Write-Bad 'docker compose failed. Run it by hand to see why:'
    Write-Host "    cd `"$Root`"; docker compose up -d" -ForegroundColor DarkGray
    Read-Host '  Press Enter to close'
    exit 1
}

# ---------------------------------------------------------------------------
# 3. Wait for the application, not merely for the containers
# ---------------------------------------------------------------------------
Write-Step 'Waiting for the application to answer...'

$ready = $false
$waited = 0

while (-not $ready -and $waited -lt $TimeoutSeconds) {
    try {
        $response = Invoke-WebRequest -Uri "$AppUrl/up" -UseBasicParsing -TimeoutSec 5
        if ($response.StatusCode -eq 200) { $ready = $true; break }
    } catch { }

    Start-Sleep -Seconds 2
    $waited += 2
}

if (-not $ready) {
    Write-Bad "The application did not answer on $AppUrl within $TimeoutSeconds seconds."
    Write-Host '    docker compose ps          - what is running' -ForegroundColor DarkGray
    Write-Host '    docker compose logs php    - why it is not' -ForegroundColor DarkGray
    Read-Host '  Press Enter to close'
    exit 1
}

Write-Ok "Ready at $AppUrl"

# ---------------------------------------------------------------------------
# 4. Open it
# ---------------------------------------------------------------------------
if (-not $NoBrowser) {
    # --app gives a window with no address bar or tabs, which is what a kiosk
    # wants and what makes this feel like an application rather than a page.
    # Falls back to the default browser when neither Chrome nor Edge is found.
    $chrome = @(
        'C:\Program Files\Google\Chrome\Application\chrome.exe',
        'C:\Program Files (x86)\Google\Chrome\Application\chrome.exe',
        'C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe',
        'C:\Program Files\Microsoft\Edge\Application\msedge.exe'
    ) | Where-Object { Test-Path $_ } | Select-Object -First 1

    if ($chrome) {
        Start-Process -FilePath $chrome -ArgumentList "--app=$KioskUrl" | Out-Null
    } else {
        Start-Process $KioskUrl | Out-Null
    }
}

Write-Host ''
Write-Ok 'Branding PM is running. Closing this window leaves it running.'
Write-Host ''
Start-Sleep -Seconds 3
