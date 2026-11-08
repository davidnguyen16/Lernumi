[CmdletBinding()]
param(
    [switch]$Smoke,
    [switch]$Test,
    [switch]$SkipBuild,
    [switch]$Client
)

$ErrorActionPreference = 'Stop'
$projectRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$backendComposeArguments = @('compose', '-f', (Join-Path $projectRoot 'server/compose.yaml'))

function Invoke-Docker {
    param([string[]]$DockerArguments)
    & docker @DockerArguments
    if ($LASTEXITCODE -ne 0) {
        throw "Docker command failed (exit $LASTEXITCODE). Fix the reported error and run this script again."
    }
}

Push-Location $projectRoot
try {
    if (-not (Test-Path -LiteralPath (Join-Path $projectRoot 'server/.env') -PathType Leaf)) {
        throw 'Create and configure server/.env locally before starting Lernumi. Environment files and templates are not stored in Git.'
    }
    if ($Client -and -not (Get-Command pnpm -ErrorAction SilentlyContinue)) {
        throw 'Install Node.js and pnpm before using -Client.'
    }
    if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
        throw 'Install Docker Desktop and start its Linux engine first.'
    }
    Invoke-Docker -DockerArguments @('info', '--format', '{{.ServerVersion}}')
    if (-not $SkipBuild) {
        Invoke-Docker -DockerArguments ($backendComposeArguments + @('build', 'api'))
    }

    Invoke-Docker -DockerArguments ($backendComposeArguments + @('up', '-d', '--wait', '--wait-timeout', '120', 'postgres'))
    Invoke-Docker -DockerArguments ($backendComposeArguments + @('run', '--rm', '--no-deps', 'api', 'composer', 'run', 'setup'))
    Invoke-Docker -DockerArguments ($backendComposeArguments + @('run', '--rm', '--no-deps', 'api', 'php', 'artisan', 'config:clear'))
    Invoke-Docker -DockerArguments ($backendComposeArguments + @('run', '--rm', '--no-deps', 'api', 'php', 'artisan', 'route:clear'))
    Invoke-Docker -DockerArguments ($backendComposeArguments + @('run', '--rm', '--no-deps', 'api', 'php', 'artisan', 'migrate', '--seed', '--no-interaction'))
    Invoke-Docker -DockerArguments ($backendComposeArguments + @('exec', '-T', 'postgres', 'psql', '-v', 'ON_ERROR_STOP=1', '-U', 'lernumi', '-d', 'postgres', '-f', '/docker-entrypoint-initdb.d/10-testing.sql'))
    Invoke-Docker -DockerArguments ($backendComposeArguments + @('up', '-d', '--wait', '--wait-timeout', '120'))

    if ($Smoke) {
        Invoke-Docker -DockerArguments ($backendComposeArguments + @('exec', '-T', 'api', 'php', 'scripts/smoke-local.php'))
    }
    if ($Test) {
        Invoke-Docker -DockerArguments ($backendComposeArguments + @('exec', '-T', 'api', 'composer', 'test'))
    }

    $apiBinding = & docker @backendComposeArguments port api 8000
    if ($LASTEXITCODE -ne 0) { throw 'Could not resolve the local API port.' }
    $apiPort = ($apiBinding | Select-Object -First 1) -replace '^.*:', ''
    Write-Host "Backend and PostgreSQL are ready. API: http://localhost:$apiPort"
    Write-Host "Database health: http://localhost:$apiPort/api/v1/health"
    Write-Host 'From the repository root, run docker compose -f server/compose.yaml down to stop; the database volume is retained.'

    if ($Client) {
        Push-Location (Join-Path $projectRoot 'client')
        try {
            & pnpm install --frozen-lockfile
            if ($LASTEXITCODE -ne 0) { throw 'Frontend dependency installation failed.' }
            Write-Host 'Frontend: http://localhost:3001/auth. Ctrl+C stops Next.js; Docker services keep running.'
            & pnpm dev
            if ($LASTEXITCODE -ne 0) { throw "Frontend stopped with exit code $LASTEXITCODE." }
        }
        finally {
            Pop-Location
        }
    }
}
finally {
    Pop-Location
}
