<#
.SYNOPSIS
    Safari Travel — start the local WordPress server (Windows, no Docker).

.DESCRIPTION
    Runs PHP's built-in server on http://localhost:8080 with the router that
    emulates WordPress rewrites. Development only.

    Terminal 1:  .\scripts\start-local.ps1
    Terminal 2:  npm run dev

.PARAMETER Port
    Override the port (default: WP_PORT, or 8080).

.EXAMPLE
    .\scripts\start-local.ps1
#>
[CmdletBinding()]
param(
    [int] $Port = 0
)

$ErrorActionPreference = 'Stop'

$root = Split-Path -Parent $PSScriptRoot
Set-Location $root

$php = Get-Command php -ErrorAction SilentlyContinue

if (-not $php) {
    Write-Host "PHP was not found on PATH. See .\scripts\setup-local.ps1 for details." -ForegroundColor Red
    exit 1
}

$arguments = @('scripts/safari.php', 'start')

if ($Port -gt 0) { $arguments += "--port=$Port" }

& $php.Source @arguments
exit $LASTEXITCODE
