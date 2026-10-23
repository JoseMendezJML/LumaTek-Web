# IoT fase 1: contrato de integración

Implementación del servidor; no se ha probado con un ESP32 físico. No se modificó Arduino, `.env`, OAuth, Anthropic, reglas de contratación ni bases MySQL. No se añadieron acciones físicas de riego.

## Registro y configuración

El formulario de zonas conserva la administración existente. Los nuevos dispositivos son ESP32 DevKit V1 con WiFi, identificador aleatorio `LT-` de 128 bits generado por el servidor e intervalo de 10–3600 segundos (60 por defecto). El identificador permanece inmutable al editar. `active` significa habilitado administrativamente, nunca conectado físicamente. La relación dispositivo → zona → invernadero → empresa define una sola empresa propietaria; la API no permite cambiar esa relación.

Sensores: el formulario pide modelo, medición, GPIO y calibración opcional. DHT22 admite temperatura (`°C`) y humedad ambiental (`%`) en GPIO4, una fila por variable. Ambos comparten el pin; no se admite duplicar una variable. `analog_soil` admite ADC1 GPIO32, 33, 34, 35, 36 y 39, exclusivamente una fila por pin, incluso si otra fila está desactivada. Un sensor desactivado conserva la reserva de su pin. No se ofrecen otros drivers. Este catálogo define el contrato que debe implementar el firmware futuro; no certifica compatibilidad de un firmware actualmente instalado.

La calibración analógica es `{"dry":3000,"wet":1000}`: dos valores ADC distintos entre 0 y 4095. Para convertir: limitar a 0–100 el resultado `(raw - dry) * 100 / (wet - dry)`. Sin calibración la unidad es `raw`. La API existente de lecturas porcentuales rechaza sensores RAW. Esta fase no añade recepción IoT de lecturas ni convierte valores recibidos.

La validación se aplica en registro/edición y también al descargar; los sensores antiguos sin configuración compatible requieren edición antes de utilizarse en IoT. Las escrituras de sensores se serializan por empresa y dispositivo para evitar colisiones y superar el plan por solicitudes simultáneas. SQLite no demuestra el comportamiento de bloqueos concurrentes de MySQL.

## Vinculación

Usar HTTPS con validación del certificado en el firmware. No usar JWT del agricultor en el ESP32.

1. El ESP32, en un modo de instalación habilitado físicamente, solicita `POST /api/iot/pairings` sin autenticación. Recibe `pairing_id`, `operator_proof`, `exchange_secret` y `expires_in:600`. Los secretos se generan con `random_bytes`; el servidor guarda exclusivamente hashes SHA-256. La respuesta lleva `Cache-Control: no-store`.
2. El firmware conserva `exchange_secret` en privado y muestra **solo** `pairing_id` y `operator_proof` al operador mediante USB/serial o una interfaz local protegida. No debe exponerlos por descubrimiento remoto. Este canal local y el modo físico todavía no están implementados.
3. El administrador autenticado de la empresa usa **Vincular / renovar** e introduce ambos valores. `POST /api/devices/{device}/pairing` valida permisos, empresa, estados, plan, caducidad y prueba. El código público `LT-...` no sustituye la prueba privada. Un desafío solo puede aprobarse para un dispositivo. Se rechaza una segunda vinculación mientras haya una credencial vigente; se debe revocar primero.
4. El mismo ESP32 envía `POST /api/iot/pairings/exchange` con `pairing_id` y `exchange_secret`. Solo después de la aprobación recibe `device_id` y `credential` (256 bits), una sola vez. El desafío se consume atómicamente y no puede reutilizarse. El servidor guarda únicamente el hash de la credencial; la oculta en la serialización del dispositivo. El ESP32 debe almacenarla en NVS protegida.
5. `GET /api/iot/config` usa `Authorization: Bearer <credential>`. No se aceptan JWT personales. El servidor deriva la empresa del dispositivo autenticado; no acepta seleccionar otra empresa o dispositivo mediante parámetros.
6. **Revocar**, `DELETE /api/devices/{device}/pairing`, invalida la credencial y desafíos aprobados pendientes. Para renovar se repite el flujo local con otro desafío. Si se pierde la respuesta del canje, se debe revocar y repetir; no se recuperan secretos guardados en texto plano.

Las rutas de desafío, canje y aprobación tienen límite de seis solicitudes por minuto con el middleware existente; configuración tiene 60 por minuto. Los límites numéricos de Laravel pueden compartir contador para una misma identidad/IP. Tras varios pasos rápidos puede ser necesario esperar al siguiente minuto. La prueba de operador tiene 192 bits y el secreto de canje 256 bits; no son PIN cortos. En despliegue, el caché de limitación debe ser persistente y compartido entre instancias. No registrar cuerpos de estas solicitudes ni cabeceras de autorización en proxies o telemetría.

El servidor verifica posesión de secretos de instalación y aprobación del administrador. La prueba de control **físico** depende de que el firmware futuro respete el canal local descrito. No hay atestación de hardware, identidad de fábrica ni detección de una placa reflasheada o clonada. La exclusividad implementada corresponde al registro lógico y sus credenciales. Garantizar exclusividad de una misma placa frente a reinstalaciones requerirá una identidad provisionada de hardware y su protocolo de transferencia; no debe darse por comprobada en esta fase.

## JSON de configuración

Ejemplo con todas las claves definitivas (IDs ilustrativos; versión calculada por el servidor):

```json
{
  "device_id": "LT-0123456789ABCDEF0123456789ABCDEF",
  "board_model": "esp32_devkit_v1",
  "measurement_interval": 60,
  "sensors": [
    {"id": 101, "driver": "DHT22", "pin": 4, "measurement": "temperature", "unit": "°C", "calibration": null},
    {"id": 102, "driver": "DHT22", "pin": 4, "measurement": "ambient_humidity", "unit": "%", "calibration": null},
    {"id": 103, "driver": "analog_soil", "pin": 32, "measurement": "soil_humidity", "unit": "%", "calibration": {"dry": 3000, "wet": 1000}}
  ],
  "configuration_version": "<sha256 de la configuración, 64 caracteres hexadecimales>"
}
```

`configuration_version` es una huella del contenido ordenado, no un contador monotónico. Cambia al cambiar intervalo, sensores autorizados, driver, pin, unidad o calibración. `measurement_interval` está expresado en segundos; `pin` es el número GPIO. No se incluyen secretos ni datos de usuarios. Solo se entregan sensores activos no restringidos después de sincronizar las restricciones existentes del plan Gratis o Pro. Empresa, invernadero, zona y dispositivo deben estar activos; invernadero y zona deben estar disponibles por el plan. Se rechaza con 422 una configuración incompatible en lugar de enviarla parcialmente. Se rechaza con 401 una credencial inválida o revocada y con 403 un recurso inactivo o restringido.

## Estados

El registro aparece como pendiente de vinculación. El campo administrativo `status` se conserva; `connection_state` distingue `pending_pairing`, `paired_without_readings`, `receiving_readings`, `stale`, `configuration_error` y `disabled`. La tarjeta muestra estado IoT y última comunicación. El umbral de comunicación reciente es el mayor entre cinco minutos y tres intervalos de medición. El canje y la consulta de configuración actualizan la última comunicación; nunca la fecha de lecturas.

`last_reading_at` queda reservado para futura recepción de lecturas IoT autenticadas. No se actualiza por lecturas manuales o simuladas de los módulos existentes. Por tanto, esta fase no produce por sí sola el estado de recepción de lecturas físicas. El estado se prueba únicamente con fechas simuladas.

## Migración y comprobaciones

Pendiente de ejecución autorizada en MySQL: `database/migrations/2026_10_22_020000_add_iot_configuration.php`. Extiende `devices` y `sensors`, y crea `iot_pairings` para desafíos temporales; reutiliza la jerarquía multiempresa existente. No migra ni adivina pines de registros antiguos.

Pruebas: `php vendor/bin/phpunit`, con `phpunit.xml` y el trait `UsesInMemoryDatabase`, que exige SQLite `:memory:` y elimina las conexiones alternativas antes de migrar. Las pruebas nuevas cubren identificadores, permisos, credenciales, canje único, revocación, renovación, vencimiento, límite de intentos, pines compartidos/exclusivos, calibración, rechazo RAW, planes Gratis/Pro y vencimiento, jerarquía activa y aislamiento de empresas. Pint y sintaxis PHP se verifican sobre los archivos afectados. No se ejecutaron migraciones MySQL ni ensayos físicos.

Resultado de la suite completa: 1,184 pruebas aprobadas, 18,071 aserciones. Pint y `php -l` aprobados en los once archivos PHP afectados. JavaScript inline verificado con `node --check` tras retirar las directivas Blade; las vistas también se compilan en las pruebas web existentes. No se realizó prueba interactiva en navegador. La comprobación remota de `develop` falló por conectividad a GitHub; al iniciar, la rama local coincidía con `origin/develop` en `e3a9cf3` y el árbol estaba limpio.

Archivos creados: `app/Http/Controllers/Api/IotController.php`, `app/Services/IotConfiguration.php`, `database/migrations/2026_10_22_020000_add_iot_configuration.php`, `tests/Feature/IotConfigurationTest.php` y este documento. Archivos modificados: `app/Http/Controllers/DeviceController.php`, `app/Http/Controllers/SensorController.php`, `app/Http/Controllers/SoilMoistureController.php`, `app/Models/Device.php`, `app/Models/Sensor.php`, `resources/views/zones/index.blade.php`, `routes/api.php` y `tests/Feature/ResourcePermissionsTest.php`.

Pendiente para hardware: firmware de instalación y drivers, almacenamiento seguro de credenciales, TLS, cliente de configuración, recepción de lecturas autenticadas y pruebas de cableado/calibración reales. También conviene definir la retención y limpieza periódica de desafíos caducados antes de exponer el servicio de instalación a escala. No hay descubrimiento remoto ni control de bombas añadido.
