$ErrorActionPreference = "Stop"

$version = "11.8.3"
$serviceName = "MariaDB"
$repoRoot = $PSScriptRoot
$downloadDir = Join-Path $repoRoot "downloads"
$backupDir = Join-Path $repoRoot "mariadb-backups"
$zipName = "mariadb-11.8.3-winx64.zip"
$zipUrl = "https://archive.mariadb.org/mariadb-11.8.3/winx64-packages/$zipName"
$zipPath = Join-Path $downloadDir $zipName
$expectedSha256 = "debd9643db9b3d35276fb782789564484c681b1bb264d03de0b3a2e8e739f493"
$tempExtractDir = Join-Path $downloadDir "mariadb-11.8.3-extract"
$installRoot = "C:\Program Files\MariaDB 11.8.3"
$legacyRoot = "C:\Program Files\MariaDB 12.2"
$timestamp = Get-Date -Format "yyyyMMdd-HHmmss"

function Assert-Admin {
    $identity = [Security.Principal.WindowsIdentity]::GetCurrent()
    $principal = New-Object Security.Principal.WindowsPrincipal($identity)
    $isAdmin = $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
    if (-not $isAdmin) {
        $argList = @(
            "-NoProfile",
            "-ExecutionPolicy", "Bypass",
            "-File", "`"$PSCommandPath`""
        )
        Start-Process PowerShell -Verb RunAs -ArgumentList $argList | Out-Null
        exit
    }
}

function Copy-PathIfExists {
    param(
        [string]$SourcePath,
        [string]$DestinationRoot
    )

    if (-not (Test-Path $SourcePath)) {
        return
    }

    $leaf = Split-Path $SourcePath -Leaf
    $destination = Join-Path $DestinationRoot $leaf
    Copy-Item -Path $SourcePath -Destination $destination -Recurse -Force
    Write-Host "Backed up: $SourcePath -> $destination"
}

function Wait-ServiceDeleted {
    param([string]$Name)

    for ($i = 0; $i -lt 20; $i++) {
        $existing = Get-Service -Name $Name -ErrorAction SilentlyContinue
        if (-not $existing) {
            return
        }
        Start-Sleep -Seconds 1
    }

    throw "Service $Name still exists after delete attempt."
}

Assert-Admin

New-Item -ItemType Directory -Force -Path $downloadDir | Out-Null
New-Item -ItemType Directory -Force -Path $backupDir | Out-Null

if (-not (Test-Path $zipPath)) {
    Write-Host "Downloading MariaDB $version ZIP from official archive..."
    Invoke-WebRequest -Uri $zipUrl -OutFile $zipPath
}

$actualSha256 = (Get-FileHash -Path $zipPath -Algorithm SHA256).Hash.ToLowerInvariant()
if ($actualSha256 -ne $expectedSha256) {
    throw "SHA256 mismatch for $zipPath. Expected $expectedSha256, got $actualSha256"
}
Write-Host "ZIP checksum OK."

$backupTarget = Join-Path $backupDir "mariadb-12.2-backup-$timestamp"
New-Item -ItemType Directory -Force -Path $backupTarget | Out-Null
Copy-PathIfExists -SourcePath (Join-Path $legacyRoot "data") -DestinationRoot $backupTarget
Copy-PathIfExists -SourcePath (Join-Path $legacyRoot "my.ini") -DestinationRoot $backupTarget

$existingService = Get-Service -Name $serviceName -ErrorAction SilentlyContinue
if ($existingService) {
    if ($existingService.Status -ne "Stopped") {
        Stop-Service -Name $serviceName -Force -ErrorAction Stop
        Start-Sleep -Seconds 3
    }
    sc.exe delete $serviceName | Out-Host
    Wait-ServiceDeleted -Name $serviceName
}

if (Test-Path $installRoot) {
    $installBackup = "$installRoot.backup-$timestamp"
    Move-Item -Path $installRoot -Destination $installBackup -Force
    Write-Host "Moved existing install aside: $installBackup"
}

if (Test-Path $tempExtractDir) {
    Remove-Item -Path $tempExtractDir -Recurse -Force
}
New-Item -ItemType Directory -Force -Path $tempExtractDir | Out-Null
Expand-Archive -Path $zipPath -DestinationPath $tempExtractDir -Force

$candidateDirs = Get-ChildItem -Path $tempExtractDir -Directory
if ($candidateDirs.Count -eq 1 -and (Test-Path (Join-Path $candidateDirs[0].FullName "bin"))) {
    Move-Item -Path $candidateDirs[0].FullName -Destination $installRoot
} else {
    New-Item -ItemType Directory -Force -Path $installRoot | Out-Null
    Get-ChildItem -Path $tempExtractDir -Force | ForEach-Object {
        Move-Item -Path $_.FullName -Destination $installRoot
    }
}

$installDb = Join-Path $installRoot "bin\mariadb-install-db.exe"
$serverExe = Join-Path $installRoot "bin\mariadbd.exe"
if (-not (Test-Path $serverExe)) {
    $serverExe = Join-Path $installRoot "bin\mysqld.exe"
}
$clientExe = Join-Path $installRoot "bin\mariadb.exe"
$dataDir = Join-Path $installRoot "data"
$myIni = Join-Path $dataDir "my.ini"

if (-not (Test-Path $installDb)) {
    throw "mariadb-install-db.exe not found in $installRoot"
}
if (-not (Test-Path $serverExe)) {
    throw "Server executable not found in $installRoot\bin"
}

if (Test-Path $dataDir) {
    Remove-Item -Path $dataDir -Recurse -Force
}

Push-Location $installRoot
try {
    & $installDb
} finally {
    Pop-Location
}

@" 
[mysqld]
datadir=$($dataDir -replace '\\','/')
port=3306

[client]
port=3306
plugin-dir=$($installRoot -replace '\\','/')/lib/plugin
"@ | Set-Content -Path $myIni -Encoding ASCII

$binPath = "`"$serverExe`" --defaults-file=`"$myIni`" $serviceName"
New-Service -Name $serviceName -BinaryPathName $binPath -DisplayName $serviceName -StartupType Automatic | Out-Null
Start-Service -Name $serviceName
Start-Sleep -Seconds 5

Get-Service -Name $serviceName | Format-Table Name, Status, StartType -AutoSize | Out-Host
netstat -ano | Select-String ":3306" | Out-Host
& $clientExe -u root -e "SELECT VERSION() AS version;" | Out-Host

Write-Host ""
Write-Host "Migration completed." -ForegroundColor Green
Write-Host "Backup stored at: $backupTarget"
Write-Host "Active install: $installRoot"
