param()
$ErrorActionPreference = 'Stop'
$demoRoot = Split-Path -Parent $PSScriptRoot
Set-Location $demoRoot
$demoPrivate = Join-Path $demoRoot '.local/demo'
$null = New-Item -ItemType Directory -Force -Path $demoPrivate
$demoIdentity = [System.Security.Principal.WindowsIdentity]::GetCurrent().Name
& icacls $demoPrivate /inheritance:r /grant:r "${demoIdentity}:(OI)(CI)F" | Out-Null
if ($LASTEXITCODE -ne 0) { throw 'Could not protect private demo directory.' }
function New-DemoRandom { param([int]$Bytes = 24) $data = [byte[]]::new($Bytes); [System.Security.Cryptography.RandomNumberGenerator]::Fill($data); [Convert]::ToBase64String($data) }
$demoEnv = Join-Path $demoPrivate 'runtime.env'
if (!(Test-Path -LiteralPath $demoEnv)) {
    $demoDbPassword = New-DemoRandom
    $demoAppKey = 'base64:' + (New-DemoRandom 32)
    @("DEMO_DB_PASSWORD=$demoDbPassword", "DEMO_APP_KEY=$demoAppKey") | Set-Content -LiteralPath $demoEnv -Encoding ascii
}
$demoAccounts = Join-Path $demoPrivate 'accounts.json'
if (!(Test-Path -LiteralPath $demoAccounts)) {
    @('owner','manager','staff') | ForEach-Object { @{username="$_.demo";role=$_;password=('PsD9!' + (New-DemoRandom))} } | ConvertTo-Json | Set-Content -LiteralPath $demoAccounts -Encoding utf8
}
$demoCompose = @('--env-file',$demoEnv,'-f','compose.demo.yaml','-p','partshelf-demo')
$demoPublished = @(& docker ps --format '{{.Names}}|{{.Ports}}')
if ($LASTEXITCODE -ne 0) { throw 'Docker unavailable.' }
$demoListeners = @(& netstat -ano -p TCP)
foreach ($demoPort in @(5177,8084,5443)) {
    $demoOwn = $demoPublished | Where-Object { $_ -match '^partshelf-demo-' -and $_ -match ":$demoPort->" }
    $demoOther = $demoPublished | Where-Object { $_ -notmatch '^partshelf-demo-' -and $_ -match ":$demoPort->" }
    if ($demoOther -or (!$demoOwn -and ($demoListeners | Where-Object { $_ -match ":$demoPort\s+\S+\s+LISTENING" }))) { throw "Port $demoPort is occupied; no service stopped." }
}
& docker compose @demoCompose config --quiet
if ($LASTEXITCODE -ne 0) { throw 'Demo Compose invalid.' }
& docker compose @demoCompose up -d --wait
if ($LASTEXITCODE -ne 0) { throw 'Demo startup failed; original stacks unchanged.' }
& docker compose @demoCompose exec -T demo-api composer install --no-interaction --prefer-dist --no-progress
if ($LASTEXITCODE -ne 0) { throw 'Demo PHP dependencies failed.' }
& docker compose @demoCompose exec -T demo-web npm ci --no-audit --no-fund
if ($LASTEXITCODE -ne 0) { throw 'Demo frontend dependencies failed.' }
& docker compose @demoCompose restart demo-web
if ($LASTEXITCODE -ne 0) { throw 'Demo frontend restart failed.' }
& docker compose @demoCompose exec -T demo-api php artisan migrate --force
if ($LASTEXITCODE -ne 0) { throw 'Demo migration failed.' }
& docker compose @demoCompose exec -T demo-api php artisan partshelf:demo:provision --credentials=/run/demo/accounts.json
if ($LASTEXITCODE -ne 0) { throw 'Demo provisioning refused or failed.' }
& docker compose @demoCompose exec -T demo-api php -r "if (!is_link('public/storage') && !file_exists('public/storage')) { symlink('../storage/app/public', 'public/storage'); }"
if ($LASTEXITCODE -ne 0) { throw 'Demo storage link failed.' }
Write-Host 'PartShelf Demo: http://127.0.0.1:5177 (loopback only). Private accounts: .local/demo/accounts.json'
