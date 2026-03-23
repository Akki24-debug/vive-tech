$ErrorActionPreference = 'Stop'

$dbRoot = Split-Path -Parent $PSScriptRoot
$pidFile = Join-Path $dbRoot 'local-instance\run\mariadbd.pid'

if (-not (Test-Path $pidFile)) {
    Write-Host 'No hay PID registrado para BusinessBrainDB local.' -ForegroundColor Yellow
    exit 0
}

$pidValue = (Get-Content -Path $pidFile -ErrorAction SilentlyContinue | Select-Object -First 1).Trim()
if (-not $pidValue) {
    Remove-Item -Path $pidFile -Force -ErrorAction SilentlyContinue
    Write-Host 'PID vacío; archivo limpiado.' -ForegroundColor Yellow
    exit 0
}

$process = Get-Process -Id $pidValue -ErrorAction SilentlyContinue
if (-not $process) {
    Remove-Item -Path $pidFile -Force -ErrorAction SilentlyContinue
    Write-Host "El proceso $pidValue ya no está activo." -ForegroundColor Yellow
    exit 0
}

Stop-Process -Id $pidValue -Force
Start-Sleep -Seconds 2
Remove-Item -Path $pidFile -Force -ErrorAction SilentlyContinue

Write-Host "BusinessBrainDB local detenido. PID: $pidValue" -ForegroundColor Green
