@echo off
rem KinderDesk Telegram worker (XAMPP). Oynani yopmang — yopilsa, xabarlar yuborilmaydi.
title KinderDesk Telegram worker
cd /d "%~dp0"
:loop
C:\xampp\php\php.exe artisan tg:run --max-seconds=3600
timeout /t 2 /nobreak >nul
goto loop
