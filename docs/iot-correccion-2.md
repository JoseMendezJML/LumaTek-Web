# Corrección 2: historial, gráficas y reportes IoT

## Causas y correcciones

Los historiales y las estadísticas de reportes consultaban únicamente `readings`, mientras las mediciones ADC se guardan en `raw_sensor_readings`. Las vistas también asignaban `%` a toda la humedad del suelo y algunas consultas elegían la tabla según la configuración actual del sensor. Cambiar la calibración podía ocultar o reinterpretar mediciones anteriores.

`MeasurementHistory` unifica ambas tablas mediante `UNION ALL`, conserva el identificador de sensor y distingue los identificadores de lectura con `reading_key` (`tabla:id`). Filtra por empresa, restricciones del plan, variable, fechas y unidad. El resumen general, dashboard, consulta individual de suelo y listado seleccionan la muestra más reciente entre ambas tablas, conservando su unidad original.

Los historiales conservan el acceso de lectura a sensores inactivos de la empresa, mostrando su estado. Esto permite consultar su pasado sin habilitar nuevas mediciones. Los recursos restringidos por el plan se excluyen. Las consultas históricas no actualizan fechas de comunicación ni generan alertas o riegos.

## Unidades históricas y migración pendiente

Nueva migración: `2026_10_22_040000_preserve_measurement_history.php`. Requiere las migraciones IoT anteriores. Agrega tipo de medición, unidad en `readings`, copia de calibración, intervalo de medición, fecha de recepción y origen de metadatos en ambas tablas.

Las inserciones mediante los modelos capturan estos metadatos desde el servidor; una actualización posterior del sensor no modifica las lecturas. Los modelos rechazan modificar sus metadatos históricos. Las inserciones SQL externas a los modelos deberán proporcionar los metadatos explícitamente.

La migración completa los registros antiguos en bloques de 500 según el contrato anterior de almacenamiento: temperatura en °C y humedad en % en `readings`; RAW en `raw_sensor_readings`. No usa la unidad ni la calibración actuales para convertir valores. Marca estos metadatos como `legacy_schema`; la interfaz indica que son inferidos. No inventa calibraciones ni intervalos antiguos. Recupera la recepción desde el lote si existe; de otro modo queda nula. Para tipos antiguos desconocidos, la unidad queda desconocida.

Limitación: si el tipo del sensor fue modificado antes de esta migración, el esquema anterior no permite reconstruirlo con certeza. Tampoco permite acreditar calibraciones históricas. El texto `source=iot` sin lote no se transforma en evidencia física.

La migración utiliza Schema Builder, JSON nullable y consultas compatibles con SQLite/MySQL. Solo se ejecutó sobre SQLite en memoria; la compatibilidad MySQL se revisó estáticamente. Su aplicación en MySQL queda pendiente de autorización y respaldo operativo. El rollback rechaza eliminar metadatos cuando existen lecturas para evitar perder su interpretación histórica.

## Consultas, gráficas y reportes

- `GET /api/history`: conserva filtros existentes y añade `sensor_id`, `unit`, `page` y `per_page`.
- `GET /api/sensors/{sensor}/history`: consulta individual con validación de empresa.
- Los historiales de suelo individuales y de invernadero usan el mismo servicio.
- Páginas de 25 lecturas por defecto, máximo 500; período máximo de 93 días. Se pueden recorrer ventanas sucesivas para períodos mayores.
- Las estadísticas se calculan en SQL sobre todo el período; la gráfica representa todo el período mediante una consulta independiente de la página. Una prueba con 601 registros comprueba páginas acotadas y un máximo de tres consultas del servicio.
- Las estadísticas `by_unit` separan unidades. Si hay RAW y %, `unit=mixed` y los promedios/mínimos/máximos globales son nulos. Nunca se promedian RAW y porcentajes juntos.
- Las gráficas separan paneles por unidad y series por sensor, procedencia y calibración. Conservan puntos originales y tiempo real; interrumpen líneas en huecos superiores a tres intervalos, con mínimo de cinco minutos. En registros sin intervalo histórico se usa ese mínimo como criterio visual, sin afirmar una frecuencia antigua conocida.
- Cada fila muestra unidad, medición, recepción y procedencia. Las muestras físicas requieren lote; las manuales/simuladas y las históricas no verificadas se distinguen.
- Los reportes y el PDF muestran estadísticas y conteos de procedencia por unidad. El PDF existente continúa siendo la exportación disponible; no se introduce otro formato de exportación.

La selección de la última muestra se basa en fecha de medición y recepción, sin reinterpretarla según la calibración actual. Un sensor recién calibrado puede seguir mostrando su última muestra RAW hasta recibir una nueva muestra porcentual. RAW mantiene «Pendiente de calibración» y no se evalúa contra umbrales porcentuales.

## Archivos de esta corrección

Nuevos: `MeasurementHistory`, `HasMeasurementSnapshot`, migración 040000, `public/js/measurement-history.js`, `MeasurementHistoryTest`, `measurement-history.test.mjs` y este documento.

Actualizados: modelos `Reading` y `RawSensorReading`; controladores `History`, `Report`, `SoilMoisture`, `Temperature`, `AmbientHumidity` y `Alert`; servicios `GreenhouseSnapshotService` y `ReadingAlertEvaluator`; ruta de historial individual; vistas de historial, reportes/PDF, invernaderos y zonas; regresiones `GreenhouseRawSummaryTest` e `IotReadingsTest`. Otros cambios presentes en Git pertenecen a fases anteriores y se conservaron.

## Seguridad y pendientes

Se conservan autenticación, vinculación, lotes, deduplicación, procedencia, permisos y políticas de alertas de la corrección 1. No se modifica el contrato del ESP32 ni se implementa riego automático IoT. No hay conversiones ADC a porcentaje, reconstrucción ficticia de calibraciones ni modificación retrospectiva de valores.

Quedan pendientes aplicar la migración autorizada en MySQL, validación visual manual en navegador y cualquier futura calibración física del suelo. No se conectó a bases reales ni se modificó `.env`, Arduino o actuadores.

## Validación

La suite completa PHPUnit pasó con SQLite `:memory:`: **1261 pruebas y 19 350 aserciones**, sin fallos. Las pruebas nuevas cubren historial RAW/porcentual, cambios posteriores de unidad y calibración, procedencia física e histórica no verificada, ausencia de datos, sensores inactivos, planes Gratis/Pro, aislamiento entre empresas, PDF y páginas acotadas con estadísticas completas.

Las 12 pruebas JavaScript pasan, incluidas separación de unidades, huecos temporales, procedencia y épocas de calibración. Se verificó la sintaxis de los archivos PHP y JavaScript afectados y `git diff --check` sin errores. No se ejecutaron pruebas contra MySQL ni comprobaciones con hardware físico.


## Gráfica del período completo

`MeasurementChart` consulta los registros detallados autorizados de ambas tablas, sin aplicar el offset de la tabla. Su contrato devuelve `scope`, `start`, `end`, `method`, `reduced`, `bucket_seconds`, `max_points` y `data`, separado de los registros paginados. Un proveedor futuro de resúmenes horarios/diarios podrá mantener ese contrato; no se crearon tablas de resúmenes ni políticas de borrado.

Hasta 2000 mediciones se envían los puntos detallados. Para más datos, SQL selecciona primera, última, mínima y máxima muestra real en cada intervalo uniforme. Los intervalos se reparten según el número de series para mantener un máximo global de 2000 puntos. Una serie distingue sensor, tipo, unidad histórica, procedencia, calibración e intervalo de captura. Los extremos por intervalo y por serie se conservan, aunque no todos los eventos secundarios del mismo intervalo; para más detalle se debe acotar el período. No se crean promedios como puntos ficticios.

Los puntos reducidos se muestran sin líneas: nunca se une un salto sobre datos omitidos ni sobre cortes de transmisión. Los puntos detallados conservan el criterio de interrupción por intervalo existente. El eje temporal utiliza las fechas del filtro, incluso si hay tramos iniciales/finales sin datos. Las fechas de cada punto son las originales. La reducción no reconstruye todos los huecos internos de un intervalo: por eso no afirma continuidad y la interfaz indica que son muestras reducidas.

La selección se realiza con funciones de ventana en SQL (MySQL 8+ y SQLite), sin hidratar todo el historial en PHP. Se cargan como máximo 501 claves de series para calcular el presupuesto y 2000 puntos. Si hay más de 500 series históricas, se solicita acotar filtros con HTTP 422 en lugar de omitir series silenciosamente. MySQL no se ejecutó; la compatibilidad se revisó en el código.

Mínimo, máximo, promedio y total continúan usando `MeasurementHistory::summary` sobre todas las mediciones originales del período, agrupadas por unidad, sin usar los puntos reducidos. La tabla conserva 25 registros por página. La gráfica se recalcula con los mismos filtros al navegar y no depende de `page` ni de `per_page`; puede reflejar nuevas mediciones si llegan entre consultas.

Validación de esta mejora: 48 pruebas PHP (837 aserciones) en SQLite `:memory:` y 19 pruebas JavaScript aprobadas. Incluye invariancia entre páginas para 82 y 2202 lecturas, extremos, unidades, procedencia, límite de puntos y ausencia de interpolación en muestras reducidas.
