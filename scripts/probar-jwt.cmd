@echo off
setlocal
chcp 65001 >nul

REM Ejecuta únicamente las pruebas de autenticación JWT de LumaTek.
REM La salida nativa de PHPUnit se conserva para facilitar el diagnóstico.
pushd "%~dp0.."
php vendor/bin/phpunit --configuration phpunit.xml --filter AuthenticationVersionTest --testdox
set "RESULTADO=%ERRORLEVEL%"

echo.
if "%RESULTADO%"=="0" (
    echo RESULTADO FINAL: PRUEBAS DE AUTENTICACIÓN JWT APROBADAS.
) else (
    echo RESULTADO FINAL: SE DETECTARON ERRORES EN LAS PRUEBAS JWT.
    echo Revisa los detalles anteriores para identificar qué prueba falló.
)

popd
exit /b %RESULTADO%
