param(
    [string]$BindHost = '127.0.0.1',
    [int]$Port = 8080
)

$ErrorActionPreference = 'Stop'

$repoRoot = Split-Path -Parent $PSScriptRoot
$publicRoot = Join-Path $repoRoot 'public_html'
$connectionDir = Join-Path $publicRoot 'pms db connections'
$localConfig = Join-Path $connectionDir 'config.local.php'
$workspaceRoot = Split-Path -Parent (Split-Path -Parent $repoRoot)
$toolsPhp = Join-Path $workspaceRoot 'tools\\php\\php.exe'

function Resolve-PhpBinary {
    if (Test-Path $toolsPhp) {
        return $toolsPhp
    }
    if (Get-Command php -ErrorAction SilentlyContinue) {
        return 'php'
    }
    throw 'No se encontro PHP en PATH ni en tools\\php\\php.exe'
}

if (-not (Test-Path $localConfig)) {
    Write-Host "Falta config.local.php en: $localConfig" -ForegroundColor Yellow
    Write-Host "Copia config.local.example.php a config.local.php y ajusta tus credenciales locales." -ForegroundColor Yellow
    exit 1
}

$phpBin = Resolve-PhpBinary

$env:PMS_APP_ENV = 'local'
$env:PMS_LOCAL_URL = "http://$BindHost`:$Port"

Write-Host "Levantando PMS local..." -ForegroundColor Cyan
Write-Host "Repo: $repoRoot"
Write-Host "Docroot: $publicRoot"
Write-Host "URL: $($env:PMS_LOCAL_URL)" -ForegroundColor Green
Write-Host "Entorno: LOCAL" -ForegroundColor Green

& $phpBin -S "$BindHost`:$Port" -t $publicRoot
