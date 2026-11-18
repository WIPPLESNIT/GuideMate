@echo off
REM ============================================================================
REM  GuideMate - Always-on ngrok tunnel for Google sign-in
REM ----------------------------------------------------------------------------
REM  Google OAuth needs a PUBLIC, UNCHANGING https callback. A free RANDOM ngrok
REM  URL changes every restart and goes offline (err_ngrok_3200). The fix is a
REM  free STATIC domain that is permanently yours + this auto-restart loop.
REM ============================================================================

REM Automatically use local ngrok.exe or winget ngrok package
if exist "%~dp0ngrok.exe" (
    set "PATH=%~dp0;%PATH%"
) else if exist "%LOCALAPPDATA%\Microsoft\WinGet\Packages\Ngrok.Ngrok_Microsoft.Winget.Source_8wekyb3d8bbwe\ngrok.exe" (
    set "PATH=%LOCALAPPDATA%\Microsoft\WinGet\Packages\Ngrok.Ngrok_Microsoft.Winget.Source_8wekyb3d8bbwe;%PATH%"
)

REM --- EDIT THIS: your reserved static domain (no https://, no trailing slash) ---
set "DOMAIN=wireless-unsoiled-ride.ngrok-free.dev"

REM --- Local port your XAMPP Apache serves on (usually 80) ---
set "PORT=80"

echo(
echo  GuideMate tunnel: https://%DOMAIN% to http://localhost:%PORT%
echo  Keep this window open. It will auto-restart if the tunnel drops.
echo(

:loop
ngrok http --domain=%DOMAIN% %PORT%
echo(
echo  [!] ngrok stopped. Restarting in 3 seconds... (Ctrl+C to quit)
timeout /t 3 /nobreak >nul
goto loop
