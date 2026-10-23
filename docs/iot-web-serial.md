# Asistente USB experimental

## Bloqueo de exclusividad absoluta (revisado antes de implementar)

El contrato actual no permite que el secreto de intercambio y la credencial existan exclusivamente en el ESP32: `IotController::challenge()` y `exchange()` los generan en Laravel y los entregan directamente al ESP32 por HTTPS. Laravel conserva únicamente hashes, pero necesariamente conoce transitoriamente los valores. Conseguir exclusividad absoluta exigiría rediseñar autenticación/protocolo y firmware; queda bloqueado y no se cambia ese contrato en esta tarea.

El asistente puede conservar la separación actual: el navegador solo recibe `pairing_id` y `operator_proof`; nunca solicita los endpoints de creación/canje ni recibe sus secretos. No se añaden logs de tramas, formularios o respuestas. Revisar por separado cualquier captura de cuerpos HTTP en infraestructura antes de ensayos externos.

## Contrato conservado

NDJSON UTF-8, 115200 baudios, máximo 1024 bytes antes del salto de línea. Negociación `hello`, protocolo 1 y modelo `esp32_devkit_v1`. Los comandos siguientes incluyen el nonce efímero. Ventana física de 180 segundos; desafío de 600 segundos, sujeto al cierre anterior de la ventana. `pair.exchange` espera al menos 11 segundos desde `pair.start`. La aprobación administrativa utiliza la ruta JWT existente; ninguna credencial de usuario cruza USB.

El perfil HTTPS/CA viene compilado en el ESP32: si no está preparado se bloquea el asistente, sin permitir cambiar el servidor desde USB. Sin almacenamiento cifrado, Wi-Fi e identidad solo duran en RAM. Una aprobación abandonada o un canje de resultado incierto puede necesitar revocación administrativa desde Zonas y sensores y una nueva sesión física; no hay reintentos automáticos de canje.

Referencia aportada por el operador para el merged.bin experimental: SHA-256 `424edfbca415ad9c0849031d1e9b56fdadd5658e24bc3e0db9e1aeaae9b8fb90`. No constituye aprobación de distribución, comprobación local del binario ni prueba física.

## Implementación y validación

- `public/js/iot-serial.js`: transporte Web Serial, negociación, parser acotado, Wi-Fi, prueba temporal y canje interno, limpieza y desconexión. Un timeout invalida la sesión para no atribuir respuestas tardías al comando siguiente.
- `public/js/iot-serial-ui.js` y `resources/views/iot/installation.blade.php`: controles con interacción explícita, HTTPS obligatorio, confirmación del dispositivo, contraseña sin persistencia ni envío HTTP, mensajes accesibles y cierre. No se cargan herramientas de flasheo.
- `IotInstallationController`: lista mínima de dispositivos propios compatibles sin credencial/vinculación; conserva middleware administrativo. La aprobación reutiliza las comprobaciones de empresa, actividad, plan y permisos de la API JWT existente.
- `tests/Frontend/iot-serial.test.mjs`: puerto simulado mediante streams, ruido de arranque, tramas fragmentadas, Wi-Fi, espera de 11 segundos, expiración, rechazo, desconexión, timeout e interfaz con HTTP simulado.
- `tests/Feature/IotSerialTest.php`: ESP32 simulado por peticiones HTTPS de desafío/canje separadas de la aprobación del operador, hashes, uso único, permisos y aislamiento. SQLite `:memory:` con guardas que excluyen MySQL.

Comandos locales sin dispositivos ni servicios externos:

```powershell
$frontendTests = @(Get-ChildItem -LiteralPath tests/Frontend -Filter '*.test.mjs' | ForEach-Object { $_.FullName })
node --test $frontendTests
php vendor/phpunit/phpunit/phpunit --filter 'IotSerialTest|IotInstallationTest|IotConfigurationTest'
python -X utf8 -m unittest discover -s firmware/esp32-devkit-v1/distribucion/tests -v
```

## Pendiente con ESP32 físico

No se accedió a puertos físicos, no se instaló firmware y no se habilitaron descargas. Probar en un entorno HTTPS de pruebas autorizado con el build exacto: selección/permiso USB y comportamiento DTR/RTS de la placa; ruido de arranque y negociación; cierre a los tres minutos; Wi-Fi correcto/incorrecto y validación de hora/TLS; bloqueo de perfil ausente; canje con intervalo de 11 segundos; desconexión antes/durante/después de aprobación y canje; revocación y recuperación; persistencia cifrada real frente a RAM tras corte eléctrico; descarga de configuración y recepción autenticada de lecturas. Falta también comprobar visualmente accesibilidad y controles en Chrome/Edge reales.

La ventana física no informa cuántos segundos quedan; el cliente usa un límite local adicional de tres minutos desde el handshake y acepta siempre el rechazo de ventana del firmware. No puede reabrirla. La limpieza de referencias y buffers del navegador no garantiza borrado físico de todas las copias internas del motor JavaScript.

Resultado local: 33 pruebas JavaScript aprobadas (12 del asistente), 19 pruebas Laravel / 379 aserciones aprobadas y 7 pruebas estáticas Python aprobadas. Sintaxis PHP y JavaScript verificada. Estas comprobaciones no acreditan comportamiento en hardware ni la revisión de seguridad del despliegue.
