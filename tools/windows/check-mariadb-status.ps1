$ErrorActionPreference = "Stop"

$mariaRoot = "C:\Program Files\MariaDB 12.2"
$client = Join-Path $mariaRoot "bin\mariadb.exe"
$serviceName = "MariaDB"

Write-Host "Service" -ForegroundColor Cyan
Get-Service -Name $serviceName -ErrorAction SilentlyContinue | Format-Table Name, Status, StartType -AutoSize

Write-Host ""
Write-Host "Port 3306" -ForegroundColor Cyan
netstat -ano | Select-String ":3306"

Write-Host ""
Write-Host "Root connection test" -ForegroundColor Cyan
if (Test-Path $client) {
    try {
        & $client -u root -e "SELECT VERSION() AS version;"
    } catch {
        Write-Warning "Root connection test failed: $($_.Exception.Message)"
    }
} else {
    Write-Warning "mariadb.exe not found at $client"
}
