@echo off
setlocal EnableExtensions
:: ============================================================
::  OCOBO - Servicio OCR (FastAPI + PaddleOCR, CPU)
::
::  Uso:  start.bat            instala lo que falte y arranca el servicio
::        start.bat install    solo crea el entorno e instala dependencias
::
::  - Crea el entorno virtual .venv la primera vez.
::  - Reinstala dependencias solo si cambio requirements.txt.
::  - Toma OCR_SERVICE_TOKEN y OCR_SERVICE_HOST del .env de Laravel
::    (sin token el servicio rechaza todas las extracciones).
::  - Python: variable OCR_PYTHON, o el lanzador "py -3.12", o "python" del PATH.
:: ============================================================

cd /d "%~dp0"

set "VENV_DIR=%~dp0.venv"
set "VENV_PYTHON=%VENV_DIR%\Scripts\python.exe"
set "REQ_STAMP=%VENV_DIR%\requirements.installed"
set "LARAVEL_ENV=%~dp0..\..\..\.env"
set "OCR_PORT=5000"

echo ============================================
echo  OCOBO - Servicio OCR (PaddleOCR)
echo ============================================
echo.

:: ---------- 1. Entorno virtual ----------
if exist "%VENV_PYTHON%" goto :deps

call :buscar_python
if errorlevel 1 goto :error

echo Creando entorno virtual en .venv ...
%PYTHON_CMD% -m venv "%VENV_DIR%"
if errorlevel 1 (
    echo ERROR: no fue posible crear el entorno virtual.
    goto :error
)

:: ---------- 2. Dependencias ----------
:deps
if exist "%REQ_STAMP%" (
    fc /b "requirements.txt" "%REQ_STAMP%" >nul 2>&1
    if not errorlevel 1 (
        echo Dependencias al dia.
        goto :instalado
    )
)

echo Instalando dependencias ^(la primera vez tarda varios minutos^)...
"%VENV_PYTHON%" -m pip install --upgrade pip
if errorlevel 1 goto :error_pip
"%VENV_PYTHON%" -m pip install -r requirements.txt
if errorlevel 1 goto :error_pip

"%VENV_PYTHON%" -c "import fastapi, uvicorn, multipart, paddleocr, pymupdf" >nul 2>&1
if errorlevel 1 (
    echo ERROR: las dependencias quedaron incompletas. Revise la salida de pip.
    goto :error
)
copy /y "requirements.txt" "%REQ_STAMP%" >nul
echo Dependencias instaladas.

:instalado
if /i not "%~1"=="install" goto :config

:: Descarga los modelos de PaddleOCR (~180 MB, en %USERPROFILE%\.paddlex) para que el
:: primer arranque no tarde
echo.
echo Verificando modelos de PaddleOCR ^(la primera vez se descargan^)...
"%VENV_PYTHON%" -c "import main; main.get_ocr_engine()"
if errorlevel 1 (
    echo ERROR: no fue posible cargar PaddleOCR. Revise la salida anterior.
    goto :error
)
echo.
echo Instalacion completa. Inicie el servicio con start.bat o "php artisan ocr start".
goto :fin

:config

:: ---------- 3. Configuracion desde el .env de Laravel ----------
if not defined OCR_SERVICE_TOKEN call :leer_env OCR_SERVICE_TOKEN
if not defined OCR_SERVICE_HOST call :leer_env OCR_SERVICE_HOST
if not defined OCR_SERVICE_HOST set "OCR_SERVICE_HOST=127.0.0.1"

if not defined OCR_SERVICE_TOKEN (
    echo ERROR: defina OCR_SERVICE_TOKEN en el .env de Laravel:
    echo   %LARAVEL_ENV%
    goto :error
)

:: ---------- 4. Arranque ----------
echo.
echo ============================================
echo  Servicio OCR en http://%OCR_SERVICE_HOST%:%OCR_PORT%
echo  Salud: http://%OCR_SERVICE_HOST%:%OCR_PORT%/health
echo  Ctrl+C para detenerlo
echo ============================================
echo.
"%VENV_PYTHON%" -m uvicorn main:app --host %OCR_SERVICE_HOST% --port %OCR_PORT%
goto :fin

:: ============================================================
:buscar_python
if defined OCR_PYTHON (
    if exist "%OCR_PYTHON%" (
        set PYTHON_CMD="%OCR_PYTHON%"
        exit /b 0
    )
    echo ERROR: OCR_PYTHON apunta a un archivo que no existe: %OCR_PYTHON%
    exit /b 1
)
py -3.12 --version >nul 2>&1
if not errorlevel 1 (
    set PYTHON_CMD=py -3.12
    exit /b 0
)
python --version >nul 2>&1
if not errorlevel 1 (
    set PYTHON_CMD=python
    exit /b 0
)
echo ERROR: no se encontro Python. Instale Python 3.12 ^(https://www.python.org^)
echo        o indique la ruta con:  set OCR_PYTHON=C:\ruta\python.exe
exit /b 1

:: Lee CLAVE=valor del .env de Laravel (sin mostrar el valor)
:leer_env
if not exist "%LARAVEL_ENV%" exit /b 0
for /f "usebackq eol=# tokens=1,* delims==" %%A in ("%LARAVEL_ENV%") do (
    if /i "%%A"=="%~1" set "%~1=%%~B"
)
exit /b 0

:error_pip
echo ERROR: fallo la instalacion de dependencias con pip.

:error
echo.
pause
endlocal
exit /b 1

:fin
endlocal
exit /b 0
