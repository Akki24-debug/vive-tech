param(
    [string]$Database = 'vlv_pms_local',
    [string]$DbHost = '127.0.0.1',
    [int]$Port = 3306,
    [string]$User = 'root',
    [string]$Password = '',
    [switch]$SkipSsl = $true
)

$ErrorActionPreference = 'Stop'

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

    throw 'No se encontro mariadb.exe. Ejecuta primero activate-local-db-tools.ps1 o instala MariaDB.'
}

$client = Resolve-MariaDbClient
$passwordArg = if ($Password -ne '') { "--password=$Password" } else { '--skip-password' }
$sql = "CREATE DATABASE IF NOT EXISTS ``$Database`` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
$sslArg = if ($SkipSsl) { '--skip-ssl' } else { $null }

Write-Host "Creando base local $Database ..." -ForegroundColor Cyan
& $client $sslArg "--host=$DbHost" "--port=$Port" "--user=$User" $passwordArg --execute=$sql
if ($LASTEXITCODE -ne 0) {
    throw "Fallo la creacion de la base local. Codigo de salida: $LASTEXITCODE"
}

Write-Host "Base lista: $Database" -ForegroundColor Green
