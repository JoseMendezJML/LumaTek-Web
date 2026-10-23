# Instalación y manual IoT — fase 1

## Acceso y navegación

Desde **Zonas y sensores → Instalación IoT → Abrir guía de instalación**. Ruta: `/iot/installation`. Se reutilizan sesión web, cuenta activa, política de acceso del plan y rol `company_admin`. Empleados, superadministradores sin empresa y cuentas restringidas no obtienen acceso por esta ruta. El enlace solo se presenta a administradores activos; la autorización efectiva se valida en el servidor.

La guía se organiza en seis pasos enlazados y accesibles sin JavaScript: registrar dispositivo, instalar firmware, configurar Wi-Fi, vincular, registrar sensores y verificar transmisión. Incluye equipo probado (ESP32 DevKit V1, DHT22 compartido en GPIO4, analog_soil en GPIO34), configuración remota, intervalo, procedencia y diagnóstico. Los estilos son locales a la guía, con la paleta institucional, tarjetas, controles de al menos 44 px y cuadrículas de una/dos/tres columnas según espacio. No se añaden dependencias.

## Vinculación y hardware

Se documenta el flujo existente de desafío temporal, prueba del operador desde monitor serial, autorización web y canje privado por el ESP32. No se reemplaza por un descubrimiento o asistente de vinculación nuevo. La futura vinculación sin monitor serial se identifica explícitamente como pendiente.

El modelo probado es el identificador existente `esp32_devkit_v1`. Esta guía no acredita variantes no probadas ni constituye una distribución del firmware de laboratorio. No se fijan IDs de sensores. Se explica RAW pendiente de calibración y su diferencia con porcentaje; no hay conversiones ni cambios de alertas/riego.

## Catálogo y descargas privadas

`config/iot_installation.php` mantiene un catálogo administrado en código, sin carga ni publicación desde el navegador. La entrada inicial está **en preparación**, con versión y fecha nulas y sin archivos. Esto evita inventar versiones, fechas, enlaces o artefactos. No hay migración nueva.

La preparación admite versiones con modelo, versión, fecha, instrucciones y estado (`planned`, `draft`, `published`, `withdrawn`). El servicio solo autoriza `.ino`/`.bin` cuando:

- El modelo es el ESP32 DevKit V1 compatible y la versión está publicada.
- Existe aprobación explícita, identidad de revisión, fecha de aprobación y revisión de secretos afirmativa.
- Hay versión, fecha e instrucciones de la versión.
- Existe un archivo privado no vacío, legible, con nombre permitido y extensión coincidente.
- Su ruta real permanece dentro del directorio privado y su SHA-256 coincide con el registrado.

Directorio previsto: `storage/app/private/iot-firmware`, fuera de `public`. No se creó ni se copió firmware real allí. Los archivos deben mantenerse privados en la configuración del servidor, sin enlaces públicos, incluyendo reglas del servidor que impidan publicar `storage/app/private`.

La ruta de descarga `/iot/firmware/{release}/{format}` valida nuevamente permisos y disponibilidad en cada solicitud, devuelve un adjunto con `nosniff` y evita caché compartida. No admite rutas enviadas por el cliente. Las versiones globales aprobadas pueden usarse entre administradores autorizados de distintas empresas; una entrada opcional con `company_id` solo se lista y descarga para esa empresa, devolviendo 404 al resto. No hay lecturas, dispositivos o credenciales de una empresa incorporados al catálogo.

El hash acredita integridad desde la revisión, no demuestra por sí solo ausencia de secretos ni seguridad de un binario. Antes de marcar una versión aprobada, la revisión humana debe comprobar fuente y configuración de compilación, ausencia de SSID, contraseñas, tokens y credenciales embebidas, compatibilidad física, parámetros de instalación e instrucciones. No se publican artefactos de laboratorio. Se debe desplegar cada archivo y su metadato de forma controlada e inmutable; después de cualquier cambio el hash debe invalidar la distribución hasta una revisión nueva.

## Instalador USB pendiente

La interfaz muestra un botón desactivado. No carga ESP Web Tools, no ofrece manifiestos, no abre puertos USB y no modifica equipos. Habilitar una descarga manual en el futuro no activa automáticamente ese botón.

Falta desarrollar y aprobar firmware distribuible, su proceso de provisión Wi-Fi/credenciales sin secretos embebidos, imágenes compiladas y parámetros de escritura para el modelo real, manifiesto ESP Web Tools válido, integración de navegador y ensayos USB/hardware. El proceso simplificado sin monitor serial también requiere implementación independiente y revisión de seguridad. No se configura ni ejecuta nada de esto en esta fase.

## Archivos

Nuevos: `IotInstallationController.php`, `IotFirmwareCatalog.php`, `config/iot_installation.php`, `resources/views/iot/installation.blade.php`, `IotInstallationTest.php` y este documento.

Modificados: `routes/web.php` y `resources/views/zones/index.blade.php`. Se conservaron los cambios IoT anteriores. No se modificó firmware Arduino, contratos API, mediciones, alertas, riego, `.env` ni bases reales.

## Verificación segura

```powershell
php vendor/bin/phpunit tests/Feature/IotInstallationTest.php
```

Las pruebas usan exclusivamente SQLite `:memory:` y artefactos sintéticos temporales que se eliminan al terminar, nunca firmware real de distribución. Cubren navegación, los seis pasos, cuentas/roles/planes, descargas autorizadas, alcance empresarial, ausencia de archivos aprobados, revisión y metadatos incompletos, retirada, integridad y rutas inválidas.

En navegador: iniciar sesión como administrador de empresa, abrir Zonas y sensores y entrar a la guía. Comprobar navegación por pasos y presentación a 1440/768/375 px. Con el catálogo actual ambas descargas y el instalador USB deben permanecer desactivados. La comprobación visual manual queda pendiente; no se accedió a dispositivos ni bases persistentes.

Resultados: 43 pruebas de integración/regresión (720 aserciones) aprobadas, incluyendo instalación, vinculación, recepción IoT y páginas existentes. Tras reforzar explícitamente la caché privada de las descargas, la ejecución específica final aprobó 5 pruebas y 121 aserciones. Además pasaron las 21 pruebas JavaScript existentes. Verificación de sintaxis PHP y del JavaScript de Zonas, y `git diff --check`, sin errores. No se ejecutó nuevamente la suite PHP completa en esta fase.
