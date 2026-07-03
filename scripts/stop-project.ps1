$ErrorActionPreference = 'Continue'

$root = Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..')
$backendDir = Join-Path $root 'app'
$runtimeDir = Join-Path $root '.runtime'
$pidFile = Join-Path $runtimeDir 'frontend.pid'

function Write-Step([string] $message) {
    Write-Host "[stop] $message"
}

if (Test-Path -LiteralPath $pidFile) {
    $frontendPid = Get-Content -LiteralPath $pidFile -ErrorAction SilentlyContinue | Select-Object -First 1
    if ($frontendPid) {
        Write-Step "Stopping frontend process $frontendPid..."
        taskkill.exe /PID $frontendPid /T /F *> $null
    }
    Remove-Item -LiteralPath $pidFile -Force -ErrorAction SilentlyContinue
}

$listeners = Get-NetTCPConnection -LocalPort 5173 -State Listen -ErrorAction SilentlyContinue
foreach ($listener in $listeners) {
    Write-Step "Stopping process on port 5173: $($listener.OwningProcess)..."
    taskkill.exe /PID $listener.OwningProcess /T /F *> $null
}

Write-Step 'Stopping backend containers...'
Push-Location -LiteralPath $backendDir
try {
    docker compose down
} finally {
    Pop-Location
}

Write-Host ''
Write-Host 'Project stopped.'
