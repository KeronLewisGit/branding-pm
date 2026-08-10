<#
    Put a "Branding PM" icon on the desktop.

    Run once, from anywhere:

        powershell -ExecutionPolicy Bypass -File scripts\install-desktop-shortcut.ps1

    Two things happen:

      1. The PWA icon is converted to a Windows .ico, because a .lnk cannot use
         a PNG. Built from icon-512.png so the shortcut, the installed PWA and
         the browser tab all wear the same badge.
      2. A shortcut is written to the desktop pointing at start-branding-pm.ps1.

    -AllUsers writes to the Public desktop instead, so every account on a
    shared shop-floor PC gets it. That needs an elevated shell.
#>

[CmdletBinding()]
param(
    [switch] $AllUsers,
    [string] $Name = 'Branding PM'
)

$ErrorActionPreference = 'Stop'

$Root      = Split-Path -Parent $PSScriptRoot
$Launcher  = Join-Path $PSScriptRoot 'start-branding-pm.ps1'
$SourceIcon = Join-Path $Root 'public\icons\icon-512.png'
$IcoPath   = Join-Path $PSScriptRoot 'branding-pm.ico'

if (-not (Test-Path $Launcher)) {
    throw "Launcher not found at $Launcher"
}

# ---------------------------------------------------------------------------
# PNG -> ICO
# ---------------------------------------------------------------------------
# Hand-rolled rather than shelled out to a converter: this has to work on a
# plant PC with nothing installed but Windows and Docker. The ICO container is
# a 6-byte header, a 16-byte directory entry, then the PNG bytes verbatim --
# PNG-compressed icons are valid from Windows Vista onward.
if (-not (Test-Path $IcoPath)) {
    if (-not (Test-Path $SourceIcon)) {
        throw "Icon source not found at $SourceIcon"
    }

    Add-Type -AssemblyName System.Drawing

    # 256x256 is the largest size the directory can describe, and it is stored
    # as 0 -- a literal 256 does not fit in one byte.
    $png = [System.Drawing.Image]::FromFile($SourceIcon)
    $resized = New-Object System.Drawing.Bitmap($png, 256, 256)
    $png.Dispose()

    $buffer = New-Object System.IO.MemoryStream
    $resized.Save($buffer, [System.Drawing.Imaging.ImageFormat]::Png)
    $resized.Dispose()

    $pngBytes = $buffer.ToArray()
    $buffer.Dispose()

    $ico = New-Object System.IO.MemoryStream
    $writer = New-Object System.IO.BinaryWriter($ico)

    $writer.Write([UInt16] 0)      # reserved
    $writer.Write([UInt16] 1)      # type: 1 = icon
    $writer.Write([UInt16] 1)      # image count

    $writer.Write([Byte] 0)        # width  (0 means 256)
    $writer.Write([Byte] 0)        # height (0 means 256)
    $writer.Write([Byte] 0)        # palette size
    $writer.Write([Byte] 0)        # reserved
    $writer.Write([UInt16] 1)      # colour planes
    $writer.Write([UInt16] 32)     # bits per pixel
    $writer.Write([UInt32] $pngBytes.Length)
    $writer.Write([UInt32] 22)     # offset: 6-byte header + 16-byte entry

    $writer.Write($pngBytes)
    $writer.Flush()

    [System.IO.File]::WriteAllBytes($IcoPath, $ico.ToArray())

    $writer.Dispose()
    $ico.Dispose()

    Write-Host "  Icon written to $IcoPath" -ForegroundColor Green
}

# ---------------------------------------------------------------------------
# The shortcut
# ---------------------------------------------------------------------------
$desktop = if ($AllUsers) {
    Join-Path $env:PUBLIC 'Desktop'
} else {
    [Environment]::GetFolderPath('Desktop')
}

$linkPath = Join-Path $desktop "$Name.lnk"

$shell = New-Object -ComObject WScript.Shell
$link = $shell.CreateShortcut($linkPath)

$link.TargetPath = 'powershell.exe'

# -WindowStyle Minimized rather than Hidden: the launcher reports progress and
# can fail, and a failure nobody is shown is a support call. Minimised keeps it
# out of the way while leaving it there to read.
$link.Arguments = "-ExecutionPolicy Bypass -WindowStyle Minimized -File `"$Launcher`""

$link.WorkingDirectory = $Root
$link.IconLocation = $IcoPath
$link.Description = 'Start Branding PM and open the kiosk'
$link.Save()

Write-Host "  Shortcut written to $linkPath" -ForegroundColor Green
Write-Host ''
Write-Host '  Double-click it to start the system and open the kiosk.' -ForegroundColor Cyan
Write-Host ''
