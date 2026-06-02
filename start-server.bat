@echo off
:: Start PHP Development Server
:: This script kills any existing PHP servers on port 8000 before starting a new one

echo ========================================
echo  Android Forensic Tool - Web Server
echo ========================================
echo.

:: Kill any existing PHP processes on port 8000
echo Stopping any existing PHP servers...
for /f "tokens=5" %%a in ('netstat -ano ^| findstr :8000 ^| findstr LISTENING') do (
    taskkill /F /PID %%a >nul 2>&1
)

:: Small delay to ensure port is released
timeout /t 1 /nobreak >nul

:: Start the new server
echo Starting PHP Development Server...
echo.
echo Server URL: http://127.0.0.1:8080
echo Press Ctrl+C to stop the server
echo ========================================
echo.

cd /d "%~dp0"
php\php.exe -S 127.0.0.1:8080 -t web
