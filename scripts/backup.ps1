param(
    [string]$OutputDirectory = (Join-Path $PSScriptRoot '..\backups')
)

$ErrorActionPreference = 'Stop'
$root = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$docker = (Get-Command docker -ErrorAction SilentlyContinue).Source
if (-not $docker) {
    $candidate = Join-Path $env:LOCALAPPDATA 'Programs\DockerDesktop\resources\bin\docker.exe'
    if (Test-Path $candidate) { $docker = $candidate }
}
if (-not $docker) { throw 'Docker CLI não encontrado.' }

New-Item -ItemType Directory -Force -Path $OutputDirectory | Out-Null
$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$name = "vitaleevo-$stamp.dump"
$output = Join-Path (Resolve-Path $OutputDirectory).Path $name

Push-Location $root
try {
    $container = (& $docker compose ps -q db).Trim()
    if (-not $container) { throw 'Container da base de dados não está em execução.' }

    & $docker compose exec -T db pg_dump -U vitaleevo -d vitaleevo -Fc -f "/tmp/$name"
    if ($LASTEXITCODE -ne 0) { throw 'pg_dump falhou.' }

    & $docker cp "${container}:/tmp/$name" $output
    if ($LASTEXITCODE -ne 0) { throw 'Não foi possível copiar o backup para o host.' }

    & $docker compose exec -T db rm -f "/tmp/$name" | Out-Null
    Write-Host "Backup criado: $output"
} finally {
    Pop-Location
}
