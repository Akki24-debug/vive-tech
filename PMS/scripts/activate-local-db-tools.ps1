$ErrorActionPreference = 'Stop'

$candidateBins = @(
    'C:\Program Files\MariaDB 11.8\bin',
    'C:\Program Files\MariaDB 11.8.3\bin',
    'C:\Program Files\MariaDB 11.8.2\bin',
    'C:\Program Files\MariaDB 11.8.1\bin',
    'C:\Program Files\MariaDB 12.2\bin'
)

$resolvedBin = $null
foreach ($bin in $candidateBins) {
    if (Test-Path $bin) {
        $resolvedBin = $bin
        break
    }
}

if (-not $resolvedBin) {
    $dynamicBin = Get-ChildItem 'C:\Program Files' -Directory -ErrorAction SilentlyContinue |
        Where-Object { $_.Name -like 'MariaDB*' } |
        Sort-Object Name |
        Select-Object -Last 1
    if ($dynamicBin) {
        $candidate = Join-Path $dynamicBin.FullName 'bin'
        if (Test-Path $candidate) {
            $resolvedBin = $candidate
        }
    }
}

if (-not $resolvedBin) {
    Write-Host 'No se encontro binario de MariaDB en Program Files.' -ForegroundColor Red
    exit 1
}

$existing = $env:PATH -split ';'
if ($existing -notcontains $resolvedBin) {
    $env:PATH = ($resolvedBin + ';' + $env:PATH)
}

Write-Host "MariaDB bin agregado a PATH de esta sesion:" -ForegroundColor Green
Write-Host " - $resolvedBin"
Write-Host ''
Write-Host 'Prueba sugerida:'
Write-Host 'mariadb --version'
