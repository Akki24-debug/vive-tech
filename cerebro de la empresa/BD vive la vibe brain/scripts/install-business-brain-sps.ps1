param(
    [string]$DbHost = "127.0.0.1",
    [int]$Port = 3307,
    [string]$User = "root",
    [string]$Database = "vive_la_vibe_brain"
)

$client = "C:\Program Files\MariaDB 12.2\bin\mariadb.exe"
$spRoot = "C:\Users\ragnarok\Documents\repos\Proyecto VLV\cerebro de la empresa\BD vive la vibe brain\stored-procedures"

if (-not (Test-Path $client)) {
    throw "MariaDB client not found at $client"
}

Push-Location $spRoot
try {
    & $client --host=$DbHost --port=$Port --user=$User --skip-password --skip-ssl $Database --execute="source helpers/001_core_helpers.sql; source domains/010_core_entities.sql; source domains/020_execution.sql; source domains/030_meetings_and_followup.sql; source domains/040_knowledge.sql; source domains/050_alerts_ai.sql; source domains/060_integrations_governance.sql; source domains/090_composite_flows.sql;"
    if ($LASTEXITCODE -ne 0) {
        throw "Stored procedure installation failed with exit code $LASTEXITCODE"
    }
}
finally {
    Pop-Location
}

Write-Host "Stored procedures installed in $Database on $DbHost`:$Port"
