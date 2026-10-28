[CmdletBinding()]
param()
$ErrorActionPreference = 'Stop'
$projectRoot = (Split-Path -Parent $PSScriptRoot).Replace('\', '/')
Set-Location $projectRoot
$files = @(& git -c "safe.directory=$projectRoot" diff --cached --name-only --diff-filter=ACMR)
if ($LASTEXITCODE -ne 0) { throw 'Could not inspect Git index.' }
$privateValues = @()
foreach ($envPath in @('.env', 'backend/.env')) {
    if (Test-Path -LiteralPath $envPath) {
        foreach ($line in Get-Content -LiteralPath $envPath) {
            if ($line -match '^\s*[A-Z_]*(?:PASSWORD|SECRET|TOKEN|APP_KEY|ACCESS_KEY)[A-Z_]*=(.+)$') {
                $value = $Matches[1].Trim().Trim('"').Trim("'")
                if ($value.Length -ge 12) { $privateValues += $value }
            }
        }
    }
}
foreach ($file in $files) {
    if ($file -match '(^|/)(\.codex|\.agents|\.kimmizo|\.local|node_modules|vendor|test-results|playwright-report)(/|$)' -or
        ($file -match '(^|/)\.env' -and $file -notmatch '\.env\.(example|production\.example)$') -or $file -match '\.(dump|pem|key)$') {
        throw "Private/generated path staged: $file"
    }
    if ($file -match '\.(png|jpe?g|gif|webp|ico)$') { continue }
    $content = (& git -c "safe.directory=$projectRoot" show ":$file") -join "`n"
    if ($LASTEXITCODE -ne 0) { throw "Could not inspect staged file: $file" }
    if ($content -match '(?:ghp_[A-Za-z0-9]{36}|github_pat_[A-Za-z0-9_]{70,}|AKIA[A-Z0-9]{16}|-----BEGIN (?:RSA |OPENSSH |EC )?PRIVATE KEY-----)') {
        throw "Credential pattern detected in staged file: $file"
    }
    foreach ($value in $privateValues) {
        if ($content.Contains($value)) { throw "Local secret value detected in staged file: $file" }
    }
}
Write-Output "PASS: $($files.Count) staged paths checked; private paths, credential patterns and local environment secret values excluded."
