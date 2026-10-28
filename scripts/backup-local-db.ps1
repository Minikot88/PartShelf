[CmdletBinding()]
param(
    [ValidateSet('partshelf', 'partshelf_test')]
    [string]$Database = 'partshelf',
    [Parameter(Mandatory = $true)]
    [string]$OutputPath
)

$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
Set-Location $projectRoot

$output = [System.IO.Path]::GetFullPath($OutputPath)
if (Test-Path -LiteralPath $output) { throw "Refusing to overwrite existing backup: $output" }
$directory = Split-Path -Parent $output
if (-not (Test-Path -LiteralPath $directory)) { New-Item -ItemType Directory -Path $directory | Out-Null }

$dbUser = 'partshelf'
if (Test-Path '.env') {
    $match = [regex]::Match((Get-Content -Raw '.env'), '(?m)^DB_USERNAME=(.*)$')
    if ($match.Success -and -not [string]::IsNullOrWhiteSpace($match.Groups[1].Value)) { $dbUser = $match.Groups[1].Value.Trim() }
}
$containerPath = "/tmp/partshelf-test-$([guid]::NewGuid().ToString('N')).dump"

try {
    & docker compose exec -T db pg_dump --format=custom --no-owner --username=$dbUser --dbname=$Database --file=$containerPath
    if ($LASTEXITCODE -ne 0) { throw "pg_dump failed for local database $Database." }
    & docker compose cp "db:$containerPath" $output
    if ($LASTEXITCODE -ne 0) { throw 'Could not copy the database backup from the local container.' }
    if (-not (Test-Path -LiteralPath $output) -or (Get-Item -LiteralPath $output).Length -eq 0) { throw 'Backup file is missing or empty.' }
    Write-Output "Backup created for local database ${Database}: $output"
}
finally {
    & docker compose exec -T db rm -f $containerPath 2>$null | Out-Null
}
