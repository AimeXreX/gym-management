@echo off
setlocal

where php >nul 2>nul
if %errorlevel% equ 0 set "PHP_BIN=php"
if not defined PHP_BIN if defined GYM_PHP_PATH if exist "%GYM_PHP_PATH%" set "PHP_BIN=%GYM_PHP_PATH%"
if not defined PHP_BIN for /d %%D in ("%~dp0..\..\.foundation-tools\php-*") do if exist "%%~fD\php.exe" set "PHP_BIN=%%~fD\php.exe"
if not defined PHP_BIN for /d %%D in ("%~dp0..\..\..\.foundation-tools\php-*") do if exist "%%~fD\php.exe" set "PHP_BIN=%%~fD\php.exe"

where composer >nul 2>nul
if %errorlevel% equ 0 set "COMPOSER_BIN=composer"
if not defined COMPOSER_BIN if exist "%~dp0..\..\.foundation-tools\composer.phar" set "COMPOSER_BIN=%~dp0..\..\.foundation-tools\composer.phar"
if not defined COMPOSER_BIN if exist "%~dp0..\..\..\.foundation-tools\composer.phar" set "COMPOSER_BIN=%~dp0..\..\..\.foundation-tools\composer.phar"

if not defined PHP_BIN (
    echo PHP was not found. Add php to PATH or set GYM_PHP_PATH to the full path of php.exe.
    exit /b 1
)

if not defined COMPOSER_BIN (
    echo Composer was not found. Install Composer or place composer.phar in .foundation-tools.
    exit /b 1
)

if /i "%COMPOSER_BIN%"=="composer" (
    composer %*
) else (
    "%PHP_BIN%" "%COMPOSER_BIN%" %*
)
exit /b %errorlevel%
