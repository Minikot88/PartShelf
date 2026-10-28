param()
$ErrorActionPreference = 'Stop'

$projectRoot = Split-Path -Parent $PSScriptRoot
Set-Location $projectRoot

if (-not (Test-Path '.env')) {
    Copy-Item '.env.example' '.env'
}

$envText = Get-Content -Raw '.env'
$passwordMatch = [regex]::Match($envText, '(?m)^DB_PASSWORD=(.*)$')
if (-not $passwordMatch.Success) {
    throw 'DB_PASSWORD is missing from .env. Restore it from .env.example.'
}
if ([string]::IsNullOrWhiteSpace($passwordMatch.Groups[1].Value)) {
    $bytes = [byte[]]::new(24)
    [System.Security.Cryptography.RandomNumberGenerator]::Fill($bytes)
    $password = [Convert]::ToHexString($bytes).ToLowerInvariant()
    $envText = [regex]::Replace($envText, '(?m)^DB_PASSWORD=.*$', "DB_PASSWORD=$password")
    Set-Content -Path '.env' -Value $envText -Encoding ascii -NoNewline
}

$settings = @{}
foreach ($line in (Get-Content '.env')) {
    if ($line -match '^\s*([^#=\s]+)\s*=\s*(.*?)\s*$') {
        $settings[$matches[1]] = $matches[2]
    }
}
$project = $settings['COMPOSE_PROJECT_NAME']
$ports = @{
    WEB_PORT = [int]$settings['WEB_PORT']
    API_PORT = [int]$settings['API_PORT']
    DB_PORT = [int]$settings['DB_PORT']
}

$containers = @(& docker ps --format '{{.Names}}|{{.Ports}}')
if ($LASTEXITCODE -ne 0) {
    throw 'Docker is unavailable. Start Docker Desktop, then retry.'
}
foreach ($entry in $ports.GetEnumerator()) {
    $port = $entry.Value
    $listener = Get-NetTCPConnection -State Listen -LocalPort $port -ErrorAction SilentlyContinue | Select-Object -First 1
    if ($listener) {
        throw "$($entry.Key)=$port is already listened to by PID $($listener.OwningProcess). Update the corresponding value in .env."
    }

    foreach ($container in $containers) {
        $parts = $container -split '\|', 2
        if ($parts.Count -lt 2 -or $parts[0].StartsWith("$project-")) { continue }
        if ($parts[1] -match "(?:127\.0\.0\.1|0\.0\.0\.0|\[::\]):$port->") {
            throw "$($entry.Key)=$port is already published by Docker container $($parts[0]). Update the corresponding value in .env."
        }
    }
}

& docker compose config --quiet
if ($LASTEXITCODE -ne 0) { throw 'Docker Compose configuration validation failed.' }
& docker compose up -d --build
if ($LASTEXITCODE -ne 0) { throw 'Docker Compose failed to build or start the PartShelf local stack.' }

Write-Host "PartShelf local stack requested. Ports: web=$($ports.WEB_PORT), api=$($ports.API_PORT), postgres=$($ports.DB_PORT)."
Write-Host 'Check readiness with: docker compose ps'
