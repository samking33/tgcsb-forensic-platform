@echo off
:: Clear Zombie TCP Connections on Port 8000
:: This script kills all processes using port 8000 and clears stuck connections

echo ========================================
echo  Clearing Zombie Connections
echo ========================================
echo.

echo Killing all PHP processes...
taskkill /F /IM php.exe >nul 2>&1
if %errorlevel% equ 0 (
    echo   [OK] PHP processes killed
) else (
    echo   [INFO] No PHP processes running
)

echo.
echo Waiting for connections to close...
timeout /t 3 /nobreak >nul

echo.
echo Checking port 8000...
netstat -ano | findstr :8000 | findstr LISTENING >nul
if %errorlevel% equ 0 (
    echo   [WARNING] Port 8000 still has listeners
    echo   Recommend using port 8080 instead
) else (
    echo   [OK] Port 8000 is clear
)

echo.
echo ========================================
echo  Cleanup Complete
echo ========================================
echo.
echo You can now start the server on port 8080
echo Run: start-server.bat
echo.
pause
