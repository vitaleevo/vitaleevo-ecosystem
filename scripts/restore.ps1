param(
    [Parameter(Mandatory=$true)]
    [string]$BackupPath,
    [switch]$Force
)

$ErrorActionPreference = 'Stop'
if (-not $Force) { throw 'Restauração é destrutiva. Execute novamente com -Force.' }
$backup = (Resolve-Path $BackupPath).Path
$root = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$docker = (Get-Command docker -ErrorAction SilentlyContinue).Source
if (-not $docker) {
    $candidate = Join-Path $env:LOCALAPPDATA 'Programs\DockerDesktop\resources\bin\docker.exe'
    if (Test-Path $candidate) { $docker = $candidate }
}
if (-not $docker) { throw 'Docker CLI não encontrado.' }

$name = Split-Path $backup -Leaf
Push-Location $root
try {
    $container = (& $docker compose ps -q db).Trim()
    if (-not $container) { throw 'Container da base de dados não está em execução.' }

    & $docker cp $backup "${container}:/tmp/$name"
    if ($LASTEXITCODE -ne 0) { throw 'Não foi possível copiar o backup para o container.' }

    & $docker compose exec -T db pg_restore --clean --if-exists --no-owner -U vitaleevo -d vitaleevo "/tmp/$name"
    if ($LASTEXITCODE -ne 0) { throw 'pg_restore falhou.' }

    & $docker compose exec -T db rm -f "/tmp/$name" | Out-Null
    Write-Host 'Restauração concluída.'
} finally {
    Pop-Location
}
