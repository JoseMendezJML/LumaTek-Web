# LUM-61: compatibilidad con las pruebas del PR #28

## Alcance de la revisión

Se revisó `tests/Feature/GreenhousePermissionsTest.php` mediante `git show`, sin
integrar ni cambiar la rama del PR #28. Las referencias locales
`feature/permisos-empleado` y `origin/feature/permisos-empleado` apuntaban a
`a21253c3158ec1554dfca96f751da30fd044716d`, cuyo cambio restringe los controles
web de escritura de invernaderos a administradores de empresa activos.

La suite del PR contiene 58 escenarios. LUM-61 agrega una condición previa a los
permisos de invernaderos: solo una cuenta activa con credenciales vigentes puede
alcanzar una funcionalidad protegida. Los cambios descritos aquí deberán hacerse
al integrar ambas tareas; no se ha modificado el archivo de pruebas del PR.

## Cambios de comportamiento esperados

| Escenarios del PR | Cantidad | Resultado después de LUM-61 |
| --- | ---: | --- |
| Interfaz web, cuenta inactiva | 3 | Redirección al login y cierre de sesión, sin renderizar la página protegida. |
| Mutaciones API, cuenta inactiva | 15 | Se mantiene `403`; el rechazo procede del control central de actividad. |
| Interfaz web, cuenta activa | 3 | Se mantiene `200` y el DOM; se recomienda login real para preparar la marca de sesión. |
| Resto de escenarios | 37 | Conservar expectativas de permisos, aislamiento y autenticación. |

Los tres escenarios web inactivos son `employee inactive`,
`company_admin inactive` y `superadmin inactive`, generados por `webUserRoles()`.
Los quince escenarios API corresponden a esos tres roles multiplicados por las
cinco operaciones de `mutations()`: creación, PUT, PATCH, activación y
desactivación.

## Ajustes necesarios en las aserciones web

El método
`test_web_interface_exposes_management_controls_only_to_active_company_administrators()`
actualmente aplica `assertOk()` y aserciones de contenido a los seis casos de
`webUserRoles()`. Al integrar LUM-61 se debe separar el resultado de las cuentas
inactivas antes de comprobar el HTML:

- Para los tres casos inactivos, sustituir `assertOk()` por
  `assertRedirect(route('login'))` y comprobar
  `assertSessionHas('error', 'La cuenta se encuentra inactiva.')`.
- Comprobar `assertGuest('web')` y que se eliminó la clave de autenticación del
  guard web. Cuando se use una sesión real, comprobar además la eliminación de
  un dato marcador, el cambio del identificador de sesión y la regeneración del
  token CSRF, como en `InactiveAccountAccessTest`.
- No ejecutar para esos tres casos las aserciones `assertSee()` del listado,
  mensaje y `loadGreenhouses()`, ni el bucle de controles de administración. Una
  redirección no renderiza el módulo; su ausencia en una redirección no prueba
  autorización de la vista.
- Conservar `assertOk()` y todas las aserciones del DOM para los tres roles
  activos. Solo `company_admin active` debe recibir formulario, botones y
  manejadores de escritura; `employee active` y `superadmin active` conservan
  únicamente las opciones autorizadas de consulta.

Es posible mantener el proveedor y hacer una bifurcación por estado, o separar
las pruebas de cuentas activas e inactivas. La segunda opción expresa con mayor
claridad la diferencia entre autorización de controles y rechazo de sesiones.

## Ajustes necesarios en las aserciones API

`test_unauthorized_user_cannot_mutate_greenhouses()` usa
`unauthorizedMutations()` para 25 combinaciones. Sus quince combinaciones
inactivas ya esperan `assertForbidden()`, por lo que el código HTTP no cambia.
Añadir en esas combinaciones una comprobación del mensaje central:

```php
$response->assertForbidden()
    ->assertExactJson(['message' => 'La cuenta se encuentra inactiva.']);
```

Conservar en todos los casos el conteo de `greenhouses`, la comparación de sus
atributos originales y el conteo de `greenhouse_thresholds`. Esas aserciones
comprueban que el rechazo no dejó efectos. Los diez casos de roles activos no
autorizados mantienen el rechazo por permisos de invernaderos, sin exigirles el
mensaje de cuenta inactiva.

Si el JWT se emitió antes de desactivar y reactivar la cuenta, el rechazo
posterior a la reactivación corresponde a credenciales revocadas (`401`), no a
una cuenta inactiva (`403`). No reemplazar indiscriminadamente todos los `403`
del PR por `401`: autenticación vigente, actividad y permiso son comprobaciones
distintas. El ciclo de reactivación se cubre en las pruebas de LUM-61.

El rechazo por credenciales revocadas debe comprobar el mensaje
`Las credenciales han caducado. Inicia sesión nuevamente.`. El estado inactivo
mantiene `La cuenta se encuentra inactiva.` y `403`, incluso cuando la versión
de las credenciales ya no coincide.

## Preparación de credenciales y sesiones

El helper `authenticate()` ya usa `JWTAuth::fromUser($user)` y fuerza la
resolución del Bearer mediante el guard real. Mantener ese mecanismo:
`User::getJWTCustomClaims()` incorpora `auth_version`, y `config/jwt.php` lo
conserva en `persistent_claims` para que la renovación no pueda convertir un
token anterior en credenciales actuales. No crear tokens manualmente sin esos
claims ni desactivar middleware para acomodar la suite.

La versión se almacena en `users.auth_version`, una columna UUID nullable.
`User::authenticationVersion()` devuelve el UUID almacenado o la versión
inicial `'0'` si la columna es nula. En el evento `updating`, cualquier cambio
de `status` rota tanto `auth_version` como `remember_token` dentro del mismo
UPDATE: ocurre al desactivar y también al reactivar. Por ello, las credenciales
anteriores no recuperan validez tras la reactivación, ni mediante JWT ni
mediante la cookie de recordar sesión anterior.

El login web almacena la marca de sesión bajo
`User::AUTH_VERSION_SESSION_KEY`, cuyo valor es `auth_version_web`:

```php
[
    'user_id' => $user->id,
    'version' => $user->authenticationVersion(),
]
```

La prueba web del PR usa `actingAs(..., 'web')`, que omite el controlador de
login. Los JWT y las sesiones anteriores sin marca solo se aceptan mientras la
columna de la cuenta siga siendo nula (versión `'0'`). Esta compatibilidad
permite que los usuarios activos sin transiciones conserven su sesión; después
de la primera transición nunca se debe completar automáticamente una marca
ausente con la versión actual.

Preferir el login web real con la contraseña de la factory y reutilizar la
cookie emitida, como `loginWeb()` en `InactiveAccountAccessTest`. Como
alternativa para pruebas centradas exclusivamente en Blade, acompañar
`actingAs()` con `withSession()` y la estructura indicada. Así, la prueba no
depende de que la cuenta permanezca en la versión inicial. Para probar el
bloqueo, emitir primero la credencial con una cuenta activa y cambiar después
su estado mediante la operación administrativa; no fabricar una sesión válida
después de la desactivación.

Con las fixtures actuales del PR, las cuentas activas recién creadas tienen
`auth_version = null`; por compatibilidad inicial, `actingAs()` continúa
funcionando sin cambios. Preparar la marca es un refuerzo recomendado, no una
corrección obligatoria de esos tres casos. Las únicas expectativas existentes
que necesariamente dejan de cumplirse son los tres `200` web de inactivos;
los quince rechazos API admiten las aserciones adicionales descritas arriba.

## Migración y cambios de estado

La migración aditiva que incorpora `users.auth_version` debe aplicarse antes
de desplegar el código que consulta esa columna. En esta tarea solo se prepara
el archivo y se ejecuta bajo SQLite en memoria en las pruebas; no se aplica
sobre una base de datos real.

La administración actual modifica usuarios mediante `User->update()`, por lo
que dispara el evento de rotación. Las actualizaciones masivas mediante
`DB::table('users')->update(...)` o un UPDATE SQL directo omiten los eventos
del modelo: desactivar y reactivar por esas vías sin rotar explícitamente la
versión no garantiza la revocación. Lo mismo ocurre con actualizaciones
masivas del query builder de Eloquent. Los futuros importadores o procesos de
administración deben conservar la misma regla de transición. Los tests que
usan una actualización SQL directa para comprobar el estado inactivo no deben
considerarse prueba del ciclo completo de revocación.

La migración es reversible en el esquema, pero `down()` elimina también las
marcas de revocación. Reaplicarla deja las versiones en `null`: un JWT legado o
con versión inicial `'0'` todavía vigente podría recuperar acceso. Un rollback
operativo debe conservar la columna y sus marcas o coordinar la invalidación de
las credenciales antes de eliminar/recrear el campo. No ejecutar `down()` con el
código que depende de la columna activo. La prueba de reversibilidad utiliza
solo SQLite en memoria y no constituye un procedimiento seguro de rollback de
producción.

La compatibilidad inicial tampoco reconstruye cambios de estado anteriores al
despliegue. Las solicitudes ya autorizadas y en ejecución no se cancelan
retroactivamente; la comprobación se realiza al ingresar a cada solicitud.

## Verificación al integrar

1. Conservar las comprobaciones de aislamiento SQLite `:memory:` antes de abrir
   conexiones o migrar. Reforzar el aislamiento eliminando las otras conexiones
   temporales, como hace la suite de LUM-61.
2. Ejecutar juntas `GreenhousePermissionsTest`, `InactiveAccountAccessTest`
   y `AuthenticationVersionTest`
   bajo `phpunit.xml`, sin configuración cacheada y sin acceso a MySQL.
3. Verificar los tres casos web inactivos, los quince rechazos API inactivos,
   los tres casos web activos y los 37 escenarios restantes del PR.
4. Mantener pruebas del ciclo activo → inactivo → activo con JWT y sesiones
   anteriores, además del nuevo login satisfactorio y rutas públicas.
5. Comprobar la compatibilidad de credenciales sin marca con versión inicial y
   su rechazo tras una transición, sin cambiar el acceso público a login,
   recuperación, registro y verificación de correo.

Esta revisión es estática sobre la referencia indicada; no equivale a haber
ejecutado ambas suites integradas. No se realizaron merge, rebase, cherry-pick,
migraciones ni cambios en la rama del PR #28 para obtener este diagnóstico.
