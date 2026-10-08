@echo off
REM ============================================================================
REM  GuideMate - Always-on ngrok tunnel for Google sign-in
REM ----------------------------------------------------------------------------
REM  Google OAuth needs a PUBLIC, UNCHANGING https callback. A free RANDOM ngrok
REM  URL changes every restart and goes offline (err_ngrok_3200). The fix is a
REM  free STATIC domain that is permanently yours + this auto-restart loop.
REM
REM  ONE-TIME SETUP:
REM   1) Reserve a free static domain (one per account):
REM        https://dashboard.ngrok.com/domains  ->  "+ New Domain"
REM      You'll get something like:  guidemate-felix.ngrok-free.app
REM   2) Put that exact domain in the DOMAIN line below (no https://).
REM   3) Make sure XAMPP Apache is running on port 80.
REM
REM  Then just run this file. Leave the window open = tunnel stays online.
REM  (See AUTO-START ON BOOT at the bottom to make it fully hands-off.)
REM ============================================================================

REM --- EDIT THIS: your reserved static domain (no https://, no trailing slash) ---
set "DOMAIN=concinnous-unobliging-max.ngrok-free.dev"

REM --- Local port your XAMPP Apache serves on (usually 80) ---
set "PORT=80"

echo(
echo  GuideMate tunnel: https://%DOMAIN%  ->  http://localhost:%PORT%
echo  Keep this window open. It will auto-restart if the tunnel drops.
echo(

:loop
ngrok http --url=https://%DOMAIN% %PORT%
echo(
echo  [!] ngrok stopped. Restarting in 3 seconds... (Ctrl+C to quit)
timeout /t 3 /nobreak >nul
goto loop
