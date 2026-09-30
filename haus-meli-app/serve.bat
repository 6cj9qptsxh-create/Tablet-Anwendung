@echo off
REM Laravel lokal starten (mit korrektem Router — alle Routen funktionieren).
REM Fuer Handy im WLAN: http://DEINE-PC-IP:8000 (nicht localhost)

cd /d "%~dp0"

echo.
echo  Stoppe ggf. alten Server auf Port 8000 und starte neu...
echo  App:  http://127.0.0.1:8000
echo  LAN:  http://^<deine-IPv4^>:8000   (ipconfig)
echo  Beenden: Ctrl+C
echo.

php artisan serve --host=0.0.0.0 --port=8000
if errorlevel 1 (
  echo.
  echo  artisan serve fehlgeschlagen — Fallback mit Router:
  php -S 0.0.0.0:8000 -t public public/router.php
)

pause
