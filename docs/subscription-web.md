# Interfaces web de suscripciones Pro

Las interfaces usan sesiones Laravel y formularios Blade, sin invocar el API JWT
desde el navegador. No procesan pagos ni ejecutan renovaciones automáticas.
Requieren el backend y las migraciones de contratos/cotizaciones ya existentes;
esta implementación no agrega migraciones ni cambia reglas contractuales.

## Rutas

| Método y ruta | Nombre | Acceso |
| --- | --- | --- |
| GET `/subscription` | `subscription.index` | Propietario activo de empresa activa |
| POST `/subscription/quotes` | `subscription.quotes.store` | Mismo permiso y protección CSRF |
| GET `/platform/subscriptions` | `platform.subscriptions.index` | Superadmin activo |
| GET `/platform/subscriptions/{quote}` | `platform.subscriptions.show` | Superadmin activo |
| POST `/platform/subscriptions/{quote}/confirm` | `platform.subscriptions.confirm` | Superadmin activo y protección CSRF |

`company.owner` exige `is_company_owner`, rol `company_admin` y empresa activa,
en concordancia con `SubscriptionContractService::quote()`. `active.user` mantiene
la verificación de estado y revocación de credenciales. La gestión de suscripción
no depende de `plan.access`: el propietario puede renovar después del vencimiento.
Los controles de los otros módulos siguen intactos.

## Pantallas y datos

“Mi suscripción” usa `Company::effectivePlan()` y los contratos del periodo actual
y futuros. Presenta precios del catálogo por separado de precios contratados. Los
periodos futuros listados ya están confirmados; una cotización pendiente no es una
renovación programada. Se conservan fechas desconocidas como desconocidas. Las
fechas muestran la zona horaria configurada de la aplicación.

El historial de cotizaciones se filtra por `company_id` de la identidad autenticada
y por `requested_by` del propietario autenticado. Otro propietario de la misma
empresa no obtiene sus solicitudes. Ambos propietarios sí pueden consultar los
periodos contratados de su empresa. Se paginan cotizaciones (10) y periodos futuros
(10, mediante `renewals_page`) por separado.

“Solicitudes Pro” pagina 15 cotizaciones y precarga empresa, solicitante,
confirmación y contrato. El detalle incluye condiciones congeladas, medio,
referencia, administrador verificador y contrato. Las relaciones agregadas a los
modelos son de lectura; no se cambian las escrituras ni los endpoints JWT.

El estado presentado se deriva al cargar la página: confirmada si existe la
confirmación; vencida si no hay confirmación y `now >= expires_at`; pendiente en
otro caso. La confirmación prevalece aunque después venza la cotización. No hay
otro campo de estado ni se actualiza la cotización. Las condiciones actuales
`manual-pro-v1` se presentan en lenguaje natural; una versión desconocida solicita
revisión por soporte en vez de atribuirle las condiciones de otra versión.

Los importes se presentan desde cadenas DECIMAL sin cálculos con float. Un precio
anual NULL se muestra “Sin configurar”, no como cero. Pro desactivado impide nuevas
solicitudes; una cotización vigente previamente emitida continúa siendo confirmable
según el servicio, conservando el precio y los límites originales.

## Escrituras, permisos e idempotencia

El formulario del propietario solo utiliza `billing_period` y un UUID de solicitud.
Empresa, plan, importe, moneda, límites y fechas nunca se toman del navegador.
El UUID generado al mostrar el formulario se conserva tras errores de validación;
repetir el POST con la misma modalidad y UUID recupera la misma cotización.
Un formulario nuevo genera otra clave para una nueva solicitud. La confirmación
usa la idempotencia del servicio por cotización, incluida después de su expiración.

El formulario del administrador exige medio, referencia y `payment_verified=1`
aceptado explícitamente, validado en el servidor. Es una declaración de que el
administrador verificó fuera de LumaTek el importe completo y su moneda; no prueba
el movimiento bancario ni concilia referencias automáticamente. Una confirmación
exitosa redirige al detalle, y una solicitud de cotización redirige al historial
(Post/Redirect/Get). Las validaciones devuelven errores claros y conservan entradas;
la casilla de verificación no se marca automáticamente al volver a mostrar el formulario.

Las acciones de plataforma reciben identificadores escalares. `platform.admin`
se ejecuta antes de consultar una cotización, evitando filtrar su existencia con
respuestas 403/404. Las rutas de propietario no reciben identificadores de otras
cotizaciones. Todo contenido dinámico se escapa con Blade; no se confía en roles,
identificadores ni precios guardados en sessionStorage. La navegación muestra
“Mi suscripción” solo a propietarios y “Solicitudes Pro” dentro de plataforma,
separada de la administración del catálogo de planes.

## Contacto para instrucciones de pago

Las cotizaciones pendientes y vigentes incluyen «Solicitar instrucciones por correo».
El enlace `mailto:` prepara un mensaje formal con ID de cotización, empresa actual
obtenida del servidor, modalidad, importe DECIMAL congelado y moneda. No incorpora
contraseñas, JWT, referencias bancarias, datos de sesión ni condiciones internas.
Asunto y cuerpo se codifican con RFC 3986 (UTF-8, espacios como `%20` y saltos CRLF).
No se aceptan importes o identidades del navegador para generar el enlace.

El destinatario se centraliza en `config/subscriptions.php`, clave
`subscriptions.quote_contact_email`, mediante `PRO_QUOTE_CONTACT_EMAIL`. El valor
inicial de pruebas es `administracion.lumatek@gmail.com`. Un operador podrá cambiar
esa variable en el entorno autorizado; esta implementación no modifica `.env`.
Asignar una cadena vacía desactiva los enlaces. Si el valor está ausente en la
configuración, vacío o no es una dirección válida, se muestran instrucciones para
contactar a administración por el canal habitual, sin generar un enlace inválido.
Si se utiliza configuración cacheada, su actualización posterior requiere el
procedimiento habitual de reconstrucción en ese entorno, fuera de este cambio local.

El usuario debe tener una aplicación de correo o un manejador `mailto:` configurado
en su escritorio o dispositivo móvil. Debe revisar y enviar el mensaje desde ella:
abrir el enlace no envía correos, confirma pagos, reserva precios adicionales ni
activa Pro. No se usa SMTP, colas ni servicios externos. La página no puede saber
si el usuario envió el mensaje. Una pestaña abierta puede quedar desactualizada;
el backend conserva la validación de vigencia al confirmar. Las cotizaciones
confirmadas y vencidas no muestran este contacto.

## Validación y límites

`SubscriptionWebTest` usa `UsesInMemoryDatabase`, que verifica SQLite `:memory:` y
elimina otras conexiones configuradas. Cubre permisos, sesiones revocadas, JWT sin
sesión, aislamiento por empresa y propietario, paginación, consultas sin crecimiento
por fila, estados, expiración, contratos futuros, límites históricos, manipulación
de payload, XSS e idempotencia. La prueba CSRF desactiva únicamente el bypass de
pruebas de `PreventRequestForgery`, manteniendo el entorno testing y la base
aislada: comprueba HTTP 419 sin token/con token incorrecto y éxito con token válido
en ambas rutas de escritura.

Comando de pruebas específico:

```text
php vendor/phpunit/phpunit/phpunit --configuration phpunit.xml tests/Feature/SubscriptionWebTest.php --do-not-cache-result
```

Las pantallas son renderizadas por Blade y usan CSS responsive y componentes
HTML nativos (`details`, tablas y formularios), sin dependencias nuevas ni build
adicional. Una página abierta puede quedar desactualizada: el backend vuelve a
validar al enviar y rechaza nuevas confirmaciones vencidas. Las pruebas aisladas
no sustituyen la comprobación visual en navegadores ni las pruebas concurrentes
InnoDB pendientes del backend.

No se ejecutaron migraciones ni escrituras MySQL, ni se modificaron `.env`, el
Scheduler, el riego automático, la sincronización de restricciones o los registros
históricos. No es necesario desplegar ni cambiar datos para revisar el código local.
