param(
    [string]$ApiBase = 'http://localhost:8000',
    [string]$WebBase = 'http://localhost:3001'
)

$ErrorActionPreference = 'Stop'
$targets = @(
    "$ApiBase/api/v1/health",
    "$ApiBase/api/v1/plans",
    "$ApiBase/api/v1/service-categories",
    "$WebBase/",
    "$WebBase/pedido",
    "$WebBase/privacidade",
    "$WebBase/termos"
)

$failed = @()
foreach ($url in $targets) {
    try {
        $response = Invoke-WebRequest -UseBasicParsing -Uri $url -TimeoutSec 15
        if ($response.StatusCode -ne 200) { $failed += "$url => $($response.StatusCode)" }
        else { Write-Host "200 $url" }
    } catch {
        $failed += "$url => $($_.Exception.Message)"
    }
}

if ($failed.Count -gt 0) {
    $failed | ForEach-Object { Write-Error $_ }
    exit 1
}

Write-Host 'Smoke test concluído com sucesso.'
