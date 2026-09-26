<#
.SYNOPSIS
    Safari Travel — one-command local setup (Windows, no Docker).

.DESCRIPTION
    Thin wrapper around `php scripts/safari.php setup`, which does the actual
    work in PHP so Windows, macOS and Linux behave identically.

    The script is idempotent: run it as often as you like.

.EXAMPLE
    .\scripts\setup-local.ps1

.EXAMPLE
    .\scripts\setup-local.ps1 -NoSeed
#>
[CmdletBinding()]
param(
    [switch] $ForceConfig,
    [switch] $NoSeed,
    [switch] $ForceSeed
)

$ErrorActionPreference = 'Stop'

$root = Split-Path -Parent $PSScriptRoot
Set-Location $root

function Resolve-Php {
    $command = Get-Command php -ErrorAction SilentlyContinue
    if ($command) { return $command.Source }

    foreach ($candidate in @(
            "$env:ProgramFiles\php\php.exe",
            "${env:ProgramFiles(x86)}\php\php.exe",
            "$env:LOCALAPPDATA\Programs\php\php.exe"
        )) {
        if (Test-Path -LiteralPath $candidate) { return $candidate }
    }

    return $null
}

$php = Resolve-Php

if (-not $php) {
    Write-Host ""
    Write-Host "  PHP 8.2 or newer was not found on PATH." -ForegroundColor Red
    Write-Host "  Install it from https://windows.php.net/download/ and make sure php.exe is on PATH," -ForegroundColor Red
    Write-Host "  then open a new PowerShell window and run this script again." -ForegroundColor Red
    Write-Host ""
    exit 1
}

$arguments = @('scripts/safari.php', 'setup')

if ($ForceConfig) { $arguments += '--force-config' }
if ($NoSeed) { $arguments += '--no-seed' }
if ($ForceSeed) { $arguments += '--force-seed' }

& $php @arguments
exit $LASTEXITCODE
