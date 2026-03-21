$ErrorActionPreference = 'Stop'

function Test-IsAdministrator {
    $currentIdentity = [Security.Principal.WindowsIdentity]::GetCurrent()
    $principal = New-Object Security.Principal.WindowsPrincipal($currentIdentity)
    return $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
}

if (-not (Test-IsAdministrator)) {
    throw 'This script must be run from an elevated PowerShell session.'
}

$scriptRoot = Split-Path -Parent $MyInvocation.MyCommand.Path
$logDir = Join-Path $scriptRoot 'logs'
$timestamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$logPath = Join-Path $logDir "repair-windows-search-$timestamp.log"

New-Item -ItemType Directory -Path $logDir -Force | Out-Null
Start-Transcript -Path $logPath -Force | Out-Null

try {
    Write-Host "Log file: $logPath" -ForegroundColor Cyan

    Write-Host 'Step 1/5: Re-register Windows Search package' -ForegroundColor Cyan
    $pkg = Get-AppxPackage Microsoft.Windows.Search
    if (-not $pkg) {
        throw 'Microsoft.Windows.Search package was not found for the current user.'
    }

    $manifestPath = Join-Path $pkg.InstallLocation 'AppXManifest.xml'
    Write-Host "Package location: $($pkg.InstallLocation)"
    Add-AppxPackage -DisableDevelopmentMode -Register $manifestPath

    Write-Host 'Step 2/5: Restart Search-related processes' -ForegroundColor Cyan
    Get-Process SearchApp, SearchHost, StartMenuExperienceHost, ShellExperienceHost -ErrorAction SilentlyContinue |
        Stop-Process -Force -ErrorAction SilentlyContinue

    Write-Host 'Step 3/5: Reset search index store' -ForegroundColor Cyan
    $searchDataRoot = 'C:\ProgramData\Microsoft\Search\Data\Applications'
    $searchDataPath = Join-Path $searchDataRoot 'Windows'
    $searchBackupName = "Windows.bak-$timestamp"

    if (Test-Path $searchDataPath) {
        Stop-Service WSearch -Force
        Rename-Item -Path $searchDataPath -NewName $searchBackupName
        New-Item -ItemType Directory -Path $searchDataPath -Force | Out-Null
        Start-Service WSearch
        Write-Host "Search index backup: $(Join-Path $searchDataRoot $searchBackupName)"
    }
    else {
        Write-Warning 'Search index folder was not found. Skipping folder reset.'
        Restart-Service WSearch -Force
    }

    Write-Host 'Step 4/5: Run DISM component repair' -ForegroundColor Cyan
    DISM /Online /Cleanup-Image /RestoreHealth

    Write-Host 'Step 5/5: Run SFC verification and repair' -ForegroundColor Cyan
    sfc /scannow

    Write-Host 'Final status:' -ForegroundColor Cyan
    Get-Service WSearch | Format-Table Name, Status, StartType

    Write-Host 'Recent SearchApp crashes (if any remain):' -ForegroundColor Cyan
    Get-WinEvent -LogName Application -MaxEvents 20 |
        Where-Object { $_.ProviderName -eq 'Application Error' -and $_.Message -match 'SearchApp.exe' } |
        Select-Object -First 5 TimeCreated, Id, Message |
        Format-List

    Write-Host 'Repair finished.' -ForegroundColor Green
}
finally {
    Stop-Transcript | Out-Null
}
