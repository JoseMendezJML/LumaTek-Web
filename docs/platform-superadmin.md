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
permanece independiente. Reactivar solo cambia el estado de empresa: no restaura
credenciales antiguas y sigue aplicando `plan.access` e inactividad individual.
