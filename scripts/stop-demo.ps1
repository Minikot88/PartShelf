param()
$ErrorActionPreference = 'Stop'
Set-Location (Split-Path -Parent $PSScriptRoot)
docker compose --env-file .local/demo/runtime.env -f compose.demo.yaml -p partshelf-demo stop
if ($LASTEXITCODE -ne 0) { throw 'Demo stop failed.' }
