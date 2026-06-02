# Add ADB to Windows PATH
# Run this script as Administrator or regular user to add ADB to your PATH

$adbDirectory = "C:\Users\91912\Downloads\platform-tools"

Write-Host "========================================" -ForegroundColor Cyan
Write-Host " Adding ADB to Windows PATH" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Check if ADB exists
if (Test-Path "$adbDirectory\adb.exe") {
    Write-Host "✅ Found ADB at: $adbDirectory" -ForegroundColor Green
}
else {
    Write-Host "❌ ADB not found at: $adbDirectory" -ForegroundColor Red
    Write-Host "Please update the path in this script" -ForegroundColor Yellow
    pause
    exit
}

# Get current User PATH
$currentPath = [Environment]::GetEnvironmentVariable("Path", "User")

# Check if already in PATH
if ($currentPath -like "*$adbDirectory*") {
    Write-Host "ℹ️  ADB directory is already in PATH" -ForegroundColor Yellow
    Write-Host ""
    Write-Host "Current PATH includes: $adbDirectory" -ForegroundColor Gray
}
else {
    Write-Host "Adding ADB to User PATH..." -ForegroundColor Yellow
    
    # Add to PATH
    $newPath = "$currentPath;$adbDirectory"
    [Environment]::SetEnvironmentVariable("Path", $newPath, "User")
    
    Write-Host "✅ Successfully added ADB to PATH!" -ForegroundColor Green
    Write-Host ""
    Write-Host "Added: $adbDirectory" -ForegroundColor Cyan
}

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host " IMPORTANT: Restart Required" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "For the changes to take effect:" -ForegroundColor Yellow
Write-Host "1. Close this PowerShell window" -ForegroundColor White
Write-Host "2. Restart the PHP server (run start-server.ps1)" -ForegroundColor White
Write-Host "3. Refresh the web dashboard" -ForegroundColor White
Write-Host ""
Write-Host "To verify ADB is accessible, open a NEW PowerShell and run:" -ForegroundColor Yellow
Write-Host "  adb version" -ForegroundColor Cyan
Write-Host ""

pause
