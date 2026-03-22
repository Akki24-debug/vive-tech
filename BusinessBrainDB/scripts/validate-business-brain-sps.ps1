param(
    [string]$DbHost = "127.0.0.1",
    [int]$Port = 3307,
    [string]$User = "root",
    [string]$Database = "vive_la_vibe_brain"
)

$client = "C:\Program Files\MariaDB 12.2\bin\mariadb.exe"

if (-not (Test-Path $client)) {
    throw "MariaDB client not found at $client"
}

$queries = @(
    "SELECT COUNT(*) AS procedure_count FROM information_schema.routines WHERE routine_schema = '$Database' AND routine_type = 'PROCEDURE';",
    "CALL sp_bootstrap_state_data(1);",
    "CALL sp_business_area_data(NULL, 1, NULL, 1, 20);",
    "CALL sp_knowledge_document_data(NULL, 1, NULL, NULL, 20);"
)

foreach ($query in $queries) {
    & $client --host=$DbHost --port=$Port --user=$User --skip-password --skip-ssl --batch --raw --skip-column-names $Database --execute=$query
    if ($LASTEXITCODE -ne 0) {
        throw "Validation query failed: $query"
    }
}

Write-Host "Stored procedure validation passed for $Database on $DbHost`:$Port"
