# Administracion de planes: etapa 2

`GET /platform/plans` lista Gratis y Pro y sus limites con el layout de plataforma.
`PATCH /platform/plans/{plan}` solo permite cambiar los precios mensual/anual y la
disponibilidad de Pro. Ambas rutas usan auth, active.user y platform.admin.
No son rutas JWT ni conceden permisos sobre recursos empresariales.

Los precios admiten entre 0 y 99999999.99 MXN, con hasta dos decimales. El precio
anual vacio conserva NULL (no configurado), distinto de cero. Gratis no admite
modificaciones ni desactivacion por esta ruta; su importe es cero. Los limites,
moneda, nombre y slug son de consulta y no se aceptan como campos modificables.
El formulario pide confirmacion y el backend exige confirmed=1/accepted.

Cada cambio efectivo bloquea la fila del plan y guarda el catalogo y una entrada
en plan_changes dentro de una unica transaccion. Si falla la auditoria, se revierte
el precio/disponibilidad. Se registran user_id, plan_id, old_values, new_values y
recorded_at; no se registran entradas falsas para envios sin cambios. Las claves
foraneas RESTRICT conservan referencias al operador y al plan. El rollback de la
migracion rechaza eliminar auditoria no vacia. No hay rutas para editar o eliminar
el historial. No se implementa aun su interfaz de consulta.

Desactivar Pro limita nuevas contrataciones; no modifica periodos contratados,
ni los contratos futuros ya registrados. Los contratos mantienen sus precios,
limites y fechas. PlanSeeder sigue usando firstOrCreate y conserva las ediciones.
No se modificaron SubscriptionContract, ContractedPlan ni SubscriptionContractService.
No se implementaron compras, renovaciones ni pagos.

Validar la nueva migracion aditiva en MySQL aislado/autorizado antes de desplegar.
No se ejecuto sobre bases reales. Los despliegues deben aplicar la migracion antes
de habilitar el formulario: una tabla de auditoria ausente hace fallar la escritura
sin permitir cambios sin registro. Conservar respaldo y ejecutar la verificacion
de integridad de contratos de la etapa 1. La cuenta de ejecucion y los accesos
administrativos a la base deben mantener los permisos minimos.
