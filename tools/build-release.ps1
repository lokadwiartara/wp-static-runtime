<#
.SYNOPSIS
    Builds wp-static-runtime.zip for WordPress upload (single plugin folder at zip root).

.NOTES
    WordPress expects: wp-content/plugins/<one-folder>/<main-plugin>.php
    Wrong layout causes "Plugin file does not exist" when activating.
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

Compress-Archive -Path $StagePath -DestinationPath $ZipPath -Force

# Verify zip root is exactly one folder "wp-static-runtime" with main php inside
Add-Type -AssemblyName System.IO.Compression.FileSystem
$zip = [System.IO.Compression.ZipFile]::OpenRead($ZipPath)
try {
    $entries = $zip.Entries | Where-Object { $_.FullName -and ($_.FullName -notmatch '[\\/]$') }
    $roots = @( $entries | ForEach-Object {
        $norm = $_.FullName -replace '\\', '/'
        ($norm -split '/')[0]
    } | Sort-Object -Unique )
    if ($roots.Count -ne 1 -or $roots[0] -ne $StageName) {
        throw "Zip must have single root folder '$StageName'. Found: $($roots -join ', ')"
    }
    $mainEntry = $entries | Where-Object {
        ($_.FullName -replace '\\', '/') -eq "$StageName/wp-static-runtime.php"
    }
    if (-not $mainEntry) {
        throw "Zip missing entry ${StageName}/wp-static-runtime.php"
    }

    # Fail if zip accidentally contains nested wp-static-runtime/wp-static-runtime/
    # (WordPress then expects plugin=wp-static-runtime/wp-static-runtime/wp-static-runtime.php → file missing).
    $nestedBad = $entries | Where-Object {
        ($_.FullName -replace '\\', '/') -match '^wp-static-runtime/wp-static-runtime/'
    }
    if ($nestedBad) {
        throw "Zip must NOT contain nested folder wp-static-runtime/wp-static-runtime/. Bad entries: $($nestedBad[0].FullName) ..."
    }
}
finally {
    $zip.Dispose()
}

Write-Host "OK: $ZipPath ($([math]::Round((Get-Item $ZipPath).Length / 1KB, 1)) KB)"
