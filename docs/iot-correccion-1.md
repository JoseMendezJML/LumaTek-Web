# Corrección 1: recepción segura, procedencia y episodios de alertas

## Alcance y causas corregidas

Los receptores antiguos de temperatura y humedad ambiental verificaban empresa y plan, pero no toda la jerarquía activa. Además aceptaban `source=iot` con JWT de usuario y actualizaban `last_connection_at`. El receptor porcentual de suelo comprobaba parte de la jerarquía, pero también aceptaba ese origen y escribía la fecha de conexión. Las tres rutas ahora usan `EnsureManualReadingAccess`: autorización por empresa, bloqueo de empresa/dispositivo/sensor, restricciones actuales de plan y verificación de sensor, dispositivo, zona e invernadero activos. Se conservan roles y permisos de las rutas existentes.

Una captura manual legítima omite `source` o envía `source=simulation`; el servidor guarda siempre `simulation`. `source=iot` y un intento de enviar `iot_reading_batch_id` por esas rutas reciben 422. Una ubicación inactiva recibe 422; un recurso restringido o de otra empresa recibe 403. Las lecturas manuales no actualizan fechas de comunicación o lectura física.

No se cambió la vinculación, la descarga de configuración, el cuerpo de recepción IoT ni la idempotencia por UUID. El ESP32 continúa usando su credencial individual; la ruta IoT fija `source=iot` desde el servidor y vincula cada lectura con el lote autenticado. No se introdujeron migraciones nuevas ni se modificó `.env`, Arduino, OAuth, Anthropic, suscripciones o contratos.

## Procedencia definitiva

La clasificación se calcula al leer mediante `HasReadingProvenance`, sin reescribir registros:

| `provenance` | Evidencia | Etiqueta |
|---|---|---|
| `physical_iot` | `source=iot` y FK no nula a un lote IoT persistido | IoT físico |
| `manual_or_simulated` | `source=simulation` | Simulación / manual |
| `unverified_iot` | Origen antiguo/no acreditado, incluido `iot` sin lote | IoT no verificado |

La FK del esquema de fase 2 conserva la referencia al lote. Las rutas con JWT no permiten crearla. No se acredita retroactivamente una lectura por decir `iot`, ni se cambia su `source`. Temperatura, humedad ambiental, suelo, resúmenes, listado de sensores, historial y alertas exponen o presentan la clasificación del servidor. Las nuevas propiedades de lectura son `provenance`, `source_label` e `is_physical`; las respuestas transformadas incorporan los metadatos correspondientes.

## Estados de comunicación

- `status=active/inactive` continúa siendo habilitación administrativa.
- `last_connection_at` corresponde a los caminos IoT autenticados: canje, descarga de configuración y recepción/reintento válido. Las cargas manuales no escriben este campo.
- `last_reading_at` solo se actualiza por una captura física nueva aceptada, con la lógica existente de no retroceder ni adelantarla más allá de la recepción. Un reintento no la actualiza.
- `last_manual_reading_at` se calcula para el listado de dispositivos a partir de sus lecturas `simulation`, sin columna ni migración adicional.
- La conexión por sensor usa la procedencia de su lectura y el umbral `max(300 segundos, 3 × intervalo del dispositivo)`, igual que el dispositivo. Las lecturas manuales devuelven `simulated`; las antiguas no acreditadas, `unverified`. Ninguna de las dos devuelve `connected`.
- Una jerarquía desactivada devuelve `disabled` al mostrar una lectura existente. Una lectura físicamente acreditada conserva esa procedencia tras revocación, pero la conexión es `unpaired` mientras no haya vinculación vigente.
- El dispositivo puede tener comunicaciones IoT recientes y una lectura manual más reciente para un sensor. Las pantallas muestran ambas dimensiones: la captura manual no se convierte en prueba de conexión física. Se conserva la selección de última lectura para presentación, identificando su origen.

Los valores antiguos de `last_connection_at` permanecen intactos: no es posible demostrar retroactivamente qué camino los escribió. La corrección impide nuevas escrituras manuales; no inventa evidencia para fechas históricas.

## Política de alertas

Los receptores manuales y `IotReadings` usan `ReadingAlertEvaluator`. Las acciones de reconocer/resolver se serializan con la recepción mediante bloqueos en el orden empresa → dispositivo → sensor. Los episodios se identifican por sensor, tipo, procedencia y lecturas de recuperación, sin tabla o migración adicional.

1. Primera lectura anómala: crea una alerta `active`, enlazada con la primera lectura del episodio.
2. Nuevas lecturas de la misma anomalía: reutilizan la alerta. No se sustituye su lectura inicial ni se pierde el historial.
3. `acknowledged`: el episodio sigue abierto. Otra captura anómala no crea una alerta nueva ni elimina quién la reconoció.
4. Recuperación: se resuelven las alertas `active` y `acknowledged` del tipo y procedencia correspondientes. Se conservan los datos de reconocimiento.
5. Resolución manual durante una anomalía: conserva `resolved` y evita reabrir/recrear el mismo episodio mientras no haya recuperación. Después de una lectura recuperada, una nueva anomalía puede crear otra alerta.
6. Temperatura alta y baja son tipos distintos. Cambiar de un extremo al otro cierra el tipo anterior y abre el correspondiente. Humedad ambiental solo tiene alerta alta; suelo porcentual solo tiene alerta baja, conforme a las reglas existentes.
7. Los episodios físicos y manuales son independientes. Una simulación normal no resuelve una alerta física; una captura física normal tampoco resuelve una alerta de simulación.
8. Una lectura atrasada se guarda en historial, pero no cambia un episodio más reciente de la misma procedencia. Los reintentos del mismo UUID no vuelven a ejecutar la evaluación; un UUID con otro contenido continúa devolviendo 409.
9. RAW no se evalúa contra umbrales porcentuales. Una unidad de umbral incompatible tampoco se utiliza.

Los duplicados históricos no se borran ni se fusionan retrospectivamente. La corrección evita añadir nuevas alertas al mismo episodio acreditado; al recuperarse el valor, se cierran las abiertas del tipo/procedencia correspondiente. Una alerta antigua sin lectura enlazada, o cuya lectura no acredite procedencia física, no se atribuye automáticamente a un episodio físico nuevo. Puede requerir resolución manual. No hay limpieza masiva de datos ni cambios de `source` históricos.

## Riego y pendientes

`IotReadings` sigue sin crear eventos de riego ni emitir órdenes físicas. El flujo de riego automático del endpoint porcentual anterior se conserva y se comprueba en SQLite; solo se sustituye su evaluación de alertas. RAW continúa rechazado por ese receptor porcentual. No se modificaron el comando de riego ni su programación.

La consulta individual de suelo RAW usa ahora la selección segura del resumen: valor RAW, sin umbral porcentual y con procedencia. Siguen pendientes la gráfica e historial RAW generales y los reportes RAW. También sigue pendiente almacenar una instantánea de unidad/tipo/calibración por lectura para impedir reinterpretaciones históricas si se cambia la configuración de un sensor. No se mezcló RAW en `readings` para suplir esos pendientes. La edición de umbrales desde web/API no se implementa en esta corrección.

## Archivos

Nuevos: `app/Models/Concerns/HasReadingProvenance.php`, `app/Http/Middleware/EnsureManualReadingAccess.php`, `app/Services/ReadingAlertEvaluator.php`, `tests/Feature/ReadingSecurityAndEpisodesTest.php` y este documento.

Modificados en esta corrección:

- Modelos: `Reading.php`, `RawSensorReading.php`, `Device.php`.
- Controladores: `TemperatureController.php`, `AmbientHumidityController.php`, `SoilMoistureController.php`, `AlertController.php`, `HistoryController.php`, `DeviceController.php`.
- Servicios: `IotReadings.php`, `GreenhouseSnapshotService.php`.
- Rutas: `routes/api.php` (middleware únicamente en los tres receptores antiguos).
- Vistas: `dashboard/index.blade.php`, `greenhouses/index.blade.php`, `zones/index.blade.php`, `history/index.blade.php`, `alerts/index.blade.php`.
- Pruebas existentes: `SoilMoistureUnitValidationTest.php`, `DashboardSnapshotRegressionTest.php`, `GreenhouseRawSummaryTest.php` y `tests/Frontend/greenhouse-summary.test.mjs`.

## Verificación

La suite usa `phpunit.xml` y `UsesInMemoryDatabase`, que exige SQLite `:memory:` antes de migrar y elimina conexiones alternativas durante las pruebas. No se ejecutaron migraciones MySQL ni se consultaron las bases reales. Los ensayos de riego solo crean eventos simulados en la memoria de las pruebas; no activan bombas ni relés.

Las regresiones cubren jerarquía inactiva en los tres receptores, capturas manuales legítimas, origen falsificado, históricos no acreditados, separación de comunicaciones, repetición de episodios, reconocimiento/resolución, cambio de extremo térmico, normalización y episodio nuevo, separación manual/física, revocación, Gratis/Pro y aislamiento empresarial. Las pruebas IoT anteriores siguen cubriendo reintentos UUID, conflictos, configuración, vinculación y RAW sin riego. Las pruebas JavaScript ejecutan las funciones de las vistas para verificar unidades y procedencia.

No se ha repetido la prueba con ESP32 físico ni la concurrencia real de MySQL. La comunicación física anterior es la reportada por el usuario; no se presenta como validación física de esta corrección. No se hicieron commits, push ni merges.

Resultados finales: **1,253 pruebas PHP aprobadas y 19,191 aserciones** con `php vendor/bin/phpunit`; **8 pruebas JavaScript aprobadas** con `node --test tests/Frontend/greenhouse-summary.test.mjs`. Sintaxis PHP aprobada en los 19 archivos PHP afectados por esta corrección; sintaxis JavaScript aprobada en las cinco vistas. `git diff --check` aprobado. Pint aprobado en los 13 archivos nuevos o previamente normalizados comprobados (trait, middleware, evaluadores, modelos, controlador de dispositivos, rutas y pruebas); se conservó el formato heredado de los demás controladores y del servicio de resumen para evitar diferencias ajenas a la corrección. Se verificó adicionalmente que el cuerpo de `tryStartAutomaticIrrigation` conserva exactamente el contenido de `develop`.

Compatibilidad: clientes antiguos que enviaban `source=iot` con JWT deben omitirlo o enviar `simulation`; la respuesta de intento de suplantación es ahora 422. Las respuestas incorporan metadatos de procedencia y estados `simulated`, `unverified`, `disabled` y `unpaired`. El plazo de frescura de consultas individuales deja de ser diez minutos fijos y se alinea con el dispositivo (cinco minutos para intervalo de 60 segundos). Los clientes deben mostrar estos estados sin convertirlos a «Conectado». La normalización ahora cierra también las alertas atendidas del episodio; una resolución manual evita reaperturas hasta una recuperación posterior. Ningún cambio requiere reclasificar lecturas históricas ni ejecutar una migración adicional.
