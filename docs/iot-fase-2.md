# Fase 2: recepción autenticada de lecturas

## Petición definitiva

`POST /api/iot/readings` por HTTPS, con `Content-Type: application/json`, `Accept: application/json` y `Authorization: Bearer <credencial del dispositivo>`. Reutiliza la credencial individual de la fase 1; no acepta JWT del agricultor. El contrato de `GET /api/iot/config` permanece igual.

```json
{
  "batch_id": "8e177ba1-398f-4bbc-8205-d4870636f845",
  "recorded_at": "2026-10-22T18:00:00Z",
  "readings": [
    {"sensor_id": 101, "measurement": "temperature", "unit": "°C", "value": 26.35},
    {"sensor_id": 102, "measurement": "ambient_humidity", "unit": "%", "value": 63.2},
    {"sensor_id": 103, "measurement": "soil_humidity", "unit": "raw", "value": 2800}
  ]
}
```

Los IDs y UUID del ejemplo son ilustrativos. Se deben utilizar los IDs, tipos y unidades de la configuración descargada. Ningún ID de los sensores físicos de prueba está fijado en código. No se envían empresa, dispositivo, credenciales personales ni driver en el cuerpo.

- `batch_id`: UUID generado por el cliente para una captura, persistido junto con su contenido hasta recibir confirmación. Generar otro UUID para cada nueva captura, incluso tras reiniciar. Reintentar con el mismo UUID y los mismos valores/fecha; no generar otro UUID para un reintento. La unicidad se aplica por dispositivo. Un UUID evita depender de contadores que se reinician al arrancar; no identifica criptográficamente el hardware.
- `recorded_at`: opcional; fecha común de captura en UTC, formato exacto `YYYY-MM-DDTHH:mm:ssZ`. Si se omite, el servidor asigna la hora del primer ingreso. Se aceptan capturas de las últimas 24 horas y hasta cinco minutos en el futuro por tolerancia de reloj. No añadir/cambiar la fecha al reintentar. Las fechas se convierten a la zona de la aplicación para persistir, conforme al esquema existente.
- `readings`: entre 1 y 32 mediciones; un sensor por lote, sin repetir IDs. Todos deben pertenecer al dispositivo autenticado, estar activos y disponibles por el plan. Se permiten lotes parciales (por ejemplo, solo RAW si el DHT22 falla).
- Temperatura DHT22: `temperature`, `°C`, de −40 a 80. Humedad ambiental DHT22: `ambient_humidity`, `%`, de 0 a 100. Suelo analógico: `soil_humidity`, `raw`, entero ADC entre 0 y 4095 si no hay calibración; con calibración configurada y unidad `%`, acepta un porcentaje de 0 a 100. El servidor no convierte RAW ni confía en que una unidad indicada por el cliente sustituya la configuración guardada.
- Los valores deben ser numéricos finitos. Las lecturas normales conservan la precisión `DECIMAL(10,2)` del esquema existente. El origen persistido es siempre `iot`; un `source` enviado por el cliente se ignora y no forma parte de la identidad del contenido.

## Respuestas y atomicidad

Primer ingreso: HTTP 201:

```json
{"batch_id":"8e177ba1-398f-4bbc-8205-d4870636f845","accepted":3,"duplicate":false}
```

Reintento idéntico: HTTP 200, mismos `batch_id` y `accepted`, con `duplicate:true`. No crea nuevas lecturas, alertas ni eventos. Puede actualizar la última comunicación, pero no la fecha de última lectura. El orden de los elementos y campos, y representaciones numéricas equivalentes, no cambian la identidad del lote. Un UUID existente con otro contenido devuelve HTTP 409.

Un reintento confirma un lote ya aceptado; no lo reprocesa con la configuración o el estado actual de sus sensores. Se siguen exigiendo credencial válida y disponibilidad actual del dispositivo, empresa, zona e invernadero. Una credencial revocada no permite recuperar la confirmación.

HTTP 401: falta de credencial, credencial inválida/revocada o dispositivo no vinculado. HTTP 403: empresa/dispositivo/ancestros inactivos o zona/invernadero restringidos. HTTP 422: lote inválido, sensor ajeno/inexistente/inactivo/restringido, modelo/pin incompatible, fecha, tipo, unidad o valor inválido. No revela a qué empresa pertenece un sensor ajeno. HTTP 429: límite existente de 60 solicitudes por minuto.

Se valida el lote entero y luego se guarda dentro de una transacción. Si falla una medición, no se guarda ninguna. Se bloquean empresa, dispositivo y sensores en un orden coherente con configuración y vinculación; el índice único `(device_id, batch_id)` refuerza la idempotencia en MySQL. Los identificadores y hashes de lotes deben conservarse para mantener la garantía de reintento; no se implementó una purga automática.

## Persistencia y presentación

Temperatura y humedad ambiental se guardan en el modelo `Reading`, tabla `readings`, con `source=iot`. Conservan las relaciones, dashboards, historial y alertas actuales. El evaluador IoT aplica los umbrales del invernadero y la unidad correspondiente: alertas alta/baja de temperatura, humedad ambiental alta y suelo porcentual bajo. Reutiliza la política existente: temperatura crea una alerta por lectura fuera de umbral; humedad ambiental y suelo reutilizan la alerta activa. Al normalizarse el valor, se resuelven las alertas activas correspondientes. Los lotes anteriores o simultáneos a la última lectura no cambian las alertas actuales.

RAW se guarda en `raw_sensor_readings`, con `value` entero, `unit=raw`, `source=iot`, sensor, lote y fecha. Es una corriente distinta porque los consumidores históricos de `readings.value` interpretan suelo como porcentaje. No modifica esa interpretación global ni inserta ADC en `readings`. La relación `latestRawReading` se incluye en la API existente de sensores y se muestra en la tarjeta del sensor en Zonas como RAW. El histórico RAW se conserva en esa tabla; no se añadió una gráfica histórica RAW ni se mezcló con el historial porcentual.

El endpoint no crea eventos de riego ni emite órdenes a bombas o relés, incluso al recibir suelo porcentual. El flujo de riego de los controladores anteriores permanece intacto. RAW nunca se evalúa contra umbrales porcentuales ni resuelve alertas porcentuales existentes.

Una captura nueva válida actualiza `last_connection_at` a la recepción y `last_reading_at` al máximo entre la fecha física aceptada y la última conocida, sin adelantarlo más allá de la hora del servidor. También cuenta un lote exclusivamente RAW. Una petición rechazada no actualiza esos campos. Una captura antigua puede conservarse en historial sin presentarse como lectura reciente; un reintento antiguo no vuelve a poner el dispositivo en estado de recepción reciente.

## Migración pendiente y compatibilidad

Nueva migración pendiente de autorización en MySQL: `2026_10_22_030000_create_iot_reading_batches.php`. Crea `iot_reading_batches` y `raw_sensor_readings`; agrega a `readings` la FK nullable `iot_reading_batch_id` y una restricción única por lote/sensor. Los registros anteriores quedan con FK nula y sus consumidores siguen funcionando. Se conserva la migración pendiente de fase 1 si aún no se aplicó en ese entorno.

Se usan tipos compatibles con MySQL: `BIGINT UNSIGNED` para FKs, UUID, hash de 64 caracteres, `SMALLINT UNSIGNED` para ADC, timestamps e índices de nombres menores de 64 caracteres. El valor ADC se limita además a 12 bits en la API. No se usa SQL específico de SQLite en producción. La reversión quita las FKs antes que sus índices/columnas. No se ejecutó esta migración en MySQL ni se ensayaron sus bloqueos concurrentes reales; las pruebas usan exclusivamente SQLite `:memory:`.

## Archivos de esta fase

Nuevos: `app/Services/IotReadings.php`, `app/Models/RawSensorReading.php`, `database/migrations/2026_10_22_030000_create_iot_reading_batches.php`, `tests/Feature/IotReadingsTest.php`, este documento.

Modificados: `app/Http/Controllers/Api/IotController.php`, `app/Http/Controllers/SensorController.php`, `app/Models/Reading.php`, `app/Models/Sensor.php`, `routes/api.php`, `resources/views/zones/index.blade.php`. Se conservan los cambios anteriores de la rama, incluido el estilo original de `SoilMoistureController.php` y su validación de unidad.

## Verificación y límites

Las pruebas cubren autenticación, JWT rechazado, revocación, empresa y dispositivo propietario, estados y restricciones del plan, atomicidad, valores y unidades inválidas, RAW sin alertas/riego, integración con consultas existentes, fechas UTC y fuera de ventana, lotes atrasados, reintentos y conflictos.

Resultados: suite completa aprobada, **1,214 pruebas y 18,532 aserciones**. El conjunto focalizado de recepción, vinculación y validación de unidad aprobó **41 pruebas y 663 aserciones**. Se probó la reversión/reaplicación de la migración nueva exclusivamente en SQLite en memoria, conservando lecturas anteriores. Pint pasó en los nueve archivos PHP de esta fase; sintaxis PHP, sintaxis JavaScript inline de la vista y `git diff --check` aprobados. Se volvió a probar la idempotencia tras explicitar el entero de `accepted` para uniformar respuestas entre drivers SQL. No se ensayó la interfaz en un navegador interactivo ni la migración o concurrencia en MySQL.

El usuario reportó vinculación y descarga de configuración por HTTPS con un ESP32 físico. Esa comprobación pertenece a la fase 1. La recepción de esta fase aún debe comprobarse con el dispositivo; no se modificó Arduino y no se presenta como una prueba física. No se modificaron `.env`, bases MySQL, suscripciones, contratos, OAuth ni Anthropic. No se realizaron commits, push ni merges.
