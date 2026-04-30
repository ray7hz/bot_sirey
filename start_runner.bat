@echo off
:: start_runner.bat
:: Taruh file ini di folder Startup Windows agar otomatis jalan saat komputer nyala.
::
:: Lokasi folder Startup:
::   Tekan Win+R → ketik: shell:startup → Enter
::   Lalu copy file ini ke folder tersebut.

set PHP_BIN=D:\xampp\php\php.exe
set RUNNER=D:\xampp\htdocs\bot_sirey\telegram\runner.php

start "Sirey Notifikasi" /B "%PHP_BIN%" "%RUNNER%"
