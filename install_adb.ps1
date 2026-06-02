# ADB Installation Script (User-level - No Admin Required)
# This script downloads ADB and sets it up for the current user

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Android ADB Installation (User Mode)" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Define paths
$downloadUrl = "https://dl.google.com/android/repository/platform-tools-latest-windows.zip"
$downloadPath = "$env:TEMP\platform-tools.zip"
$installPath = "$env:USERPROFILE\platform-tools"

Write-Host "Step 1: Downloading Android Platform Tools..." -ForegroundColor Yellow
try {
    Invoke-WebRequest -Uri $downloadUrl -OutFile $downloadPath -UseBasicParsing
    Write-Host "✅ Download complete! ($(
(Get-Item $downloadPath).Length / 1MB | ForEach-Object {$_.ToString('0.0')}) MB)" -ForegroundColor Green
}
catch {
    Write-Host "❌ Download failed: $_" -ForegroundColor Red
    Read-Host "Press Enter to exit"
    exit 1
}

Write-Host ""
Write-Host "Step 2: Extracting files to $installPath..." -ForegroundColor Yellow
try {
    if (Test-Path $installPath) {
        Write-Host "   Removing existing installation..." -ForegroundColor Gray
        Remove-Item -Path $installPath -Recurse -Force
    }
    
    Expand-Archive -Path $downloadPath -DestinationPath $env:USERPROFILE -Force
    Write-Host "✅ Extraction complete!" -ForegroundColor Green
}
catch {
    Write-Host "❌ Extraction failed: $_" -ForegroundColor Red
    Read-Host "Press Enter to exit"
    exit 1
}

Write-Host ""
Write-Host "Step 3: Adding to USER PATH (no admin required)..." -ForegroundColor Yellow
try {
    # Get current user PATH
    $currentPath = [Environment]::GetEnvironmentVariable("Path", "User")
    
    if ($currentPath -notlike "*$installPath*") {
        $newPath = $currentPath + ";" + $installPath
        [Environment]::SetEnvironmentVariable("Path", $newPath, "User")
        $env:Path = $env:Path + ";" + $installPath
        Write-Host "✅ Added to PATH!" -ForegroundColor Green
    }
    else {
        Write-Host "✅ Already in PATH!" -ForegroundColor Green
    }
}
catch {
    Write-Host "❌ Failed to update PATH: $_" -ForegroundColor Red
    Read-Host "Press Enter to exit"
    exit 1
}

Write-Host ""
Write-Host "Step 4: Cleaning up..." -ForegroundColor Yellow
Remove-Item -Path $downloadPath -Force -ErrorAction SilentlyContinue
Write-Host "✅ Cleanup complete!" -ForegroundColor Green

Write-Host ""
Write-Host "========================================" -ForegroundColor Green
Write-Host "✅ Installation Complete!" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Green
Write-Host ""

# Test ADB
Write-Host "Testing ADB installation..." -ForegroundColor Yellow
try {
    $adbPath = Join-Path $installPath "adb.exe"
    $version = & $adbPath version 2>&1 | Select-Object -First 1
    Write-Host "✅ ADB installed: $version" -ForegroundColor Green
}
catch {
    Write-Host "⚠️  ADB installed but not in PATH yet. Close and reopen PowerShell." -ForegroundColor Yellow
}

Write-Host ""
Write-Host "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" -ForegroundColor Cyan
Write-Host "NEXT STEPS:" -ForegroundColor Yellow
Write-Host "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" -ForegroundColor Cyan
Write-Host ""
Write-Host "1. CLOSE THIS POWERSHELL and open a NEW ONE" -ForegroundColor White
Write-Host "   (This loads the updated PATH)" -ForegroundColor Gray
Write-Host ""
Write-Host "2. Test ADB:" -ForegroundColor White
Write-Host "   adb version" -ForegroundColor Cyan
Write-Host ""
Write-Host "3. Connect your Android phone via USB" -ForegroundColor White
Write-Host ""
Write-Host "4. Enable USB Debugging:" -ForegroundColor White
Write-Host "   • Settings → About Phone" -ForegroundColor Gray
Write-Host "   • Tap 'Build Number' 7 times" -ForegroundColor Gray
Write-Host "   • Settings → Developer Options → Enable 'USB Debugging'" -ForegroundColor Gray
Write-Host ""
Write-Host "5. Check device connection:" -ForegroundColor White
Write-Host "   adb devices" -ForegroundColor Cyan
Write-Host "   (Accept the prompt on your phone)" -ForegroundColor Gray
Write-Host ""
Write-Host "6. Run the Forensic Tool:" -ForegroundColor White
Write-Host "   cd 'c:\android log extract\Log-analysis-main'" -ForegroundColor Cyan
Write-Host "   python main.py" -ForegroundColor Cyan
Write-Host ""
Write-Host "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" -ForegroundColor Cyan
Write-Host ""
Write-Host "Installation path: $installPath" -ForegroundColor Gray
Write-Host ""

Read-Host "Press Enter to exit"
