[CmdletBinding()]
param(
    [switch]$Smoke,
    [switch]$Test,
    [switch]$SkipBuild
)

$ErrorActionPreference = 'Stop'
$projectRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path

function Invoke-Docker {
    param([string[]]$DockerArguments)
    & docker @DockerArguments
    if ($LASTEXITCODE -ne 0) {
        throw "Docker command failed (exit $LASTEXITCODE). Fix the reported error and run this script again."
    }
}

Push-Location $projectRoot
try {
    if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
        throw 'Install Docker Desktop and start its Linux engine first.'
    }
    Invoke-Docker -DockerArguments @('info', '--format', '{{.ServerVersion}}')
    if (-not $SkipBuild) {
        Invoke-Docker -DockerArguments @('compose', 'build', 'api')
    }

    Invoke-Docker -DockerArguments @('compose', 'up', '-d', '--wait', '--wait-timeout', '120', 'postgres')
    Invoke-Docker -DockerArguments @('compose', 'run', '--rm', '--no-deps', 'api', 'composer', 'run', 'setup')
    Invoke-Docker -DockerArguments @('compose', 'run', '--rm', '--no-deps', 'api', 'php', 'artisan', 'config:clear')
    Invoke-Docker -DockerArguments @('compose', 'run', '--rm', '--no-deps', 'api', 'php', 'artisan', 'route:clear')
    Invoke-Docker -DockerArguments @('compose', 'run', '--rm', '--no-deps', 'api', 'php', 'artisan', 'migrate', '--seed', '--no-interaction')
    Invoke-Docker -DockerArguments @('compose', 'exec', '-T', 'postgres', 'psql', '-v', 'ON_ERROR_STOP=1', '-U', 'lernumi', '-d', 'postgres', '-f', '/docker-entrypoint-initdb.d/10-testing.sql')
    Invoke-Docker -DockerArguments @('compose', 'up', '-d', '--wait', '--wait-timeout', '120')

    if ($Smoke) {
        Invoke-Docker -DockerArguments @('compose', 'exec', '-T', 'api', 'php', 'scripts/smoke-local.php')
    }
    if ($Test) {
        Invoke-Docker -DockerArguments @('compose', 'exec', '-T', 'api', 'composer', 'test')
    }

    $apiBinding = & docker compose port api 8000
    if ($LASTEXITCODE -ne 0) { throw 'Could not resolve the local API port.' }
    $apiPort = ($apiBinding | Select-Object -First 1) -replace '^.*:', ''
    Write-Host "Backend and PostgreSQL are ready. API: http://localhost:$apiPort"
    Write-Host "Database health: http://localhost:$apiPort/api/v1/health"
    Write-Host 'Run docker compose down to stop; the database volume is retained.'
}
finally {
    Pop-Location
}
