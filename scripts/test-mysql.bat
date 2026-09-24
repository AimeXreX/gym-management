@echo off
setlocal EnableExtensions
cd /d "%~dp0.."
if not exist ".env.testing" (
  echo [ERROR] .env.testing not found.
  echo Create .env.testing with the dedicated MySQL test credentials.
  exit /b 2
)
for /f "usebackq tokens=1,* delims==" %%A in (`findstr /b "DB_DATABASE=" ".env.testing"`) do set "TEST_DB=%%B"
if not defined TEST_DB (
  echo [ERROR] DB_DATABASE is missing.
  exit /b 3
)
echo %TEST_DB%| findstr /r /c:"_test$" >nul
if errorlevel 1 (
  echo [REFUSED] DB_DATABASE must end in _test. Current value: %TEST_DB%
  echo migrate:fresh was NOT executed.
  exit /b 4
)
echo %TEST_DB%| findstr /i /x /c:"mysql" /c:"production" /c:"gym_saas" >nul
if not errorlevel 1 (
  echo [REFUSED] Suspicious database name.
  exit /b 5
)
call scripts\artisan.bat optimize:clear --env=testing || exit /b 10
call scripts\artisan.bat migrate:fresh --seed --env=testing || exit /b 11
rem Run PHPUnit directly: the collision `artisan test` command always injects its
rem own discovered config, so a custom --configuration cannot be passed through it.
call php vendor\bin\phpunit --configuration=phpunit.mysql.xml || exit /b 12
echo [PASS] MySQL migration, seed and full test suite passed on %TEST_DB%.
exit /b 0
