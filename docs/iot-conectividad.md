# Vigencia de comunicación IoT

## Auditoría y causa

Antes de esta corrección, `HasReadingProvenance::connectionStatus` usaba la fecha de medición y una ventana `max(300, intervalo * 3)`. Con un intervalo de 300 segundos, la lectura seguía indicando conectado durante 900 segundos (15 minutos). Un hueco de 12 minutos quedaba dentro de esa ventana. No era una ausencia total de comparación con el reloj, sino una tolerancia excesiva para ese intervalo.

`Device::connection_state` también usaba la ventana de tres intervalos, combinando comunicación y última medición. Dashboard/Invernaderos/consultas individuales utilizaban el método de las lecturas; Zonas mostraba el diagnóstico del dispositivo y la procedencia de la última lectura sin una política común de conexión. Esta auditoría se realizó sobre código y pruebas aisladas, sin consultar bases reales ni el Invernadero Sur físico.

## Política común

`IotConnectivity` calcula la tolerancia como `max(60 segundos, 20% del intervalo configurado)`:

| Estado | Tiempo desde la última comunicación autenticada |
|---|---|
| Conectado (`connected`) | Menor que intervalo + tolerancia |
| Sin comunicación reciente (`stale`) | Desde ese límite hasta menos de 2 × intervalo + tolerancia |
| Desconectado (`disconnected`) | Desde el segundo límite o sin fecha acreditada |

Para 300 segundos: conectado antes de 360 segundos, sin comunicación reciente desde 360 hasta menos de 660, desconectado desde 660 segundos. Para 60 segundos los límites son 120/180; para 900 segundos, 1080/1980. No se modifica ningún intervalo almacenado.

La fecha utilizada es `devices.last_connection_at`, actualizada por canje de vinculación autenticado, configuración autenticada, lotes válidos y reintentos auténticos reconocidos. Una configuración inválida que autentica al dispositivo también acredita comunicación, manteniendo el diagnóstico de error por separado. Las lecturas manuales, simuladas e históricas sin lote no escriben esta fecha. No se incorpora ningún fallback desde la fecha de una lectura a una comunicación ausente.

Se conserva `connection_state` como diagnóstico compatible (vinculación, configuración, recepción de mediciones). La vigencia presentada usa los nuevos `connection_status` y `connection_label`, calculados en cada acceso. El estado administrativo `status=active` no prueba conectividad. Una revocación deja pendiente de vinculación y la credencial anterior es rechazada; los recursos inactivos se muestran desactivados.

## Comunicación frente a medición

La conexión del dispositivo no garantiza que cada sensor haya producido una lectura nueva. Por ello se conservan el valor y `recorded_at` y se agregan `measurement_is_stale`, `measurement_label` y `last_authenticated_communication_at` en las consultas actuales. La vigencia de la medición usa su propia fecha y el intervalo actual, sin escribir datos.

Una consulta autenticada de configuración puede mostrar el dispositivo conectado y una medición antigua al mismo tiempo. Una lectura manual reciente conserva su etiqueta de simulación/manual; nunca se presenta como evidencia física, aunque el dispositivo tenga comunicación válida. La procedencia de una lectura física antigua tampoco se elimina al desconectar o revocar el dispositivo.

Dashboard, Invernaderos y Zonas conservan valores y muestran fecha/antigüedad. Las consultas individuales de temperatura, humedad ambiental y suelo utilizan la misma política. Los valores RAW siguen separados y no cambian sus umbrales ni sus reglas de alerta/riego.

Las fechas se recalculan con `now()` al consultar y al serializar respuestas, sin esperar una nueva lectura ni persistir un booleano conectado. Una página que permanece abierta necesita consultar nuevamente o recargarse para recibir un estado actualizado; no se añade polling ni un reloj independiente en JavaScript.

## Archivos de esta corrección

- Nuevo `app/Services/IotConnectivity.php`.
- `app/Models/Device.php` y `app/Models/Concerns/HasReadingProvenance.php`.
- `app/Services/GreenhouseSnapshotService.php`.
- Controladores `SensorController`, `TemperatureController`, `AmbientHumidityController` y `SoilMoistureController`.
- Vistas `dashboard/index`, `greenhouses/index` y `zones/index`.
- Nuevo `tests/Feature/IotConnectivityTest.php`; ajustes en `DashboardSnapshotRegressionTest` para comprobar los nuevos metadatos y en `GreenhouseRawSummaryTest` para acreditar explícitamente la comunicación de sus muestras físicas de prueba.
- `tests/Frontend/greenhouse-summary.test.mjs` y este documento.

No hay migración nueva. No se modifican escritores IoT, firmware, intervalo físico de 300 segundos, alertas o riego. Tampoco `.env`, bases MySQL ni lotes.

## Verificación segura

```powershell
php vendor/bin/phpunit tests/Feature/IotConnectivityTest.php
```

La prueba usa exclusivamente SQLite `:memory:` y reloj simulado: comprueba las tres pantallas, listados, consultas individuales, límites exactos, diferentes intervalos, valores antiguos visibles, reconexión real mediante el endpoint de lecturas, consulta de configuración con medición antigua, revocación, inactividad y acceso entre empresas. No requiere esperar físicamente ni operar sobre equipos o bases persistentes.

Para una comprobación visual autorizada del equipo ya vinculado: recargar tras 6 minutos y tras 11 minutos sin comunicación. Se conserva el último valor con su fecha, cambiando primero a sin comunicación reciente y después a desconectado. Al recibir una comunicación autenticada nueva, recargar y comprobar conectado. No se realizaron estas operaciones físicas desde el agente.

Resultado final: **1272 pruebas PHP y 19 735 aserciones aprobadas** exclusivamente con SQLite `:memory:`; **21 pruebas JavaScript aprobadas**. Verificación de sintaxis PHP/JavaScript y `git diff --check` sin errores. La expectativa exacta del JSON del Dashboard se actualizó para incluir los nuevos campos de antigüedad, manteniendo las comprobaciones anteriores.
