# Sincronización automática de restricciones

`subscriptions:sync-restrictions` aplica las restricciones del plan efectivo por
empresa. No activa Pro, no renueva, no cobra y no modifica suscripciones ni contratos.
Una cotización pendiente no concede acceso. Los intervalos siguen siendo [inicio, fin).

## Funcionamiento

El Scheduler lo registra cada minuto, después del registro de riego existente,
con `withoutOverlapping()` y ejecución en segundo plano para no retener el proceso
del Scheduler durante un recorrido largo. El evento y su mutex son independientes
del riego. Se requiere que el operador mantenga funcionando el Scheduler normal
de Laravel; esta implementación local no configura cron, servicios ni hosting.

El comando recorre todas las empresas mediante `chunkById` (100 por lote por
defecto), incluyendo inactivas: sincronizar banderas no reactiva empresas ni usuarios.
Las lecturas por empresa comprueban integridad histórica y resuelven una sola vez
el periodo vigente. La huella SHA-256 incluye versión de reglas, identidad del
contrato efectivo (o Gratis de respaldo) y los cinco límites. Un nuevo contrato
produce otra huella incluso si sus límites coinciden con los anteriores.

La tabla auxiliar `subscription_restriction_states` guarda `company_id` como clave
primaria/foránea, `fingerprint` y `synced_at`. No almacena ni reemplaza historia
comercial. La migración nueva únicamente crea esa tabla; su rollback solo elimina
metadatos derivados. No hace backfill: la primera ejecución sincroniza todas las
empresas. Eliminar metadatos obliga a sincronizarlas otra vez.

Cada empresa tiene una transacción con bloqueo de su fila y hasta tres intentos
ante interbloqueos. Si la huella coincide, no se escriben recursos ni metadatos.
Si cambia, se ejecuta `syncSensorPlanRestrictions()` (incluye zonas e invernaderos)
y `syncUserPlanRestrictions()` compartiendo el mismo plan. La huella se guarda al
final, dentro de la misma transacción. Un fallo revierte banderas y metadatos de esa
empresa, continúa con las demás y devuelve código 1. Éxito devuelve 0 y resume
empresas sincronizadas, sin cambios y fallidas.

Esta estrategia no pierde vencimientos si el Scheduler estuvo detenido: aplica el
estado efectivo actual al reanudarse, sin reproducir periodos intermedios. Lee todas
las empresas cada minuto pero escribe recursos solo cuando hace falta. No depende
de una ventana temporal ni mantiene un puntero que pueda omitir cambios tardíos.
La persistencia de banderas tiene una demora normal de hasta aproximadamente un
minuto, más esperas/carga. Los controles dinámicos de acceso continúan vigentes.

## Reglas conservadas

- Recursos activos se priorizan por ID ascendente. Los excedentes reciben
  `plan_restricted`; no se eliminan ni se cambia su estado operativo.
- El límite de sensores es por zona, sumando dispositivos. Se conserva la regla
  actual que cuenta sensores activos incluso en dispositivos inactivos. No existe
  bandera propia de restricción en Device: su disponibilidad depende de sus padres.
- Todos los propietarios quedan fuera de las restricciones por plan. Se cuentan
  todos los propietarios activos al calcular plazas de empleados, igual que en
  `isUserRestrictedByPlan()`. Las cuentas/empresas inactivas siguen bloqueadas por
  sus mecanismos de autenticación; esta excepción no les concede acceso.
- Límites Pro provienen del contrato inmutable; Gratis puede provenir de su
  suscripción histórica vigente o del catálogo de respaldo según `effectivePlan()`.
- Un plan efectivo ausente o historia inconsistente produce un error, sin guardar
  una sincronización exitosa ni borrar las restricciones anteriores.
- El procesamiento de riegos ya iniciados sigue independiente de estas banderas
  y puede finalizarlos aunque la zona haya quedado restringida. No se modificó
  `ProcessAutomaticIrrigations` ni el registro de su tarea.

## Concurrencia y límites

Las cuatro entradas públicas de sincronización en Company ahora adquieren el mismo
bloqueo/transacción, incluidas las llamadas de controladores y login. Las cascadas
anidadas reutilizan el contexto y el plan; lo liberan también ante excepciones.
Las confirmaciones Pro existentes ya bloquean la empresa, por lo que no pueden
intercalar la inserción de un periodo con esta sincronización en MySQL/InnoDB.
Las sincronizaciones parciales de controladores no adelantan la huella global: el
comando sigue debiendo aplicar todas las categorías cuando cambia un periodo.

Riesgo residual: las operaciones CRUD de recursos, sus comprobaciones de cupo y
escrituras SQL administrativas no están todas cubiertas por este bloqueo. El cambio
coordina sincronizaciones, no convierte todos los CRUD en transacciones de empresa.
Cambios de recursos con la misma huella se atienden por los controles/sincronizaciones
existentes; para corregir banderas alteradas manualmente existe `--force`. No se
añadieron observers generales ni se modificaron permisos o cupos ajenos al alcance.
Una transacción externa que ya abrió una lectura consistente antes de adquirir el
bloqueo puede conservar una vista anterior bajo REPEATABLE READ; evitar envolver
estas entradas en transacciones largas con lecturas previas. Los métodos existentes
limpian y reaplican banderas: consumidores con lecturas sucias no están soportados.

Los procesos concurrentes esperan por empresa; los lotes no crean una transacción
global. Validar tiempos y planes de consultas con volumen real antes de considerar
índices adicionales. SQLite no prueba bloqueos InnoDB ni la concurrencia real.

`withoutOverlapping()` requiere un almacén de caché persistente compatible con
mutexes. En varios nodos se necesita caché compartida; valorar `onOneServer()` al
configurar esa infraestructura. El mutex del evento usa la caducidad predeterminada
de Laravel (24 horas); tras una terminación abrupta, diagnosticar y liberar solo el
mutex de este evento si procede. No limpiar globalmente los mutexes del Scheduler
ni los del riego. Invocaciones manuales también quedan protegidas por el bloqueo de
empresa y la huella, aunque no usan el mutex del Scheduler.

## Operación posterior en la base de pruebas

No se ejecutaron migraciones ni comandos de escritura en MySQL. No se modificó
`.env` ni `lumatek_actual`. No hace falta modificar código para usar
`lumatek_pruebas_migracion`.

Cuando exista autorización específica, el operador deberá seleccionar esa base
mediante una configuración aislada o variables del proceso, comprobar la conexión
resuelta (incluidas URL y configuración cacheada) y sus permisos antes de ejecutar
la migración `2026_10_22_010000_create_subscription_restriction_states_table.php`.
Las migraciones previas de contratos/cotizaciones deben estar aplicadas. No usar
`migrate:fresh`, `db:wipe`, ni comandos de sincronización contra `lumatek_actual`.

Después de migrar en ese entorno autorizado:

```text
php artisan subscriptions:sync-restrictions --batch=100
php artisan subscriptions:sync-restrictions --company=123
php artisan subscriptions:sync-restrictions --company=123 --force
```

El último ejemplo reaplica las reglas aunque la huella no cambie. Los comandos
escriben banderas y metadatos: no son verificaciones de solo lectura.

Queda pendiente validar en MySQL desechable: dos comandos simultáneos; comando
frente a sincronización de controlador y confirmación Pro; fallo parcial y
recuperación; suspensión concurrente; vistas consistentes de transacciones; carga
de lectura y duración de bloqueos. Las pruebas locales usan exclusivamente SQLite
`:memory:` mediante `UsesInMemoryDatabase`, que rechaza conexiones físicas/MySQL.
