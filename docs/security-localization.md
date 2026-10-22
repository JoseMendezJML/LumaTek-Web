# Seguridad de acceso y mensajes en español

El restablecimiento exitoso de contraseña guarda la nueva contraseña, una nueva
`auth_version` y un nuevo token de recuerdo en la misma actualización del usuario.
El middleware existente rechaza los JWT y las sesiones web anteriores en su
siguiente solicitud protegida. Un JWT actualizado mediante refresh conserva su
versión anterior y tampoco recupera el acceso. No se eliminan sesiones de otros
usuarios ni se depende de que el almacenamiento de sesiones sea una tabla SQL.
El usuario puede volver a iniciar sesión con su nueva contraseña o con su cuenta
Google correctamente vinculada. Los intentos de restablecimiento fallidos no
revocan credenciales.

Los flujos Google web y móvil exigen que el correo actual de la cuenta coincida
con el correo verificado por Google, sin distinguir mayúsculas. Si cambió el correo
local y dejó de coincidir, se rechaza ese inicio de sesión: no se verifica el nuevo
correo, no se reasigna la identidad Google y no se crea otra cuenta. El usuario
debe iniciar sesión con correo y contraseña. Antes de guardar una vinculación se
vuelven a consultar y comprobar correo e identidad Google con bloqueo de la fila
del usuario. No se cambian roles, empresa ni reglas de acceso.

## Localización

`config/app.php` establece `es` como idioma y respaldo de LumaTek, incluso si una
instalación anterior conserva `APP_LOCALE=en`. No se necesita modificar `.env`.
Los archivos `lang/es` contienen validación, atributos legibles, autenticación,
recuperación, paginación, errores HTTP y mensajes contractuales públicos.
`lang/es.json` traduce las frases del framework utilizadas en notificaciones,
correos, paginación y páginas de error.

El manejador de excepciones localiza los mensajes JSON públicos conservando los
estados HTTP, claves y cabeceras. Los errores 404 y 405 muestran mensajes genéricos
en español en lugar de detalles de modelos o rutas. No se traducen identificadores,
códigos, valores de enumeraciones ni diagnósticos internos. Se conservan los
mensajes personalizados existentes que ya estaban en español. Los cambios en
`SubscriptionContractService` se limitan a mensajes de error; no cambian reglas,
precios, fechas, condiciones ni contratos históricos.

## Verificación y despliegue posterior

Las pruebas de base de datos utilizan exclusivamente SQLite `:memory:` y el rasgo
`UsesInMemoryDatabase`, que comprueba el aislamiento antes de migrar y elimina las
conexiones reales de la configuración de pruebas. Google y Anthropic se simulan en
sus pruebas. Los correos se renderizan o usan el transporte de pruebas, sin envío
externo. No hay migraciones nuevas para estas correcciones; se requiere el esquema
actual de `develop`, incluida la migración existente de `auth_version`.

Antes de desplegar, el operador debe reconstruir la caché de configuración con el
código actualizado, establecer `APP_DEBUG=false`, comprobar HTTPS, la URL pública y
el callback autorizado de Google, y validar el transporte y remitente de correo
para recuperación y verificación. Estas operaciones requieren autorización y no
forman parte de la ejecución local de las pruebas.

SQLite no verifica los bloqueos ni la concurrencia de MySQL: queda pendiente una
comprobación controlada de cambios concurrentes de correo/vinculación en un entorno
MySQL de pruebas autorizado. Las pruebas locales cubren la relectura de una cuenta
cambiada entre la consulta inicial y la vinculación. La comprobación real de OAuth,
la entrega de correos y las sesiones del hosting debe realizarse posteriormente.
Luma, su proveedor Anthropic, el Scheduler y el riego no cambian.
