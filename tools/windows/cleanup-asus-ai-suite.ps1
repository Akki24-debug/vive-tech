$ErrorActionPreference = "Stop"

$currentIdentity = [Security.Principal.WindowsIdentity]::GetCurrent()
$principal = New-Object Security.Principal.WindowsPrincipal($currentIdentity)
$isAdmin = $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)

if (-not $isAdmin) {
    throw "This script must be run as Administrator."
}

$logDir = Join-Path $env:USERPROFILE "Desktop"
$logPath = Join-Path $logDir ("asus-cleanup-" + (Get-Date -Format "yyyyMMdd-HHmmss") + ".log")
Start-Transcript -Path $logPath -Force | Out-Null

function Write-Step {
    param([string]$Message)
    Write-Host ""
    Write-Host "==> $Message" -ForegroundColor Cyan
}

function Remove-ServiceSafe {
    param([string]$Name)

    $svc = Get-Service -Name $Name -ErrorAction SilentlyContinue
    $serviceItem = Get-ItemProperty "HKLM:\SYSTEM\CurrentControlSet\Services\$Name" -ErrorAction SilentlyContinue
    $isKernelDriver = $serviceItem -and ($serviceItem.Type -band 0x1)

    if ($svc) {
        if (($svc.Status -ne "Stopped") -and (-not $isKernelDriver)) {
            try {
                Stop-Service -Name $Name -Force -ErrorAction Stop
                Write-Host "Stopped service: $Name"
            } catch {
                Write-Warning ("Could not stop service {0}: {1}" -f $Name, $_.Exception.Message)
            }
        } elseif ($isKernelDriver) {
            Write-Host "Kernel driver detected, skipping live stop: $Name"
        }
    }

    try {
        sc.exe config $Name start= disabled | Out-Null
    } catch {
    }

    $serviceKey = "HKLM:\SYSTEM\CurrentControlSet\Services\$Name"
    if (Test-Path $serviceKey) {
        try {
            sc.exe delete $Name | Out-Null
            Write-Host "Deleted service entry: $Name"
        } catch {
            Write-Warning ("Could not delete service {0}: {1}" -f $Name, $_.Exception.Message)
        }

        if (Test-Path $serviceKey) {
            try {
                Remove-Item -Path $serviceKey -Recurse -Force -ErrorAction Stop
                Write-Host "Removed service registry key directly: $Name"
            } catch {
                Write-Warning ("Could not remove service registry key {0}: {1}" -f $Name, $_.Exception.Message)
            }
        }
    }
}

function Schedule-DeleteOnReboot {
    param([string]$Path)

    if (-not (Test-Path $Path)) {
        return
    }

    $sessionManagerKey = "HKLM:\SYSTEM\CurrentControlSet\Control\Session Manager"
    $existing = (Get-ItemProperty -Path $sessionManagerKey -Name PendingFileRenameOperations -ErrorAction SilentlyContinue).PendingFileRenameOperations
    $newValue = New-Object System.Collections.Generic.List[string]
    if ($existing) {
        foreach ($item in $existing) {
            $newValue.Add([string]$item)
        }
    }
    $newValue.Add([string]$Path)
    $newValue.Add("")
    Set-ItemProperty -Path $sessionManagerKey -Name PendingFileRenameOperations -Value ([string[]]$newValue.ToArray())
    Write-Host "Scheduled delete on reboot: $Path"
}

function Remove-PathSafe {
    param([string]$Path)

    if (-not (Test-Path $Path)) {
        return
    }

    try {
        if ((Get-Item $Path -Force).PSIsContainer) {
            cmd.exe /c "takeown /F `"$Path`" /A /R" | Out-Null
        } else {
            cmd.exe /c "takeown /F `"$Path`" /A" | Out-Null
        }
    } catch {
    }

    try {
        if ((Get-Item $Path -Force).PSIsContainer) {
            cmd.exe /c "icacls `"$Path`" /grant Administrators:F /T /C" | Out-Null
        } else {
            cmd.exe /c "icacls `"$Path`" /grant Administrators:F /C" | Out-Null
        }
    } catch {
    }

    try {
        Remove-Item -Path $Path -Recurse -Force -ErrorAction Stop
        Write-Host "Removed: $Path"
    } catch {
        Write-Warning ("Could not remove {0}: {1}" -f $Path, $_.Exception.Message)
        Schedule-DeleteOnReboot -Path $Path
    }
}

function Remove-RegistryKeySafe {
    param([string]$Path)

    if (-not (Test-Path $Path)) {
        return
    }

    try {
        Remove-Item -Path $Path -Recurse -Force -ErrorAction Stop
        Write-Host "Removed registry key: $Path"
    } catch {
        Write-Warning ("Could not remove registry key {0}: {1}" -f $Path, $_.Exception.Message)
    }
}

function Remove-RegistryValueSafe {
    param(
        [string]$Path,
        [string[]]$Names
    )

    if (-not (Test-Path $Path)) {
        return
    }

    foreach ($name in $Names) {
        try {
            $null = Get-ItemProperty -Path $Path -Name $name -ErrorAction Stop
            Remove-ItemProperty -Path $Path -Name $name -Force -ErrorAction Stop
            Write-Host "Removed registry value: $Path -> $name"
        } catch {
        }
    }
}

function Remove-StartupEntriesByPattern {
    param(
        [string[]]$RegistryPaths,
        [string[]]$Patterns
    )

    foreach ($path in $RegistryPaths) {
        if (-not (Test-Path $path)) {
            continue
        }

        $props = Get-ItemProperty -Path $path
        foreach ($prop in $props.PSObject.Properties) {
            if ($prop.Name -like "PS*") {
                continue
            }

            $candidate = "{0} {1}" -f $prop.Name, [string]$prop.Value
            foreach ($pattern in $Patterns) {
                if ($candidate -match $pattern) {
                    Remove-RegistryValueSafe -Path $path -Names @($prop.Name)
                    break
                }
            }
        }
    }
}

function Remove-ScheduledTaskSafe {
    param([string[]]$Patterns)

    try {
        $tasks = Get-ScheduledTask -ErrorAction Stop
    } catch {
        Write-Warning ("Could not enumerate scheduled tasks: {0}" -f $_.Exception.Message)
        return
    }

    foreach ($task in $tasks) {
        $taskText = "{0}{1}" -f $task.TaskPath, $task.TaskName
        $matches = $false
        foreach ($pattern in $Patterns) {
            if ($taskText -match $pattern) {
                $matches = $true
                break
            }
        }

        if (-not $matches) {
            continue
        }

        try {
            Disable-ScheduledTask -TaskName $task.TaskName -TaskPath $task.TaskPath -ErrorAction SilentlyContinue | Out-Null
        } catch {
        }

        try {
            Unregister-ScheduledTask -TaskName $task.TaskName -TaskPath $task.TaskPath -Confirm:$false -ErrorAction Stop
            Write-Host ("Removed scheduled task: {0}{1}" -f $task.TaskPath, $task.TaskName)
        } catch {
            Write-Warning ("Could not remove scheduled task {0}{1}: {2}" -f $task.TaskPath, $task.TaskName, $_.Exception.Message)
        }
    }
}

Write-Step "Stopping ASUS user processes"
$processNames = @(
    "AISuite3",
    "AsPowerBar",
    "AsRoutineController",
    "AsusFanControlService",
    "atkexComSvc",
    "AsusUpdateCheck"
)
foreach ($proc in $processNames) {
    Get-Process -Name $proc -ErrorAction SilentlyContinue | ForEach-Object {
        try {
            Stop-Process -Id $_.Id -Force -ErrorAction Stop
            Write-Host "Stopped process: $($_.ProcessName) [$($_.Id)]"
        } catch {
            Write-Warning "Could not stop process $($_.ProcessName): $($_.Exception.Message)"
        }
    }
}

Write-Step "Removing ASUS startup entries"
$startupRegistryPaths = @(
    "HKLM:\SOFTWARE\Microsoft\Windows\CurrentVersion\Run",
    "HKLM:\SOFTWARE\WOW6432Node\Microsoft\Windows\CurrentVersion\Run",
    "HKCU:\SOFTWARE\Microsoft\Windows\CurrentVersion\Run",
    "HKLM:\SOFTWARE\Microsoft\Windows\CurrentVersion\RunOnce",
    "HKLM:\SOFTWARE\WOW6432Node\Microsoft\Windows\CurrentVersion\RunOnce",
    "HKCU:\SOFTWARE\Microsoft\Windows\CurrentVersion\RunOnce"
)
$startupPatterns = @(
    "ASUS",
    "AI Suite",
    "AISuite",
    "AXSP",
    "AsusUpdateCheck",
    "AsPowerBar",
    "AsRoutineController",
    "Fan Xpert",
    "DIP",
    "EPU",
    "TPU"
)
Remove-StartupEntriesByPattern -RegistryPaths $startupRegistryPaths -Patterns $startupPatterns

Write-Step "Removing ASUS scheduled tasks"
Remove-ScheduledTaskSafe -Patterns $startupPatterns

Write-Step "Cleaning ASUS services and drivers"
$serviceNames = @(
    "IOMap",
    "AsIO",
    "Asusgio2",
    "asComSvc",
    "AsusFanControlService",
    "AsusUpdateCheck"
)
foreach ($name in $serviceNames) {
    Remove-ServiceSafe -Name $name
}

Write-Step "Removing ASUS files and folders"
$paths = @(
    "C:\Windows\System32\drivers\IOMap64.sys",
    "C:\Windows\System32\drivers\AsIO2.sys",
    "C:\Windows\SysWOW64\drivers\AsIO.sys",
    "C:\Windows\System32\AsusUpdateCheck.exe",
    "C:\Program Files (x86)\ASUS\AXSP",
    "C:\Program Files (x86)\ASUS\AI Suite III",
    "C:\Program Files (x86)\ASUS",
    "C:\ProgramData\ASUS",
    "C:\ProgramData\Microsoft\Windows\Start Menu\Programs\ASUS",
    "C:\Users\ragnarok\AppData\Roaming\Microsoft\Windows\Start Menu\Programs\ASUS",
    "C:\Program Files (x86)\InstallShield Installation Information\{CD36E28B-6023-469A-91E7-049A2874EC13}",
    "C:\Program Files (x86)\InstallShield Installation Information\{C740780B-F589-481C-8F59-A32735DEFCFF}",
    "C:\Program Files (x86)\InstallShield Installation Information\{7B40EADF-CA1B-423A-A110-89DA90679788}",
    "C:\Program Files (x86)\InstallShield Installation Information\{AF8D8D0D-1262-4368-895E-44DA5632CD7B}",
    "C:\Program Files (x86)\InstallShield Installation Information\{C0FEE440-FA2F-4C0D-B64C-35F1D4B7A009}"
)
foreach ($path in $paths) {
    Remove-PathSafe -Path $path
}

Write-Step "Removing ASUS registry residue"
$registryKeys = @(
    "HKLM:\SOFTWARE\WOW6432Node\ASUS\AI-SUITE_II",
    "HKLM:\SOFTWARE\WOW6432Node\ASUS\AXSP",
    "HKLM:\SOFTWARE\WOW6432Node\Microsoft\Windows\CurrentVersion\App Paths\DIP4",
    "HKLM:\SOFTWARE\WOW6432Node\Microsoft\Windows\CurrentVersion\Uninstall\{CD36E28B-6023-469A-91E7-049A2874EC13}",
    "HKLM:\SOFTWARE\Classes\atkexCom.axdata",
    "HKLM:\SOFTWARE\Classes\atkexCom.axdata.1",
    "HKLM:\SOFTWARE\Classes\aaHM.apiHmData2",
    "HKLM:\SOFTWARE\Classes\aaHM.apiHmData2.1",
    "HKLM:\SOFTWARE\WOW6432Node\Classes\aaHM.apiHmData2",
    "HKLM:\SOFTWARE\WOW6432Node\Classes\aaHM.apiHmData2.1",
    "HKLM:\SOFTWARE\Classes\TypeLib\{34AAD71E-0356-470C-94B7-593BE46311BB}",
    "HKLM:\SOFTWARE\Classes\WOW6432Node\TypeLib\{34AAD71E-0356-470C-94B7-593BE46311BB}",
    "HKLM:\SOFTWARE\Classes\WOW6432Node\CLSID\{2627F8BE-4482-4081-BC62-8A12CA24BDF8}",
    "HKLM:\SOFTWARE\Classes\WOW6432Node\CLSID\{BC50CF2A-E12C-4F18-90CE-714CC8600CEE}"
)
foreach ($key in $registryKeys) {
    Remove-RegistryKeySafe -Path $key
}

Write-Step "Checking residue"
$serviceNames | ForEach-Object {
    $exists = Test-Path "HKLM:\SYSTEM\CurrentControlSet\Services\$_"
    Write-Host ("{0,-24} {1}" -f $_, ($(if ($exists) { "PRESENT" } else { "REMOVED" })))
}

Write-Host ""
Write-Host "Cleanup completed. Reboot Windows before testing stability or reinstalling MariaDB." -ForegroundColor Green
Write-Host "Log saved to: $logPath"
Stop-Transcript | Out-Null
