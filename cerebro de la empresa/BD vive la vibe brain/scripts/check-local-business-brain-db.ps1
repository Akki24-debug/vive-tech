$ErrorActionPreference = 'Stop'

$dbRoot = Split-Path -Parent $PSScriptRoot
$pidFile = Join-Path $dbRoot 'local-instance\run\mariadbd.pid'
$logFile = Join-Path $dbRoot 'local-instance\logs\mariadbd.err'
$clientExe = 'C:\Program Files\MariaDB 12.2\bin\mariadb.exe'
$dbHost = '127.0.0.1'
$dbPort = 3307

$pidValue = if (Test-Path $pidFile) {
    (Get-Content -Path $pidFile -ErrorAction SilentlyContinue | Select-Object -First 1).Trim()
}

$running = $false
if ($pidValue) {
    $running = [bool](Get-Process -Id $pidValue -ErrorAction SilentlyContinue)
}

Write-Host "Running: $running"
Write-Host "Host: $dbHost"
Write-Host "Port: $dbPort"
if ($pidValue) {
    Write-Host "PID: $pidValue"
}

if ($running) {
    & $clientExe `
        --skip-ssl `
        "--host=$dbHost" `
        "--port=$dbPort" `
        --user=root `
        --skip-password `
        --execute="SHOW DATABASES LIKE 'vive_la_vibe_brain';"
}

if (Test-Path $logFile) {
    Write-Host "Log: $logFile"
}
