# Planes y suscripciones: etapa 1

## Catalogo y condiciones contratadas

`plans.price_monthly` conserva su significado. `price_yearly` es nullable: null
significa precio anual no configurado, nunca doce veces el mensual ni precio cero.
La moneda del catalogo es MXN. PlanSeeder solo crea los slugs ausentes mediante
firstOrCreate: no sobrescribe precios, limites, nombres ni disponibilidad existentes.

Cada fila de `subscriptions` representa un periodo. `subscription_contracts` guarda
una unica copia de su empresa/plan, fechas, nombre/slug, cinco limites, importe,
moneda, modalidad y origen. No tiene updated_at. Los contratos no pueden editarse
ni eliminarse desde modelos, operaciones masivas o SQL UPDATE/DELETE: hay triggers
para SQLite y MySQL. Las referencias usan RESTRICT para conservar la historia.
La aplicacion no debe escribir contratos mediante SQL directo ni deshabilitar sus
protecciones. Las identidades y fechas de una suscripcion persistida tampoco se
reescriben por el modelo; una renovacion crea otro periodo, no extiende la fila vieja.

`Company::effectivePlan()` mantiene el tipo Plan esperado por los controladores,
pero devuelve una proyeccion de lectura de las condiciones congeladas. Cambiar
precios, limites, nombre o disponibilidad del catalogo no cambia un periodo vigente.
`is_active` del catalogo se exige para nuevas contrataciones, no para quitar acceso
a contratos ya vigentes. Una empresa suspendida sigue bloqueada por los controles
existentes, independientemente de sus contratos.

## Compatibilidad y migracion

Las migraciones agregan columnas y una tabla; no actualizan filas de suscripciones
anteriores ni sus fechas. Para ellas se registra origin=legacy con los limites
observables al migrar y recorded_at indicando ese momento. **No se afirma que sean
los limites originales de contratacion**. Importe, moneda historica y modalidad
quedan NULL porque no hay evidencia para reconstruirlos. Estos precios desconocidos
no se deben mostrar como cero. Reanudar la migracion no duplica ni actualiza contratos.

Las creaciones con el modelo existente (incluido el registro Gratis) capturan limites
en la misma transaccion con origin=compatibility y sin atribuir una modalidad/importe
que ese flujo no suministra. No se hace backfill al consultar ni se alteran contratos
para actualizar precios. No hay interfaz ni operaciones de cobro en esta etapa.

## Vigencia

Un periodo concede condiciones si status=active, starts_at es NULL o <= ahora y
ends_at es NULL o > ahora: inicio incluido, fin excluido. Fechas NULL mantienen su
compatibilidad anterior sin inventar vencimientos. Entre periodos elegibles
heredados se selecciona el mayor id; uno futuro o vencido no oculta otro vigente.
Si no hay ninguno se utiliza Gratis activo del catalogo. Una suscripcion Gratis
anterior de vigencia indefinida conserva tambien su propia copia de condiciones.
Los estados historicos no se actualizan automaticamente por el paso del tiempo.

## Punto de entrada interno para nuevos periodos

`SubscriptionContractService::create(company, plan, period, startsAt, endsAt,
requestKey, origin)` acepta period monthly/yearly, origin purchase/renewal y fechas
explicitas. Bloquea empresa y plan, verifica disponibilidad y captura el precio
actual de la modalidad y los limites. La clave idempotente identifica el periodo;
un reintento devuelve el mismo contrato y no vuelve a cotizar. No puede reutilizarse
para otra empresa ni otras fechas/modalidad. Un indice unico impide duplicados.
Se rechazan solapamientos con otros periodos de pago activos; el Gratis base no
impide contratar Pro. La renovacion anticipada puede empezar cuando termina el
periodo anterior. Todo se confirma o revierte en una transaccion.

Este servicio no es una API publica ni verifica pagos: el futuro flujo autorizado
de contratacion debe llamarlo solo al confirmar la operacion de negocio. No fija
precios desde el cliente, genera fechas automaticamente, cobra ni renueva solo.

## Validacion y decisiones pendientes

Las pruebas usan solo SQLite :memory: despues de verificar driver, ruta y PRAGMA.
No se aplicaron migraciones sobre MySQL. Su usuario de migracion necesitara permiso
TRIGGER y debe validarse la DDL en un entorno autorizado antes del despliegue.
Los limites previos al momento del baseline y precios historicos desconocidos no
pueden recuperarse sin evidencia externa. Permanecen pendientes la interfaz de
catalogo, fijar importes anuales reales, politicas comerciales exactas de calendario,
cobros y Mercado Pago; esta etapa no los implementa.

## Refuerzo de inmutabilidad y recuperacion

SubscriptionContract solo admite insercion mediante capture; su constructor de
consultas bloquea escrituras heredadas, increment/decrement (incluidos quiet/Each),
upsert, truncate y escrituras por toBase/getQuery. ContractedPlan usa el mismo
constructor sin habilitar inserciones y no puede escribir en el catalogo.
Las lecturas y subconsultas conservan la API de Eloquent. SQL directo es una
frontera administrativa: no usarlo como flujo de contratacion.

Un tercer trigger BEFORE INSERT rechaza colisiones de id, subscription_id o
request_key, incluso con INSERT OR REPLACE y recursive_triggers=OFF en SQLite.
Los triggers existentes se verifican y nunca se eliminan para reemplazarlos.
Un nombre de trigger con otra definicion produce un error que requiere revision.
La migracion 010002 aplica el refuerzo a instalaciones con 010000/010001 ya
registradas; no crea contratos faltantes ni recalcula condiciones historicas.

Antes de habilitar trafico tras el despliegue ejecutar, en un entorno autorizado,
`php artisan subscriptions:verify-integrity`. Es de solo lectura: comprueba
columnas/tipos/nullabilidad, claves unicas, referencias RESTRICT, los tres triggers,
cobertura y coincidencia de empresa/plan/fechas. La migracion comprueba integridad
y la resolucion del plan efectivo vuelve a verificar las suscripciones de esa
empresa: si faltan contratos o se cambiaron referencias/fechas, falla cerrada sin
recurrir a limites actuales ni alterar datos. Un fallo requiere corregir la causa
antes de restaurar trafico; no se reparan contratos automaticamente.

Para desplegar: detener escritores/colas, respaldar esquema y datos, ejecutar
migraciones con el usuario autorizado y verificar integridad antes de reanudar.
MySQL hace commits implicitos de DDL: un fallo puede conservar columnas, tabla
o algunos triggers aunque no se registre la migracion. El backfill nuevo es
transaccional. No borrar tablas ni contratos para reintentar: revisar el esquema,
mensaje de error y tabla migrations. Dos columnas de precios compatibles permiten
reanudar sin sobrescribir; una sola columna, tipos/indices/referencias incompletos
o triggers conflictivos requieren reparacion revisada o restauracion del respaldo.
Reintentar solo migraciones pendientes despues de verificar la causa. No ejecutar
manualmente la migracion original sobre historia perdida: restaurar los contratos
verificados; no capturarlos con el catalogo actual. El rollback de la tabla rechaza
historia no vacia; el rollback de precios rechaza importes anuales configurados
y el rollback del refuerzo conserva las protecciones. El servicio de nuevos
periodos verifica la historia de la empresa antes de aceptar nuevas operaciones.

Pendiente verificar en MySQL aislado y autorizado: DDL y metadatos de la version
real, privilegios TRIGGER (y requisitos de binary logging), almacenamiento
transaccional InnoDB, REPLACE/INSERT IGNORE y recuperacion de fallos de DDL.
La cuenta de ejecucion debe carecer de ALTER/DROP/TRIGGER: ningun trigger puede
impedir que una cuenta con privilegios administrativos elimine sus protecciones.
No se conecto a MySQL para estas validaciones.
