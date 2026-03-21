$ErrorActionPreference = "Stop"

$mariaRoot = "C:\Program Files\MariaDB 12.2"
$mysqld = Join-Path $mariaRoot "bin\mysqld.exe"
$client = Join-Path $mariaRoot "bin\mariadb.exe"
$defaultsFile = Join-Path $mariaRoot "data\my.ini"
$serviceName = "MariaDB"

if (-not (Test-Path $mysqld)) {
    throw "mysqld.exe not found at $mysqld"
}

if (-not (Test-Path $defaultsFile)) {
    throw "Config file not found at $defaultsFile"
}

$identity = [Security.Principal.WindowsIdentity]::GetCurrent()
$principal = New-Object Security.Principal.WindowsPrincipal($identity)
$isAdmin = $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
if (-not $isAdmin) {
    throw "Run this script as Administrator."
}

$existing = Get-Service -Name $serviceName -ErrorAction SilentlyContinue
if (-not $existing) {
    $binPath = "`"$mysqld`" --defaults-file=`"$defaultsFile`" $serviceName"
    New-Service -Name $serviceName -BinaryPathName $binPath -DisplayName $serviceName -StartupType Automatic | Out-Null
    Write-Host "Created service: $serviceName"
}

Set-Service -Name $serviceName -StartupType Automatic

try {
    Start-Service -Name $serviceName -ErrorAction Stop
    Write-Host "Started service: $serviceName"
} catch {
    Write-Warning "Could not start service cleanly: $($_.Exception.Message)"
}

Start-Sleep -Seconds 3

Get-Service -Name $serviceName -ErrorAction Stop | Format-Table Name, Status, StartType -AutoSize | Out-Host
netstat -ano | Select-String ":3306" | Out-Host

try {
    & $client -u root -e "SELECT VERSION() AS version;"
} catch {
    Write-Warning "Service was created/started, but root login test failed: $($_.Exception.Message)"
    Write-Host "Try running manually:" -ForegroundColor Yellow
    Write-Host "`"$client`" -u root -e `"SELECT VERSION() AS version;`""
}
