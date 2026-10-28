[CmdletBinding(SupportsShouldProcess = $true, ConfirmImpact = 'High')]
param(
    [ValidateSet('partshelf', 'partshelf_test')]
    [string]$Database = 'partshelf_test',
    [Parameter(Mandatory = $true)]
    [string]$BackupPath
)

$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
Set-Location $projectRoot

$backup = (Resolve-Path -LiteralPath $BackupPath).Path
if (-not (Test-Path -LiteralPath $backup -PathType Leaf)) { throw 'BackupPath must name an existing backup file.' }

$dbUser = 'partshelf'
$maintenanceDb = if ($Database -eq 'partshelf') { 'postgres' } else { 'partshelf' }
if (Test-Path '.env') {
    $envText = Get-Content -Raw '.env'
    $userMatch = [regex]::Match($envText, '(?m)^DB_USERNAME=(.*)$')
    $databaseMatch = [regex]::Match($envText, '(?m)^DB_DATABASE=(.*)$')
    if ($userMatch.Success -and -not [string]::IsNullOrWhiteSpace($userMatch.Groups[1].Value)) { $dbUser = $userMatch.Groups[1].Value.Trim() }
    if ($Database -eq 'partshelf_test' -and $databaseMatch.Success -and -not [string]::IsNullOrWhiteSpace($databaseMatch.Groups[1].Value)) { $maintenanceDb = $databaseMatch.Groups[1].Value.Trim() }
}

$containerPath = "/tmp/partshelf-test-restore-$([guid]::NewGuid().ToString('N')).dump"
& docker compose cp $backup "db:$containerPath"
if ($LASTEXITCODE -ne 0) { throw 'Could not copy the backup into the local database container.' }
try {
    $archiveList = & docker compose exec -T db pg_restore --list $containerPath 2>&1 | Out-String
    if ($LASTEXITCODE -ne 0) { throw 'Backup archive validation failed; target database was not changed.' }
    $archiveDatabase = [regex]::Match($archiveList, '(?m)^;\s*dbname:\s*(\S+)')
    if (-not $archiveDatabase.Success -or $archiveDatabase.Groups[1].Value -ne $Database) {
        throw "Backup archive source database does not match selected target $Database; target database was not changed."
    }

    if (-not $PSCmdlet.ShouldProcess("local PostgreSQL database $Database", 'Replace data from the selected backup')) { return }

    $terminateSql = "SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = '$Database' AND pid <> pg_backend_pid();"
    & docker compose exec -T db psql --username=$dbUser --dbname=$maintenanceDb --command=$terminateSql
    if ($LASTEXITCODE -ne 0) { throw "Could not clear connections to local database $Database." }
    & docker compose exec -T db dropdb --if-exists --username=$dbUser $Database
    if ($LASTEXITCODE -ne 0) { throw "Could not drop local database $Database." }
    & docker compose exec -T db createdb --username=$dbUser $Database
    if ($LASTEXITCODE -ne 0) { throw "Could not recreate local database $Database." }
    & docker compose exec -T db pg_restore --exit-on-error --no-owner --username=$dbUser --dbname=$Database $containerPath
    if ($LASTEXITCODE -ne 0) { throw "Restore failed for local database $Database." }

    Write-Output "Restored the selected backup into local database $Database."
}
finally {
    & docker compose exec -T db rm -f $containerPath 2>$null | Out-Null
}
