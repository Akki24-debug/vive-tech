param(
    [Parameter(Mandatory = $true)]
    [string]$DumpPath,
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

if (-not (Test-Path $DumpPath)) {
    throw "No existe el dump: $DumpPath"
}

$client = Resolve-MariaDbClient
$passwordArg = if ($Password -ne '') { "--password=$Password" } else { '--skip-password' }
$resolvedDump = Resolve-Path $DumpPath
$sslArg = if ($SkipSsl) { '--skip-ssl' } else { $null }

Write-Host "Importando dump en $Database ..." -ForegroundColor Cyan
Get-Content -LiteralPath $resolvedDump -Raw | & $client $sslArg "--host=$DbHost" "--port=$Port" "--user=$User" $passwordArg $Database
if ($LASTEXITCODE -ne 0) {
    throw "Fallo la importacion del dump. Codigo de salida: $LASTEXITCODE"
}

Write-Host "Importacion completada: $resolvedDump" -ForegroundColor Green
