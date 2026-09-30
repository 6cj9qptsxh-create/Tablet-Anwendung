# Deploy Haus Meli to the NAS web share and merge production keys into .env.
# Web Station virtual host: document root = .../haus-meli-app/public
# PHP 8.2+ | Apache 2.4 | Port 8081 (port 80 already serves /web)

$ErrorActionPreference = 'Stop'

$Root = Split-Path -Parent $PSScriptRoot
$Dest = if ($env:HAUS_MELI_NAS_DEST) {
    $env:HAUS_MELI_NAS_DEST
} else {
    '\\NAS\web\Tablet Anwendung\haus-meli-app'
}
$EnvExample = Join-Path $Root '.env.nas.example'
$LocalEnv = Join-Path $Root '.env'

function Read-DotEnv([string]$Path) {
    $map = [ordered]@{}
    if (-not (Test-Path -LiteralPath $Path)) { return $map }
    foreach ($line in [System.IO.File]::ReadAllLines($Path)) {
        if ($line -match '^\s*#' -or $line -notmatch '=') { continue }
        $name, $value = $line.Split('=', 2)
        $map[$name.Trim()] = $value
    }
    return $map
}

function Write-DotEnv([string]$Path, $Map) {
    $lines = foreach ($key in $Map.Keys) {
        '{0}={1}' -f $key, $Map[$key]
    }
    $utf8 = New-Object System.Text.UTF8Encoding $false
    [System.IO.File]::WriteAllLines($Path, [string[]]$lines, $utf8)
}

function Merge-NasEnv([string]$DestEnv) {
    $template = Read-DotEnv $EnvExample
    $existing = Read-DotEnv $DestEnv
    $local = Read-DotEnv $LocalEnv

    $merged = [ordered]@{}
    foreach ($key in $template.Keys) { $merged[$key] = $template[$key] }
    foreach ($key in $existing.Keys) {
        if (-not $merged.Contains($key)) { $merged[$key] = $existing[$key] }
    }

    foreach ($keep in @(
        'APP_KEY', 'DB_USERNAME', 'DB_PASSWORD', 'DB_DATABASE',
        'TOURS_ADMIN_PASSWORD', 'MAIL_MAILER', 'MAIL_HOST', 'MAIL_PORT',
        'MAIL_USERNAME', 'MAIL_PASSWORD', 'MAIL_FROM_ADDRESS', 'MAIL_FROM_NAME',
        'MAIL_SCHEME', 'METEOBLUE_API_KEY'
    )) {
        if ($existing.Contains($keep) -and $existing[$keep]) { $merged[$keep] = $existing[$keep] }
        elseif ($local.Contains($keep) -and $local[$keep]) { $merged[$keep] = $local[$keep] }
    }

    Write-DotEnv $DestEnv $merged
}

Write-Host "Quelle: $Root"
Write-Host "Ziel:   $Dest"

$parent = Split-Path $Dest
if (-not (Test-Path -LiteralPath $parent)) {
    throw "NAS-Share nicht erreichbar: $parent"
}

New-Item -ItemType Directory -Force -Path $Dest | Out-Null

$excludeDirs = @(
    '.git', '.github', '.cursor', '.idea', '.vscode',
    'node_modules', 'tests', 'doku',
    'storage\logs',
    'storage\framework\cache',
    'storage\framework\sessions',
    'storage\framework\views',
    'storage\framework\testing',
    'storage\pail'
)

$robocopyArgs = @(
    $Root, $Dest, '/E', '/Z', '/R:2', '/W:2', '/NFL', '/NDL', '/NJH', '/NJS', '/NP',
    '/XD'
) + $excludeDirs + @(
    '/XF', '.env', '.env.backup', '.env.production', 'Thumbs.db', '.DS_Store', 'vendor.zip'
)

& robocopy @robocopyArgs
if ($LASTEXITCODE -ge 8) {
    throw "Robocopy fehlgeschlagen (Exit $LASTEXITCODE)"
}

$ensureDirs = @(
    'storage\app\public',
    'storage\framework\cache\data',
    'storage\framework\sessions',
    'storage\framework\views',
    'storage\logs',
    'bootstrap\cache'
)
foreach ($rel in $ensureDirs) {
    New-Item -ItemType Directory -Force -Path (Join-Path $Dest $rel) | Out-Null
}

Copy-Item -LiteralPath $EnvExample -Destination (Join-Path $Dest '.env.nas.example') -Force

$destEnv = Join-Path $Dest '.env'
$created = -not (Test-Path -LiteralPath $destEnv)
if ($created -and -not (Test-Path -LiteralPath $LocalEnv)) {
    throw 'Lokale .env fehlt; NAS-.env kann nicht angelegt werden.'
}
if ($created) {
    Copy-Item -LiteralPath $EnvExample -Destination $destEnv
}
Merge-NasEnv $destEnv
if ($created) {
    Write-Host 'Neue .env auf der NAS angelegt.'
} else {
    Write-Host 'NAS-.env gemerged (Produktion, vorhandene Secrets behalten).'
}

Write-Host ''
Write-Host 'Fertig. Dateien liegen auf der NAS.'
Write-Host 'Handy: http://192.168.1.10:8081'
