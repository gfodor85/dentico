@echo off
setlocal
powershell.exe -NoLogo -NoProfile -ExecutionPolicy Bypass -File "%~dp0refresh-preview.ps1"
if errorlevel 1 (
  echo.
  echo Preview refresh failed. See the error above.
  pause
  exit /b 1
)
echo.
pause
