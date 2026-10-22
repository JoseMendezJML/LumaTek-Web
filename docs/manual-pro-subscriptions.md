# Contratación y renovación manual de Pro: backend

La aplicación automática de restricciones al cambiar el periodo efectivo se describe
en [subscription-restriction-sync.md](subscription-restriction-sync.md).

La confirmación registra que un superadmin verificó un pago externo por el importe
cotizado. LumaTek no procesa el pago, no contacta a Mercado Pago y no ejecuta
cobros ni renovaciones automáticas.

## API JWT

Ambas rutas usan `auth:api` y `active.user`, incluida la revocación de credenciales
existente. No requieren tener Pro para acceder.

- `POST /api/company/pro/quotes`: `billing_period` (`monthly`/`yearly`) y
  `request_key` (máximo 100 caracteres). Exige propietario activo con rol
  `company_admin`, perteneciente a una empresa activa. Empresa, plan, importe,
  moneda y límites provienen exclusivamente del servidor. Otros campos del cliente
  no se utilizan. Devuelve `quote`; no crea una suscripción.
- `POST /api/platform/pro/quotes/{quote}/confirm`: `medium` (máximo 100) y
  `reference` (máximo 255), ambos obligatorios. Exige superadmin activo. Devuelve
  `confirmation` con `contract`. El administrador debe verificar externamente el
  importe completo y la moneda de la cotización antes de invocar esta operación.
  No hay un estado o identificador de pago de Mercado Pago.

Las respuestas exitosas usan HTTP 200, también al reintentar. Una clave de solicitud
identifica una única cotización por empresa y solicitante/modalidad; reutilizarla
con otra modalidad o solicitante devuelve 422. La misma clave devuelve la cotización
original aunque esté vencida: para obtener condiciones nuevas se requiere otra
clave. Una confirmación nueva vencida devuelve 422. Un reintento de confirmación
exitosa devuelve la confirmación original, sin cambiar actor, fecha, medio,
referencia o periodo, incluso después de las 48 horas. El actor debe seguir siendo
un superadmin activo. Los permisos insuficientes devuelven 403.

## Persistencia e invariantes

La migración `2026_10_21_030000_create_manual_subscription_quotes.php` agrega
`subscription_quotes` y `subscription_manual_confirmations`. No modifica registros
ni columnas históricos. Las referencias usan RESTRICT. Cada confirmación tiene
una cotización única y un contrato único. La cotización conserva importe DECIMAL
(nunca float para calcular dinero), MXN, modalidad, nombre/slug, cinco límites,
condiciones versionadas y fechas. La precisión temporal es de segundos.

Las cotizaciones y confirmaciones son inmutables mediante el constructor de
consultas y triggers de UPDATE, DELETE y colisiones de INSERT/REPLACE en SQLite y
MySQL. No se cambia su estado: el vencimiento se deriva del reloj y la confirmación
de la relación persistida. La migración rechaza rollback si existen registros.

`SubscriptionContractService::quote()` congela el catálogo bajo bloqueo del plan.
Pro desactivado impide nuevas cotizaciones, pero no invalida las ya emitidas.
Precio anual NULL significa no configurado. El precio mensual cero del catálogo
se conserva como tal; no se infieren precios ni descuentos.

`confirm()` bloquea empresa, actor y cotización, vuelve a validar el estado y crea
suscripción, contrato y confirmación en una transacción. La captura del contrato
relee la cotización persistida y verifica su empresa, plan, modalidad y clave;
no copia condiciones del catálogo actual ni de un objeto modificado en memoria.
Las condiciones adicionales permanecen en la cotización inmutable vinculada al
contrato mediante la confirmación. Se conservan las protecciones históricas y la
verificación de integridad existentes.

El inicio es el máximo entre la confirmación y el último fin contratado Pro,
incluidos periodos futuros e históricos, independientemente del estado mutable de
la suscripción. Esta decisión es conservadora: cancelar un periodo no libera sus
días para otra contratación. Cualquier periodo Pro sin fin bloquea solicitudes y
confirmaciones nuevas. Una renovación usa la modalidad recién cotizada; no altera
el periodo anterior. Mes y año usan operaciones calendario sin desbordamiento:
31 enero → 28/29 febrero; 29 febrero + un año → 28 febrero. Se usa la zona horaria
configurada de la aplicación y deben mantenerse coherentes aplicación y conexión.

Los intervalos son [inicio, fin). El modelo existente resuelve automáticamente el
periodo efectivo por fecha, sin activar anticipadamente periodos futuros. No se
necesita un job de renovación. `auto_renew` siempre es false.

## Validación y concurrencia pendiente en MySQL

Las pruebas automatizadas usan el trait `UsesInMemoryDatabase`: comprueba entorno
testing, ausencia de configuración cacheada y SQLite `:memory:`, elimina otras
conexiones configuradas y verifica que SQLite no use archivos. No ejecutar estas
pruebas sobre la base de desarrollo. Cubren permisos JWT, aislamiento, catálogo
exclusivo, vencimiento exacto, congelación, desactivación, cambios de modalidad,
fechas calendario, idempotencia, rollback, unicidad e inmutabilidad.

SQLite no demuestra el comportamiento de bloqueos InnoDB. En un MySQL desechable,
con autorización explícita y dos conexiones/procesos independientes, quedan por
verificar:

1. Dos solicitudes con la misma empresa/clave: una sola cotización y mismo ID.
2. Dos confirmaciones de la misma cotización: una suscripción, un contrato y una
   confirmación; ambos clientes reciben el mismo resultado.
3. Dos cotizaciones distintas de una empresa confirmadas simultáneamente: periodos
   consecutivos, sin superposición, incluso con diferentes modalidades.
4. Cotización concurrente con edición/desactivación del catálogo: copia consistente
   completa anterior o rechazo; ninguna mezcla de condiciones.
5. Suspensión concurrente de empresa/actor y confirmación, reintentos por deadlock
   (máximo tres transacciones) y expiración mientras se espera un bloqueo.
6. Fallo de inserción de confirmación: rollback de contrato y suscripción; comprobar
   índices, claves RESTRICT y triggers de MySQL, incluidos intentos REPLACE/upsert.

Todas las vías de creación de periodos deben respetar el bloqueo de empresa y el
servicio; una escritura SQL administrativa puede eludir el control de intervalos.
La referencia externa es evidencia textual, no conciliación bancaria ni garantía
de unicidad de un pago entre cotizaciones. La comprobación del pago es humana.

MySQL requiere privilegio TRIGGER y realiza commits implícitos de DDL. Si falla la
migración parcialmente, revisar el esquema antes de reintentar; no borrar tablas
con historia ni asumir rollback transaccional del DDL. No se ha ejecutado esta
migración en MySQL.
