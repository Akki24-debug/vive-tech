$ErrorActionPreference = 'Stop'

$repoRoot = Split-Path -Parent $PSScriptRoot
$publicRoot = Join-Path $repoRoot 'public_html'
$connectionDir = Join-Path $publicRoot 'pms db connections'
$localConfig = Join-Path $connectionDir 'config.local.php'
$toolsPhp = Join-Path (Split-Path -Parent $repoRoot) 'vive-tech-tools\\php\\php.exe'
$testScript = Join-Path $connectionDir 'test-connection.php'

function Resolve-PhpBinary {
    if (Test-Path $toolsPhp) {
        return $toolsPhp
    }
    if (Get-Command php -ErrorAction SilentlyContinue) {
        return 'php'
    }
    return $null
}

$phpBin = Resolve-PhpBinary

function Resolve-MariaDbClient {
    if (Get-Command mariadb -ErrorAction SilentlyContinue) {
        return 'mariadb'
    }

    $candidates = @(
        'C:\Program Files\MariaDB 11.8\bin\mariadb.exe',
        'C:\Program Files\MariaDB 11.8.3\bin\mariadb.exe',
        'C:\Program Files\MariaDB 12.2\bin\mariadb.exe'
    )
    foreach ($candidate in $candidates) {
        if (Test-Path $candidate) {
            return $candidate
        }
    }

    return $null
}

$mariaClient = Resolve-MariaDbClient

Write-Host '=== PMS Local Prerequisites ==='
Write-Host "Repo root: $repoRoot"
Write-Host "Public root: $publicRoot"

if ($phpBin) {
    Write-Host "PHP: OK ($phpBin)" -ForegroundColor Green
    & $phpBin -v | Select-Object -First 1
} else {
    Write-Host 'PHP: FALTA' -ForegroundColor Red
}

if (Test-Path $localConfig) {
    Write-Host "config.local.php: OK" -ForegroundColor Green
} else {
    Write-Host "config.local.php: FALTA" -ForegroundColor Red
    Write-Host "Copia config.local.example.php a config.local.php"
}

if ($mariaClient) {
    Write-Host "MariaDB/MySQL CLI: OK ($mariaClient)" -ForegroundColor Green
} else {
    Write-Host 'MariaDB/MySQL CLI: no disponible' -ForegroundColor Yellow
}

if ($phpBin -and (Test-Path $localConfig)) {
    Write-Host ''
    Write-Host 'Intentando prueba de conexion por PHP...' -ForegroundColor Cyan
    $env:PMS_APP_ENV = 'local'
    try {
        & $phpBin $testScript
    } catch {
        Write-Host "Fallo la prueba de conexion: $($_.Exception.Message)" -ForegroundColor Red
    }
}
