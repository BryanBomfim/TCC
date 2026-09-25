@echo off
setlocal
cd /d "%~dp0"
set "SC_PHP=C:\xampp\php\php.exe"
if exist "%SC_PHP%" goto executar
where php >nul 2>nul
if errorlevel 1 goto semphp
set "SC_PHP=php"
:executar
echo [1/3] Sintaxe PHP
"%SC_PHP%" tests\lint.php
if errorlevel 1 goto falhou
echo [2/3] Testes sem banco
"%SC_PHP%" tests\unit.php
if errorlevel 1 goto falhou
echo [3/3] Banco e testes transacionais - confira o .env antes de executar
"%SC_PHP%" tests\run.php
if errorlevel 1 goto falhou
echo Verificacoes locais aprovadas. Siga tambem docs\TESTES.md.
pause
exit /b 0
:semphp
echo PHP nao encontrado. Instale o XAMPP ou ajuste SC_PHP neste arquivo.
pause
exit /b 1
:falhou
echo Uma verificacao falhou. Consulte a mensagem acima e storage\logs\app.log.
pause
exit /b 1
