# Start PHP Development Server
# This script kills any existing PHP servers on port 8000 before starting a new one

Write-Host "========================================" -ForegroundColor Cyan
Write-Host " Android Forensic Tool - Web Server" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Kill any existing PHP processes on port 8080
Write-Host "Stopping any existing PHP servers..." -ForegroundColor Yellow

$connections = Get-NetTCPConnection -LocalPort 8080 -ErrorAction SilentlyContinue
if ($connections) {
    foreach ($conn in $connections) {
        $process = Get-Process -Id $conn.OwningProcess -ErrorAction SilentlyContinue
        if ($process) {
            Write-Host "  Killing process: $($process.ProcessName) (PID: $($conn.OwningProcess))" -ForegroundColor Red
            Stop-Process -Id $conn.OwningProcess -Force -ErrorAction SilentlyContinue
        }
    }
    Start-Sleep -Seconds 1
}

Write-Host ""
Write-Host "Starting PHP Development Server on port 8080..." -ForegroundColor Green
Write-Host "Access the tool at: http://127.0.0.1:8080" -ForegroundColor Cyan
Write-Host "Press Ctrl+C to stop the server" -ForegroundColor Yellow
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Start the server
$scriptPath = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $scriptPath

# Ensure ADB is in the PATH for the PHP process
$adbDir = "$env:USERPROFILE\platform-tools"
if (Test-Path "$adbDir\adb.exe") {
    if ($env:PATH -notlike "*$adbDir*") {
        $env:PATH = "$adbDir;" + $env:PATH
    }
}

& ".\php\php.exe" -S 127.0.0.1:8080 -t web
