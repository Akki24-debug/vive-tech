$repoTools = Split-Path -Parent $MyInvocation.MyCommand.Path
$pathsToAdd = @(
    (Join-Path $repoTools 'php'),
    (Join-Path $repoTools 'composer'),
    (Join-Path $repoTools 'ngrok')
)

$existing = $env:PATH -split ';'
$newPaths = @()

foreach ($path in $pathsToAdd) {
    if ((Test-Path $path) -and ($existing -notcontains $path)) {
        $newPaths += $path
    }
}

if ($newPaths.Count -gt 0) {
    $env:PATH = ($newPaths + $existing) -join ';'
    Write-Host 'PATH actualizado para esta sesion con tools locales:'
    $newPaths | ForEach-Object { Write-Host " - $_" }
} else {
    Write-Host 'No hubo cambios; tools locales ya estaban en PATH o no existen.'
}

