$serviceNames = @(
    "IOMap",
    "AsIO",
    "Asusgio2",
    "asComSvc",
    "AsusFanControlService",
    "AsusUpdateCheck"
)

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
    "$env:APPDATA\Microsoft\Windows\Start Menu\Programs\ASUS"
)

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

Write-Host "ASUS services" -ForegroundColor Cyan
foreach ($name in $serviceNames) {
    $item = Get-ItemProperty "HKLM:\SYSTEM\CurrentControlSet\Services\$name" -ErrorAction SilentlyContinue
    if ($item) {
        Write-Host ("{0,-24} PRESENT  Start={1}  Path={2}" -f $name, $item.Start, $item.ImagePath)
    } else {
        Write-Host ("{0,-24} REMOVED" -f $name)
    }
}

Write-Host ""
Write-Host "ASUS files" -ForegroundColor Cyan
foreach ($path in $paths) {
    Write-Host ("{0} :: {1}" -f $path, ($(if (Test-Path $path) { "PRESENT" } else { "REMOVED" })))
}

Write-Host ""
Write-Host "ASUS registry keys" -ForegroundColor Cyan
foreach ($key in $registryKeys) {
    Write-Host ("{0} :: {1}" -f $key, ($(if (Test-Path $key) { "PRESENT" } else { "REMOVED" })))
}

$startupRegistryPaths = @(
    "HKLM:\SOFTWARE\Microsoft\Windows\CurrentVersion\Run",
    "HKLM:\SOFTWARE\WOW6432Node\Microsoft\Windows\CurrentVersion\Run",
    "HKCU:\SOFTWARE\Microsoft\Windows\CurrentVersion\Run",
    "HKLM:\SOFTWARE\Microsoft\Windows\CurrentVersion\RunOnce",
    "HKLM:\SOFTWARE\WOW6432Node\Microsoft\Windows\CurrentVersion\RunOnce",
    "HKCU:\SOFTWARE\Microsoft\Windows\CurrentVersion\RunOnce"
)
$startupPatterns = "ASUS|AI Suite|AISuite|AXSP|AsusUpdateCheck|AsPowerBar|AsRoutineController|Fan Xpert|DIP|EPU|TPU"

Write-Host ""
Write-Host "ASUS startup values" -ForegroundColor Cyan
foreach ($path in $startupRegistryPaths) {
    if (-not (Test-Path $path)) {
        continue
    }

    $matches = @()
    $props = Get-ItemProperty -Path $path
    foreach ($prop in $props.PSObject.Properties) {
        if ($prop.Name -like "PS*") {
            continue
        }
        $candidate = "{0} {1}" -f $prop.Name, [string]$prop.Value
        if ($candidate -match $startupPatterns) {
            $matches += $candidate
        }
    }

    if ($matches.Count -gt 0) {
        Write-Host "[$path]"
        $matches | ForEach-Object { Write-Host "  PRESENT :: $_" }
    }
}

Write-Host ""
Write-Host "ASUS scheduled tasks" -ForegroundColor Cyan
try {
    $tasks = Get-ScheduledTask -ErrorAction Stop | Where-Object {
        ("{0}{1}" -f $_.TaskPath, $_.TaskName) -match $startupPatterns
    }
    if ($tasks) {
        $tasks | Select-Object TaskPath, TaskName, State | Format-Table -AutoSize
    } else {
        Write-Host "No ASUS scheduled tasks found."
    }
} catch {
    Write-Warning ("Could not enumerate scheduled tasks: {0}" -f $_.Exception.Message)
}
