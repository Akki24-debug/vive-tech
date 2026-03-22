param(
    [switch]$Reimport
)

$ErrorActionPreference = 'Stop'

function Wait-ForTcpPort {
    param(
        [string]$Address,
        [int]$Port,
        [int]$TimeoutSeconds = 20
    )

    $deadline = (Get-Date).AddSeconds($TimeoutSeconds)
    while ((Get-Date) -lt $deadline) {
        try {
            $client = New-Object System.Net.Sockets.TcpClient
            $async = $client.BeginConnect($Address, $Port, $null, $null)
            if ($async.AsyncWaitHandle.WaitOne(1000)) {
                $client.EndConnect($async)
                $client.Close()
                return $true
            }
            $client.Close()
        } catch {
        }

        Start-Sleep -Milliseconds 500
    }

    return $false
}

$dbRoot = Split-Path -Parent $PSScriptRoot
$instanceRoot = Join-Path $dbRoot 'local-instance'
$dataDir = Join-Path $instanceRoot 'data'
$logDir = Join-Path $instanceRoot 'logs'
$runDir = Join-Path $instanceRoot 'run'
$pidFile = Join-Path $runDir 'mariadbd.pid'
$logFile = Join-Path $logDir 'mariadbd.err'
$schemaPath = Join-Path $dbRoot 'schema\001_business_brain_schema.sql'
$seedPath = Join-Path $dbRoot 'seed\001_seed_explicit_context.sql'

$binDir = 'C:\Program Files\MariaDB 12.2\bin'
$installDb = Join-Path $binDir 'mariadb-install-db.exe'
$serverExe = Join-Path $binDir 'mariadbd.exe'
$clientExe = Join-Path $binDir 'mariadb.exe'
$powerShellExe = 'C:\Windows\System32\WindowsPowerShell\v1.0\powershell.exe'

$dbHost = '127.0.0.1'
$dbPort = 3307
$pipeName = 'BusinessBrainDBLocal'

New-Item -ItemType Directory -Force -Path $instanceRoot, $dataDir, $logDir, $runDir | Out-Null

if (Test-Path $pidFile) {
    $existingPid = (Get-Content -Path $pidFile -ErrorAction SilentlyContinue | Select-Object -First 1).Trim()
    if ($existingPid) {
        $existingProcess = Get-Process -Id $existingPid -ErrorAction SilentlyContinue
        if ($existingProcess) {
            Write-Host "BusinessBrainDB ya está corriendo con PID $existingPid en el puerto $dbPort." -ForegroundColor Yellow
        } else {
            Remove-Item -Path $pidFile -Force -ErrorAction SilentlyContinue
        }
    }
}

if (-not (Test-Path (Join-Path $dataDir 'mysql'))) {
    Write-Host 'Inicializando data directory local para BusinessBrainDB...' -ForegroundColor Cyan
    & $installDb `
        "--datadir=$dataDir" `
        "--port=$dbPort" `
        "--socket=$pipeName" `
        '--allow-remote-root-access'

    if ($LASTEXITCODE -ne 0) {
        throw "Fallo la inicialización de MariaDB local. Código: $LASTEXITCODE"
    }
}

$serverProcess = $null
$currentPid = $null
if (Test-Path $pidFile) {
    $currentPid = (Get-Content -Path $pidFile -ErrorAction SilentlyContinue | Select-Object -First 1).Trim()
}

if (-not $currentPid) {
    Write-Host "Arrancando instancia local BusinessBrainDB en $dbHost`:$dbPort ..." -ForegroundColor Cyan
    $serverCommand = "& '$serverExe' '--datadir=$dataDir' '--port=$dbPort' '--bind-address=$dbHost' '--socket=$pipeName' '--pid-file=$pidFile' '--console'"
    $serverProcess = Start-Process `
        -FilePath $powerShellExe `
        -ArgumentList @(
            '-NoExit',
            '-NoProfile',
            '-ExecutionPolicy',
            'Bypass',
            '-Command',
            $serverCommand
        ) `
        -PassThru `
        -WindowStyle Minimized

    if (-not (Wait-ForTcpPort -Address $dbHost -Port $dbPort -TimeoutSeconds 25)) {
        $dataErrLog = Join-Path $dataDir 'DESKTOP-I1HBTS2.err'
        if (Test-Path $dataErrLog) {
            throw "La instancia local no abrió el puerto $dbPort. Revisa $dataErrLog"
        }

        throw "La instancia local no abrió el puerto $dbPort. Revisa $logFile"
    }
}

$dbExists = & $clientExe `
    --skip-ssl `
    "--host=$dbHost" `
    "--port=$dbPort" `
    --user=root `
    --skip-password `
    --batch `
    --skip-column-names `
    --execute="SHOW DATABASES LIKE 'vive_la_vibe_brain';"

if ($LASTEXITCODE -ne 0) {
    throw "No se pudo consultar la instancia local BusinessBrainDB."
}

if ($Reimport -or -not $dbExists) {
    Write-Host 'Importando schema completo...' -ForegroundColor Cyan
    Get-Content -Path $schemaPath -Raw | & $clientExe `
        --skip-ssl `
        "--host=$dbHost" `
        "--port=$dbPort" `
        --user=root `
        --skip-password

    if ($LASTEXITCODE -ne 0) {
        throw "Falló la importación del schema."
    }

    Write-Host 'Aplicando seed explícito inicial...' -ForegroundColor Cyan
    Get-Content -Path $seedPath -Raw | & $clientExe `
        --skip-ssl `
        "--host=$dbHost" `
        "--port=$dbPort" `
        --user=root `
        --skip-password

    if ($LASTEXITCODE -ne 0) {
        throw "Falló la importación del seed."
    }
}

$verificationSql = @"
SELECT COUNT(*) AS organizations FROM organization;
SELECT COUNT(*) AS business_areas FROM business_area;
SELECT COUNT(*) AS business_lines FROM business_line;
SELECT COUNT(*) AS business_priorities FROM business_priority;
SELECT COUNT(*) AS objectives FROM objective_record;
SELECT COUNT(*) AS external_systems FROM external_system;
SELECT COUNT(*) AS knowledge_documents FROM knowledge_document;
"@

Write-Host 'Verificando conteos iniciales...' -ForegroundColor Cyan
& $clientExe `
    --skip-ssl `
    "--host=$dbHost" `
    "--port=$dbPort" `
    --user=root `
    --skip-password `
    vive_la_vibe_brain `
    --execute=$verificationSql

if ($LASTEXITCODE -ne 0) {
    throw "Falló la verificación de conteos."
}

$finalPid = if (Test-Path $pidFile) {
    (Get-Content -Path $pidFile -ErrorAction SilentlyContinue | Select-Object -First 1).Trim()
} elseif ($serverProcess) {
    $serverProcess.Id
}

Write-Host ''
Write-Host 'BusinessBrainDB local está listo.' -ForegroundColor Green
Write-Host "Host: $dbHost" -ForegroundColor Green
Write-Host "Port: $dbPort" -ForegroundColor Green
Write-Host 'User: root' -ForegroundColor Green
Write-Host 'Password: <vacío>' -ForegroundColor Green
if ($finalPid) {
    Write-Host "PID: $finalPid" -ForegroundColor Green
}
