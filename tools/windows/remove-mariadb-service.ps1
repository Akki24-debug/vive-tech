$ErrorActionPreference = "Stop"

$serviceName = "MariaDB"
$identity = [Security.Principal.WindowsIdentity]::GetCurrent()
$principal = New-Object Security.Principal.WindowsPrincipal($identity)
$isAdmin = $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
if (-not $isAdmin) {
    throw "Run this script as Administrator."
}

sc.exe stop $serviceName | Out-Null
Start-Sleep -Seconds 2
sc.exe delete $serviceName | Out-Host
