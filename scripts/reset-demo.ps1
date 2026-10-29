[CmdletBinding(SupportsShouldProcess, ConfirmImpact='High')]
param()
$ErrorActionPreference = 'Stop'
Set-Location (Split-Path -Parent $PSScriptRoot)
if ($PSCmdlet.ShouldProcess('partshelf-demo Docker project and its isolated volumes ONLY', 'Delete demo data and recreate sample dataset; original local/DP03 unaffected')) {
    docker compose --env-file .local/demo/runtime.env -f compose.demo.yaml -p partshelf-demo down --volumes
    if ($LASTEXITCODE -ne 0) { throw 'Demo reset stop failed.' }
    & "$PSScriptRoot/start-demo.ps1"
}
