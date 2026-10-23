# Mi suscripción: API para la app móvil

`GET /api/company/subscription` devuelve en JSON la misma información que la página
web "Mi suscripción" (`SubscriptionController@index`). Es de solo lectura: las
cotizaciones se siguen solicitando con `POST /api/company/pro/quotes`.

## Acceso

`auth:api`, `active.user` y `company.owner`, igual que la página web. Solo el
propietario activo de una empresa activa puede consultarla. Responde `403` a
administradores adicionales, empleados, superadmin y empresas suspendidas.
No depende de `plan.access`: el propietario puede consultarla y renovar aunque Pro
haya vencido.

## Respuesta

```json
{
  "plan": {
    "slug": "pro",
    "name": "Pro",
    "limits": {
      "max_greenhouses": 10, "max_zones_per_greenhouse": 5, "max_sensors_per_zone": 10,
      "max_users": 47, "max_additional_admins": 2
    }
  },
  "current": {
    "id": 1, "plan_name": "Pro", "billing_period": "monthly", "amount": "199.50",
    "currency": "MXN", "starts_at": "2026-10-01T12:00:00-06:00", "ends_at": "2026-11-01T12:00:00-06:00"
  },
  "renewals": [
    { "id": 2, "plan_name": "Pro", "billing_period": "yearly", "amount": "1999.00", "currency": "MXN",
      "starts_at": "2026-11-01T12:00:00-06:00", "ends_at": "2027-11-01T12:00:00-06:00" }
  ],
  "pro": { "available": true, "price_monthly": "199.50", "price_yearly": "1999.00", "currency": "MXN" },
  "quotes": [
    { "id": 7, "billing_period": "monthly", "amount": "199.50", "currency": "MXN", "status": "pending",
      "issued_at": "2026-10-01T12:00:00-06:00", "expires_at": "2026-10-03T12:00:00-06:00",
      "contact_url": "mailto:...", "contract": null }
  ]
}
```

- `plan` es el plan efectivo (`Company::effectivePlan()`). Con un contrato vigente,
  sus límites son los contratados, no los del catálogo actual. Es `null` si la
  empresa no tiene plan.
- `current` es el periodo vigente, o `null`. `renewals` son hasta 10 periodos Pro
  futuros ya confirmados, ordenados por fecha de inicio.
- `pro` muestra el catálogo actual. `price_yearly` es `null` cuando no está
  configurado (distinto de cero). Si `available` es `false`, no se aceptan nuevas
  cotizaciones.
- `quotes` son las 10 cotizaciones más recientes **del propietario autenticado** en
  su empresa. `status` puede ser `pending`, `expired` o `confirmed`, con la misma
  regla de la web: la confirmación prevalece aunque la cotización haya vencido.
- `contact_url` es el mismo enlace `mailto:` de la web para pedir instrucciones de
  pago. Solo existe en cotizaciones pendientes y vigentes, y cuando
  `PRO_QUOTE_CONTACT_EMAIL` está configurado.
- `contract` tiene las fechas del periodo creado al confirmar la cotización.
- Los importes son cadenas DECIMAL (sin `float`). Las fechas están en ISO 8601,
  en la zona horaria de la aplicación.

Solicitar una cotización no activa Pro: requiere la verificación manual del pago
externo por parte de un superadmin.
