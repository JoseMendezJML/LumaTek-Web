# Resúmenes históricos — fase 1

## Alcance y archivos

Se agrega la migración `2026_10_23_010000_create_measurement_summaries.php`, el servicio `MeasurementSummaries`, el comando `history:summarize`, la configuración `config/measurement_summaries.php` y `MeasurementSummariesTest`. `routes/console.php` contiene una programación condicional, desactivada por defecto. Ningún controlador, gráfica, alerta, riego ni contrato IoT cambia en esta fase.

No se aplicó ninguna migración MySQL. No se borran ni actualizan lecturas ni lotes. Los originales siguen siendo la fuente de los historiales, gráficas y reportes existentes.

## Esquema y series

`sensor_measurement_summaries` guarda empresa, invernadero, sensor, hora/día, inicio y fin exclusivos, zona horaria, variable, unidad histórica, procedencia, identidad y copia de calibración, origen de metadatos e intervalo histórico. Incluye mínimo, máximo, suma, cantidad, promedio y primera/última fecha de medición. Sumas y valores usan DECIMAL(24,6), y cantidades BIGINT.

La clave de serie SHA-256 incluye esas dimensiones; los objetos de calibración se ordenan recursivamente para que cambiar el orden de sus propiedades no cree series ficticias. RAW, porcentajes, calibraciones, intervalos y procedencias distintos no se mezclan. La restricción única `(company_id, granularity, period_start, series_key)` impide duplicar períodos de una serie. Los índices cubren empresa, granularidad, variable, sensor y fecha.

La procedencia se deriva de las mismas evidencias existentes: `source=iot` con lote implica `physical_iot`; `simulation` implica `manual_or_simulated`; el resto conserva `unverified_iot`. El procesamiento no modifica esas evidencias ni acredita registros antiguos.

`measurement_summary_contributions` registra cada lectura aplicada, con unicidad `(stream, reading_id)`. Los IDs de `readings` y `raw_sensor_readings` son independientes. Este registro añade almacenamiento proporcional a los originales: esta fase prepara agregados, todavía no reduce ni archiva el almacenamiento detallado.

## Tiempo, períodos y lecturas tardías

Las fechas SQL existentes usan `config('app.timezone')`, actualmente America/Mexico_City. Los períodos respetan esa convención para mantener consistencia con los filtros actuales. Se registra la zona en el resumen y en su identidad; no se reinterpretan fechas antiguas como UTC.

Los intervalos son `[inicio, fin)`. No se generan filas vacías ni mediciones ficticias durante una desconexión. La cantidad refleja exclusivamente muestras recibidas y procesadas, no una cobertura garantizada. Un cambio de 60 a 300 segundos conserva estadísticas reales y produce series distintas por intervalo histórico.

`period_closed` se calcula al consultar: indica únicamente que pasó el fin del período. No significa que ya llegaron todos los datos, ni que el agricultor tenga cobertura completa. Los períodos abiertos son provisionales; los cerrados también pueden actualizarse por lecturas tardías. No hay una ventana de descarte: una nueva lectura puede corregir una hora/día antiguo.

Las ubicaciones empresariales se toman de las relaciones actuales al procesar por primera vez, pues las lecturas anteriores no contienen copias de empresa/invernadero. No se puede reconstruir con certeza una ubicación anterior a un traslado de sensor. Cambios posteriores de propiedad no trasladan agregados ya generados. La consulta autorizada excluye sensores que ya no pertenecen al invernadero correspondiente.

## Procesamiento incremental y fallos

El servicio recibe una empresa y procesa ambas tablas en bloques de máximo 500 lecturas. El valor predeterminado es 10 bloques por tabla y empresa (hasta 10 000 lecturas por ejecución/empresa). En cada bloque:

1. Bloquea la empresa para serializar trabajadores de ese mismo inquilino.
2. Consulta lecturas que todavía no figuran en el registro de contribuciones, ordenadas por ID.
3. Calcula incrementos horarios y diarios únicamente para ese bloque.
4. Consulta y escribe resúmenes en lotes de hasta 100, combinando mínimos/máximos/sumas/cantidades y calculando el promedio como suma/cantidad.
5. Inserta las contribuciones en la misma transacción.

El bloqueo es una protección de concurrencia de MySQL; las restricciones únicas son una defensa adicional. Si falla cualquier escritura, se revierten resúmenes y contribuciones juntos. Los bloques anteriores permanecen confirmados; la siguiente ejecución retoma los pendientes. Se permiten tres intentos de transacción ante conflictos compatibles con el reintento de Laravel.

La exploración avanza por ID dentro de cada ejecución, pero vuelve a buscar pendientes desde el inicio en la siguiente. Esto evita depender exclusivamente de una marca máxima que podría saltarse una transacción confirmada tarde con ID menor. El índice único de contribuciones permite comprobar pertenencia en SQL. Conservar años de datos exige medir el coste de esta exploración en el volumen real; queda pendiente una estrategia de cola de pendientes si ese coste lo requiere.

Los resúmenes asumen originales anexados, sin ediciones retroactivas. Una modificación SQL posterior de valor, fecha o procedencia en una lectura ya aplicada no se propaga automáticamente. Reconciliación/reconstrucción, auditoría de correcciones manuales y cambios de ubicación requieren una fase posterior explícita. No hay borrado automático ni comandos destructivos de reconstrucción.

## Preparación de consultas futuras

`authorizedQuery` proporciona filas identificadas como `aggregated_statistics`, con empresa y restricciones de planes; acepta exclusivamente períodos completos y alineados. `statistics` combina esos períodos por serie mediante `SUM(value_sum)/SUM(measurement_count)`, nunca con un promedio de promedios. El consumidor futuro debe aplicar la autenticación, permisos y sincronización de restricciones existentes antes de invocarlo, igual que con los servicios actuales. No se expone una ruta nueva.

La futura integración podrá usar originales para los extremos parciales de un rango y resúmenes para períodos completos, sin sumar horas y días superpuestos. Aún no se cambia automáticamente la fuente del Historial o de reportes ni se reemplazan las gráficas por promedios. Conservar picos gráficos y huecos requiere una decisión posterior; los agregados por sí solos no reconstruyen todos los puntos ni todos los cortes de transmisión.

## Comando y programación desactivada

`history:summarize --company=ID --chunk=500 --batches=10` limita el procesamiento a una empresa; sin `--company` recorre empresas por bloques. No depende del plan actual para preservar estadísticas de los originales; las restricciones se aplican al consultar.

El comando rechaza bases persistentes salvo que se incluya explícitamente `--allow-persistent`, después de autorizar y verificar el destino. La programación cada diez minutos solo se registra si se habilita `measurement_summaries.schedule_enabled`; su valor actual es `false`. No se configuró cron, worker, sistema operativo ni ejecución automática sobre ninguna base real. No ejecutar `schedule:run` para verificar esta función: existen otras tareas del proyecto ajenas a esta fase.

## Verificación segura

Ejecutar desde el proyecto:

```powershell
php vendor/bin/phpunit tests/Feature/MeasurementSummariesTest.php
```

El soporte de pruebas comprueba SQLite `:memory:`, elimina las demás conexiones configuradas durante el proceso, ejecuta migraciones solo en memoria y crea sus propios datos. No requiere editar `.env` ni seleccionar `lumatek_actual` o `lumatek_pruebas_migracion`. Las tablas desaparecen al terminar la prueba. Las pruebas invocan también el comando de consola dentro de ese mismo entorno en memoria.

La migración queda pendiente de revisión/aplicación autorizada en una base persistente separada. Usa Schema Builder, índices compatibles con MySQL, `upsert` y transacciones; MySQL no se ejecutó. El rollback de esta migración elimina solo los agregados y contribuciones, que son reconstruibles desde los originales; no toca lecturas ni lotes. No se ejecutó ese rollback en bases reales.

Las pruebas cubren horas/días, extremos, series RAW/%, calibraciones equivalentes y diferentes, procedencia física/manual/no verificada, sensores y empresas distintos, vacíos, reejecución, retrasos, lotes incrementales, promedio ponderado, planes y reversión ante interrupciones.

Resultados: regresión general aprobada con **1268 pruebas y 19 545 aserciones** en SQLite `:memory:`. La ejecución específica final de los resúmenes, incluida la prueba adicional de aparición tardía con ID menor y programación desactivada, aprobó **6 pruebas y 115 aserciones**. Sintaxis PHP y `git diff --check` sin errores. No se ensayó concurrencia real entre procesos MySQL ni rendimiento con millones de filas; esas verificaciones permanecen pendientes en un entorno autorizado.
