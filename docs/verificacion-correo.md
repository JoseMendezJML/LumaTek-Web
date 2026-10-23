# Verificación de correo

## Flujo y seguridad

El registro crea empresa, administrador propietario y suscripción gratuita dentro de una transacción. Solo después del commit se intenta la notificación estándar `VerifyEmail` de Laravel. La respuesta sigue siendo 201 si la cuenta se creó, incluso cuando falla el transporte: `verification_status` será `pending` o `delivery_failed`, y `verification_url` abre la ayuda/reenvío. No se emite sesión ni JWT al registrar o verificar. «Envío solicitado» no significa entrega al buzón.

`verification.verify` conserva su ruta `/api/email/verify/{id}/{hash}`, firma absoluta temporal y throttle de seis solicitudes por minuto. Exige firma válida y hash del correo actual. La firma incluye dominio, esquema, puerto y parámetros: no se debe transformar el enlace para hacerlo funcionar. Caduca a los 60 minutos por defecto (`auth.verification.expire` de Laravel). No se cambió la generación estándar del enlace.

Con `Accept: application/json`, devuelve JSON; una navegación normal presenta una vista LumaTek con botón de login. Los estados son `verified` (200), `already_verified` (200), `expired` (410) e `invalid` (403). Solo se identifica como vencido un enlace cuya firma auténtica sea correcta. El resto de fallos comparte un mensaje genérico sin datos del usuario. La respuesta prohíbe caché y envío de Referer, y el evento `Verified` se emite una sola vez bajo bloqueo de la fila.

El uso repetido de un enlace todavía válido es idempotente: informa que el correo ya estaba verificado, sin cambiar fecha, rol, empresa, estado o credenciales. El reenvío no revoca enlaces anteriores; siguen sujetos a firma, vencimiento y coincidencia del correo actual, como en el mecanismo de Laravel. Una URL conocida no puede autorizar un correo distinto.

La página `/email/verification` está disponible desde login y registro. El formulario pide correo y contraseña y llama a `POST /api/email/verification-notification`. Es una prueba de conocimiento de la contraseña, no un login ni una excepción a la firma. La contraseña solo se envía en el cuerpo de la petición; no se conserva en almacenamiento del navegador ni se incluye en URLs. No compartirla ni habilitar captura de cuerpos HTTP en diagnósticos, analítica o registros del proxy.

Para correos inexistentes, contraseñas incorrectas, cuentas verificadas, cuentas inactivas o empresas inactivas, el reenvío devuelve el mismo 202 y mensaje genérico, sin enviar. Solo quien acredita la contraseña de una cuenta habilitada pendiente puede recibir `delivery_failed` (503) si falla el transporte. Los errores internos no se exponen ni se registran completos: se registra únicamente el identificador interno y un código de fallo. Esto impide enumerar cuentas solo conociendo una dirección de correo.

Límites independientes:

| Acción | Límite |
| --- | --- |
| Registro público de empresa | 2 intentos/minuto y 5/hora por IP, incluidos datos inválidos |
| Solicitud de reenvío | 10 intentos/minuto por IP y 5/hora por correo normalizado, exista o no |
| Notificación inicial + reenvíos | 3 intentos de envío/hora por usuario, incluidos fallos de transporte |
| Apertura del enlace firmado | 6/minuto por IP (middleware existente) |

Los límites HTTP responden 429 con `Retry-After`. Alcanzar el límite interno de envíos conserva la respuesta pública genérica. Login, creación/ingreso con Google y recuperación de contraseña mantienen sus límites y reglas existentes; Google con correo verificado no requiere otra notificación. La verificación de una cuenta inactiva mediante un enlace autorizado no la reactiva ni elimina restricciones de empresa, rol, plan o sesión. No se modificaron esos mecanismos.

## Configuración pendiente del hosting

Configurar los valores en el gestor de secretos/variables del hosting, fuera del repositorio. No se editó `.env` ni se incluyeron valores reales. Los nombres siguientes están conectados a `config/mail.php`, `config/services.php`, `config/cache.php` y `config/app.php` del proyecto.

| Variable | Uso |
| --- | --- |
| `APP_ENV` | `production` en producción |
| `APP_DEBUG` | `false`, para evitar revelar detalles internos |
| `APP_URL` | Origen HTTPS público canónico, por ejemplo `https://app.example.com`, con puerto si corresponde |
| `APP_KEY` | Clave existente de la aplicación; conservarla estable y secreta. No regenerarla para arreglar correo o firmas |
| `TRUSTED_PROXIES` | IPs concretas del proxy de producción, si termina TLS antes de Laravel. Debe enviar `X-Forwarded-Proto`/host correctos; no confiar indiscriminadamente en cualquier cliente |
| `MAIL_MAILER` | `smtp`, o proveedor configurado (`postmark`, `resend`, `ses`) con su transporte disponible |
| `MAIL_FROM_ADDRESS` | Remitente autorizado de un dominio validado por el proveedor |
| `MAIL_FROM_NAME` | Nombre visible, por ejemplo `LumaTek` |
| `MAIL_HOST`, `MAIL_PORT` | Host SMTP y puerto indicados por el proveedor |
| `MAIL_SCHEME` | `smtp` o `smtps`, según las condiciones TLS del proveedor; no deshabilitar validación de certificados |
| `MAIL_USERNAME`, `MAIL_PASSWORD` | Autenticación SMTP; guardar exclusivamente como secretos del hosting |
| `MAIL_EHLO_DOMAIN` | Dominio de identificación SMTP si el proveedor lo requiere; por defecto se deriva de `APP_URL` |
| `MAIL_URL` | Alternativa de configuración SMTP por URL; puede contener secretos. Evitar definirla junto con ajustes contradictorios |
| `POSTMARK_API_KEY` / `RESEND_API_KEY` | Solo para el proveedor elegido, si su transporte y SDK están instalados |
| `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION` | Solo para SES, con permisos mínimos y dominio/remitente habilitado |
| `CACHE_STORE` | Almacén persistente y compartido para que los límites sobrevivan a peticiones y funcionen entre procesos/servidores. No usar `array` ni `null` en producción |

Verificar remitente/dominio y los registros SPF, DKIM y DMARC conforme a las instrucciones del proveedor, restricciones de sandbox/destinatarios y salida de red SMTP/API desde el hosting. No usar `MAIL_MAILER=log` o `array` para entregas reales: no envían al destinatario y el primero puede escribir enlaces de autorización en logs. No se añadieron paquetes ni se habilitó un proveedor nuevo.

Las notificaciones siguen siendo síncronas: no dependen de un worker. El timeout y la conectividad del transporte deberán revisarse en el hosting; no se prometen reintentos automáticos. Tras configurar valores, refrescar la caché de configuración y reiniciar procesos persistentes mediante el procedimiento autorizado del hosting. No se ejecutaron esas operaciones en esta tarea.

Validar manualmente con una cuenta de prueba autorizada: recepción, carpeta spam, dominio y HTTPS del enlace, verificación en navegador y API, enlace vencido/alterado, reenvío tras caída del transporte, 429 y `Retry-After`, login posterior y acceso Google existente. No se enviaron correos reales ni se accedió a servicios externos o bases MySQL para esta implementación.

## Pruebas

`tests/Feature/EmailVerificationTest.php` usa SQLite `:memory:`, `Notification::fake()`, transporte simulado que falla y URLs temporales firmadas reales. Comprueba registro transaccional, notificación, estados HTML/JSON, expiración, alteración, correo cambiado, reuso, reenvío con prueba de contraseña, respuestas genéricas, límites por IP/correo/usuario, fallo y reintento, y login antes/después de verificar. La regresión de Google comprueba que autenticar un correo verificado no envíe una segunda notificación.

Ejecutar sin servicios externos:

```text
php vendor/phpunit/phpunit/phpunit
```

Resultado de esta implementación: **1.339 pruebas / 20.904 aserciones aprobadas** en la suite completa, incluidas 19 pruebas nuevas de verificación y la regresión Google ampliada. La sintaxis PHP y `git diff --check` también pasaron. No se ejecutaron migraciones ni operaciones en MySQL, ni envíos reales. La recepción efectiva del correo y la configuración del hosting quedan pendientes de un ensayo autorizado.
