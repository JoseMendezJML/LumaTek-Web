# Base de plataforma

Las rutas web `/platform` y `/platform/logout` requieren sesion, cuenta activa
(y credenciales vigentes) y el rol exacto `superadmin`. No dependen del plan de
una empresa. El login existente redirige este rol a `/platform`.

## Cuentas sin empresa

La migracion `2026_10_21_000000_allow_platform_users_without_company.php` permite
`users.company_id = NULL`, conserva los valores existentes y la clave foranea.
El modelo exige empresa para los otros roles y no permite que una cuenta sin
empresa sea propietaria. No convierte ni desvincula cuentas existentes.
Los modulos empresariales siguen usando `plan.access`: una cuenta sin empresa
recibe 403, aunque sea superadmin. No se amplian consultas entre empresas.
El rollback falla de forma explicita si hay cuentas sin empresa; nunca borra
cuentas ni les asigna una empresa artificial.

## Primera cuenta

Tras aplicar las migraciones mediante el procedimiento autorizado y disponer
del rol `superadmin`, un operador con acceso confiable al servidor puede ejecutar:

```sh
php artisan platform:create-superadmin
```

El comando solicita nombre, email, password y confirmacion oculta; requiere al
menos 16 caracteres con mayusculas, minusculas, numero y simbolo. No acepta la
password por argumentos, no tiene valores fijos y rechaza ejecucion no interactiva.
La confirmacion final exige que el operador haya verificado la titularidad del
correo; por eso la cuenta queda verificada para el login existente. No envia
credenciales por correo ni crea empresas o suscripciones.

Solo funciona si no existe ningun superadmin, incluso inactivo. No promueve
usuarios existentes ni sustituye cuentas. Bloquea la fila del rol y vuelve a
comprobar la ausencia de superadmins dentro de una transaccion. La recuperacion
posterior usa el flujo de password existente; este comando no es una herramienta
de recuperacion ni de alta masiva. El registro publico siempre crea un propietario
`company_admin`, ignorando cualquier rol proporcionado por el cliente.

No se ha ejecutado el comando contra ninguna base real. Las migraciones se han
probado exclusivamente en SQLite `:memory:`. La aplicacion de la migracion en
MySQL requiere su validacion y autorizacion por separado. La comprobacion de
empresa/rol del modelo no protege escrituras SQL directas: esas operaciones deben
mantener las mismas invariantes.

### Procedimiento en hosting

1. Un operador autorizado debe conectarse por SSH a una terminal interactiva
   privada, sin grabación de sesiones ni captura de entradas. Si el hosting no
   ofrece una terminal que pueda ocultar contraseñas, no se debe ejecutar el alta
   mediante un formulario web, una tarea cron, una tubería ni un script automático.
2. Desde la raíz de la aplicación desplegada, comprobar mediante el procedimiento
   de despliegue autorizado que se utiliza PHP 8.3 o superior, que el esquema está
   actualizado y que existe el rol `superadmin`. Debe estar aplicada la migración
   existente `2026_10_21_000000_allow_platform_users_without_company.php`.
   Este comando no ejecuta migraciones, no crea roles y no requiere cambios en `.env`.
3. Ejecutar únicamente `php artisan platform:create-superadmin`, usando el binario
   PHP adecuado del hosting si hay varias versiones. No proporcionar contraseñas
   en argumentos, variables de entorno o archivos. El comando rechaza `--no-interaction`.
4. Introducir nombre y correo del operador designado. El correo se normaliza a
   minúsculas y se comprueba sin distinguir mayúsculas para evitar duplicados.
   Introducir la contraseña oculta y repetirla. Se conserva el mínimo de 16
   caracteres para esta cuenta privilegiada, con las mismas exigencias de
   mayúsculas, minúsculas, números y símbolos que utiliza LumaTek.
5. Verificar por un medio confiable que el correo mostrado pertenece al operador
   autorizado. Solo entonces responder afirmativamente a la confirmación final.
   La opción predeterminada es **No**: cancelar no crea ni verifica ninguna cuenta.
   La confirmación es una declaración del operador; el comando no envía un enlace
   ni comprueba técnicamente la propiedad del buzón.
6. Tras el mensaje de éxito, abrir `/login` mediante HTTPS e iniciar sesión con las
   credenciales elegidas. La cuenta activa accederá a `/platform`, con
   `company_id = null`, sin condición de propietario y sin empresa o suscripción
   creadas por el comando. Conservar la contraseña en el gestor de contraseñas
   autorizado, fuera del repositorio y de los registros del despliegue.

Si ya existe cualquier superadministrador, incluso inactivo o asociado a una
empresa antigua, el comando se detiene. Tampoco convierte usuarios existentes ni
restablece sus contraseñas. Si falta el rol, detener el procedimiento y revisar la
preparación del hosting por separado; no improvisar cambios SQL ni volver a cargar
datos de ejemplo en producción.

### Fallos y concurrencia

La creación y verificación se guardan en una transacción. Se bloquea la fila del
rol `superadmin` y se vuelven a comprobar el rol, los superadministradores
existentes y el correo después de la interacción. El índice único existente de
`users.email` protege adicionalmente frente a una inserción simultánea del mismo
correo. Las ejecuciones de este comando se serializan por la fila del rol en
MySQL/InnoDB; no existe una restricción SQL global de un único superadministrador
para escrituras manuales o herramientas ajenas al comando.

Un error de persistencia devuelve un código de salida distinto de cero y un
mensaje genérico, sin imprimir ni registrar la excepción SQL, la contraseña ni su
hash. Antes de reintentar, un operador autorizado debe comprobar si la cuenta ya
existe; ante una pérdida de conexión durante la confirmación de la transacción,
el cliente puede desconocer si el servidor llegó a confirmar la escritura.

Las pruebas automatizadas utilizan exclusivamente SQLite `:memory:` y cubren
contraseñas ocultas sin alternativa visible, confirmación con **No** por defecto,
validación, ausencia de rol, duplicados, cambios durante los prompts, rollback y
ausencia de credenciales en la salida y los registros de la aplicación.
Queda pendiente verificar en un entorno MySQL/InnoDB de pruebas autorizado que
dos terminales simultáneas crean una sola cuenta, que un registro concurrente del
mismo correo no se promueve y que un fallo revierte la creación. SQLite no valida
el bloqueo real de filas de MySQL. No ejecutar estas pruebas sobre producción.

## Empresas

`/platform/companies` permite buscar por empresa/propietario, filtrar por estado
y plan efectivo y paginar resultados. El detalle admite suspender/reactivar con
confirmacion validada en backend, CSRF y rol superadmin.

Se reutiliza `companies.status`; no se necesitan migraciones adicionales.
La suspension actualiza estado y revoca `auth_version`/remember-me de las cuentas
empresariales de esa empresa dentro de una transaccion. No modifica sus estados
individuales, datos ni suscripciones. Las cuentas superadmin conservan sus
credenciales de plataforma, incluso si tienen una empresa asociada antigua.
El login y las rutas empresariales rechazan empresas suspendidas; `/platform`
permanece independiente. Reactivar tambien revoca las credenciales empresariales,
incluso si la empresa ya estaba inactiva antes de implementar la suspension.
No restaura credenciales antiguas y sigue aplicando `plan.access` e inactividad
individual. Solicitar el mismo estado actual no vuelve a revocar credenciales.
