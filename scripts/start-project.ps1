$ErrorActionPreference = 'Stop'

$root = Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..')
$backendDir = Join-Path $root 'app'
$frontendDir = Join-Path $root 'frontend'
$runtimeDir = Join-Path $root '.runtime'
$pidFile = Join-Path $runtimeDir 'frontend.pid'
$logFile = Join-Path $runtimeDir 'frontend.log'
$frontendUrl = 'http://127.0.0.1:5173'
$backendUrl = 'http://127.0.0.1:8000/api/templates'

function Write-Step([string] $message) {
    Write-Host "[start] $message"
}

function Wait-Docker {
    for ($i = 1; $i -le 60; $i++) {
        docker info *> $null
        if ($LASTEXITCODE -eq 0) {
            return
        }
        Start-Sleep -Seconds 2
    }

    throw 'Docker engine did not become ready. Open Docker Desktop manually and run start-project.cmd again.'
}

function Start-DockerDesktop {
    docker info *> $null
    if ($LASTEXITCODE -eq 0) {
        Write-Step 'Docker is already running.'
        return
    }

    $candidates = @(
        'C:\Program Files\Docker\Docker\Docker Desktop.exe',
        (Join-Path $env:LOCALAPPDATA 'Docker\Docker Desktop.exe')
    )
    $dockerDesktop = $candidates | Where-Object { Test-Path -LiteralPath $_ } | Select-Object -First 1

    if (-not $dockerDesktop) {
        throw 'Docker Desktop was not found. Install Docker Desktop first.'
    }

    Write-Step 'Starting Docker Desktop...'
    Start-Process -FilePath $dockerDesktop -WindowStyle Hidden
    Wait-Docker
    Write-Step 'Docker is ready.'
}

function Wait-Http([string] $url, [int[]] $acceptedStatuses, [string] $name) {
    for ($i = 1; $i -le 60; $i++) {
        try {
            $response = Invoke-WebRequest -Uri $url -UseBasicParsing -TimeoutSec 3
            if ($acceptedStatuses -contains [int] $response.StatusCode) {
                return
            }
        } catch {
            if ($_.Exception.Response -and ($acceptedStatuses -contains [int] $_.Exception.Response.StatusCode)) {
                return
            }
        }
        Start-Sleep -Seconds 2
    }

    throw "$name did not become ready: $url"
}

function Ensure-FrontendDependencies {
    $nodeModules = Join-Path $frontendDir 'node_modules'
    if (Test-Path -LiteralPath $nodeModules) {
        return
    }

    Write-Step 'Installing frontend dependencies...'
    Push-Location -LiteralPath $frontendDir
    try {
        npm.cmd install
    } finally {
        Pop-Location
    }
}

function Start-Frontend {
    $listener = Get-NetTCPConnection -LocalPort 5173 -State Listen -ErrorAction SilentlyContinue | Select-Object -First 1
    if ($listener) {
        Write-Step 'Frontend is already running on http://127.0.0.1:5173.'
        return
    }

    Ensure-FrontendDependencies

    if (-not (Test-Path -LiteralPath $runtimeDir)) {
        New-Item -ItemType Directory -Path $runtimeDir | Out-Null
    }

    Write-Step 'Starting frontend...'
    $command = 'npm.cmd run dev -- --host 127.0.0.1 --port 5173 > "..\.runtime\frontend.log" 2>&1'
    $process = Start-Process -FilePath 'cmd.exe' `
        -ArgumentList @('/c', $command) `
        -WorkingDirectory $frontendDir `
        -WindowStyle Hidden `
        -PassThru
    Set-Content -LiteralPath $pidFile -Value $process.Id -Encoding ASCII
}

Start-DockerDesktop

Write-Step 'Starting backend containers...'
Push-Location -LiteralPath $backendDir
try {
    docker compose up -d
} finally {
    Pop-Location
}

Write-Step 'Waiting for backend API...'
Wait-Http -url $backendUrl -acceptedStatuses @(200, 401) -name 'Backend API'

Start-Frontend

Write-Step 'Waiting for frontend...'
Wait-Http -url $frontendUrl -acceptedStatuses @(200) -name 'Frontend'

Write-Host ''
Write-Host 'Project is running.'
Write-Host "Frontend: $frontendUrl"
Write-Host 'Backend API: http://127.0.0.1:8000/api'
Write-Host ''
Write-Host 'To stop everything, run:'
Write-Host '.\stop-project.cmd'
