<#
.SYNOPSIS
    Builds wp-static-runtime.zip for WordPress upload (single plugin folder at zip root).

.NOTES
    WordPress expects: wp-content/plugins/<one-folder>/<main-plugin>.php
    Wrong layout causes "Plugin file does not exist" when activating.

    BENTUK SALAH (folder di dalam folder nama sama):
      wp-static-runtime/wp-static-runtime/wp-static-runtime.php
    BENTUK BENAR (hanya satu tingkat setelah root zip):
      wp-static-runtime/wp-static-runtime.php
#>

$ErrorActionPreference = 'Stop'
$RepoRoot = Split-Path -Parent $PSScriptRoot
$DistDir = Join-Path $RepoRoot 'dist'
$StageName = 'wp-static-runtime'
$StagePath = Join-Path $DistDir $StageName
$ZipPath = Join-Path $DistDir 'wp-static-runtime.zip'

Remove-Item $DistDir -Recurse -Force -ErrorAction SilentlyContinue
New-Item -ItemType Directory -Path $StagePath -Force | Out-Null

$Include = @(
    'wp-static-runtime.php',
    'uninstall.php',
    'README.md',
    'INSTALL.txt',
    'free',
    'premium-ui'
)

foreach ($item in $Include) {
    $src = Join-Path $RepoRoot $item
    if (-not (Test-Path $src)) { throw "Missing: $src" }
    Copy-Item $src -Destination $StagePath -Recurse
}

$MainPhp = Join-Path $StagePath 'wp-static-runtime.php'
if (-not (Test-Path $MainPhp)) { throw 'Main plugin file missing after stage.' }

# Larangan: subfolder "wp-static-runtime" lagi di dalam root plugin (double nest).
$NestedPluginDir = Join-Path $StagePath 'wp-static-runtime'
if (Test-Path -LiteralPath $NestedPluginDir) {
    throw @"
STAGING INVALID: there must be no folder named 'wp-static-runtime' inside the plugin root.
  Found: $NestedPluginDir
  Fix your copy list — the zip must flatten to: wp-static-runtime/<main>.php, free/, premium-ui/, NOT wp-static-runtime/wp-static-runtime/...
"@
}

Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem
if (Test-Path $ZipPath) { Remove-Item $ZipPath -Force }

# Create zip manually to ensure forward slashes (standard ZIP format)
$stream = [System.IO.File]::OpenWrite($ZipPath)
$archive = New-Object System.IO.Compression.ZipArchive($stream, [System.IO.Compression.ZipArchiveMode]::Create)

try {
    $files = Get-ChildItem -Path $StagePath -Recurse | Where-Object { -not $_.PSIsContainer }
    foreach ($file in $files) {
        # Get relative path and force forward slashes
        $relativeName = $file.FullName.Substring($StagePath.Length).TrimStart('\').Replace('\', '/')
        
        $entry = $archive.CreateEntry($relativeName, [System.IO.Compression.CompressionLevel]::Optimal)
        $entryStream = $entry.Open()
        $fileStream = [System.IO.File]::OpenRead($file.FullName)
        $fileStream.CopyTo($entryStream)
        $fileStream.Close()
        $entryStream.Close()
    }
}
finally {
    $archive.Dispose()
    $stream.Close()
}

# Verify zip has main php at the top level
$zip = [System.IO.Compression.ZipFile]::OpenRead($ZipPath)
try {
    $entries = $zip.Entries | Where-Object { $_.FullName -and ($_.FullName -notmatch '[\\/]$') }
    
    # Pastikan file utama ada di root zip (bukan di dalam subfolder)
    # Gunakan normalisasi slash untuk pengecekan verifikasi juga
    $mainEntry = $entries | Where-Object {
        ($_.FullName -replace '\\', '/') -eq "wp-static-runtime.php"
    }
    if (-not $mainEntry) {
        throw "Zip INVALID: wp-static-runtime.php must be at the zip root."
    }

    # Pastikan entry name benar-benar menggunakan forward slash (kode 47)
    $badEntry = $entries | Where-Object { $_.FullName -match '\\' }
    if ($badEntry) {
        throw "Zip INVALID: Backslashes found in entry names: $($badEntry[0].FullName). All paths must use '/'."
    }
}
finally {
    $zip.Dispose()
}

Write-Host "OK: $ZipPath ($([math]::Round((Get-Item $ZipPath).Length / 1KB, 1)) KB)"
