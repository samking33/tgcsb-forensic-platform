# Clear Zombie TCP Connections
# Kills all PHP processes and waits for connections to clear

Write-Host "========================================" -ForegroundColor Cyan
Write-Host " Clearing Zombie Connections" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Kill all PHP processes
Write-Host "Killing all PHP processes..." -ForegroundColor Yellow
$phpProcesses = Get-Process php -ErrorAction SilentlyContinue
if ($phpProcesses) {
    $phpProcesses | Stop-Process -Force
    Write-Host "  [OK] Killed $($phpProcesses.Count) PHP process(es)" -ForegroundColor Green
}
else {
    Write-Host "  [INFO] No PHP processes running" -ForegroundColor Gray
}

Write-Host ""
Write-Host "Waiting for connections to close..." -ForegroundColor Yellow
Start-Sleep -Seconds 3

# Check port 8000
Write-Host ""
Write-Host "Checking port 8000..." -ForegroundColor Yellow
$port8000 = Get-NetTCPConnection -LocalPort 8000 -State Listen -ErrorAction SilentlyContinue
if ($port8000) {
    Write-Host "  [WARNING] Port 8000 still has listeners" -ForegroundColor Red
    Write-Host "  Recommend using port 8080 instead" -ForegroundColor Yellow
}
else {
    Write-Host "  [OK] Port 8000 is clear" -ForegroundColor Green
}

# Check for TIME_WAIT connections
$timeWaitCount = (Get-NetTCPConnection -LocalPort 8000 -State TimeWait -ErrorAction SilentlyContinue | Measure-Object).Count
if ($timeWaitCount -gt 0) {
    Write-Host "  [INFO] $timeWaitCount TIME_WAIT connections (will clear in 1-2 minutes)" -ForegroundColor Gray
}

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host " Cleanup Complete" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "You can now start the server on port 8080" -ForegroundColor Green
Write-Host "Run: " -NoNewline
Write-Host ".\start-server.ps1" -ForegroundColor Cyan
Write-Host ""
