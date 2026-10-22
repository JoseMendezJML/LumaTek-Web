# Asistente LumaTek (chatbot): diseño

Fecha: 2026-10-22 · Rama: `feature/chatbot`

## Objetivo

Un asistente conversacional, disponible en el plan Gratis, que:

1. Resume el estado de todos los invernaderos de la empresa del usuario.
2. Da consejos agronómicos relacionados con el cultivo y las lecturas reales.
3. Analiza fotos para orientar la detección de plagas.

La web y la app móvil comparten la misma infraestructura: un único endpoint
`POST /api/chat` protegido con JWT. La app móvil vive en otro repositorio y solo
consume el contrato documentado aquí.

## Fuera de alcance

- Historial persistente en el servidor (cada cliente guarda su historial localmente).
- Streaming de respuestas.
- Que el asistente ejecute acciones (riegos, alertas, configuración).
- Cambios en planes o suscripciones.
- La pantalla de chat de la app móvil.

## Proveedor

Claude (Anthropic) mediante el SDK oficial de PHP `anthropic-ai/sdk`, con una API
key de la Claude Developer Platform (cobro por uso, independiente de cualquier
suscripción de claude.ai). Un mismo modelo atiende texto e imágenes.

## Arquitectura

```
Web (Blade + fetch)  ─┐
                      ├─ POST /api/chat (auth:api, throttle:10,1)
App móvil            ─┘        │
                         Api\ChatController
                           ├─ valida petición
                           ├─ verifica límite diario por empresa (Cache)
                           ├─ GreenhouseSnapshotService → resumen de la empresa
                           └─ ChatAssistant → Claude (Messages API)
```

### Componentes

| Archivo | Responsabilidad |
|---|---|
| `app/Services/GreenhouseSnapshotService.php` | Construye el resumen de una empresa. Contiene la lógica de métricas y estados extraída de `DashboardController` (`buildMetric`, `metricStatus`, `statusLabel` y auxiliares). |
| `app/Http/Controllers/DashboardController.php` | Pasa a usar `GreenhouseSnapshotService` para las métricas. Su respuesta JSON no cambia. |
| `app/Services/ChatAssistant.php` | Único punto de contacto con Claude: arma las instrucciones, el resumen, el historial y la imagen, y devuelve el texto. Se registra en el contenedor para poder sustituirlo en pruebas. |
| `app/Http/Controllers/Api/ChatController.php` | Validación, límite diario, orquestación y mapeo de errores. |
| `routes/api.php` | `POST /chat` dentro del grupo `auth:api`, con `throttle:10,1`. Nombre: `api.chat.send`. |
| `config/services.php` | Bloque `anthropic`: `api_key`, `model`, `daily_limit`, `timeout`. |
| `.env.example` | `ANTHROPIC_API_KEY=`, `ANTHROPIC_MODEL=claude-opus-5-5`, `CHAT_DAILY_LIMIT=50`. |
| `routes/web.php` | `Route::view('/assistant', 'assistant.index')->name('assistant.index')` dentro del grupo `auth`, `active.user`, `plan.access`. |
| `resources/views/assistant/index.blade.php` | Interfaz web del chat. |
| `resources/views/layouts/app.blade.php` | Enlace "Asistente" en el menú lateral y limpieza de `lumatek_chat_history` al cerrar sesión. |
| `docs/chatbot.md` | Contrato del endpoint para el equipo móvil. |

## Contrato del endpoint

```
POST /api/chat
Authorization: Bearer <jwt>
Content-Type: multipart/form-data

messages  string JSON, requerido
          [{ "role": "user" | "assistant", "content": "<texto>" }, ...]
          1 a 20 elementos; el primero y el último deben ser "user";
          content no vacío: hasta 4000 caracteres en mensajes user y
          20 000 en assistant; los roles deben alternarse.
image     archivo opcional: jpeg, png o webp, máximo 5 MB.
          Se adjunta al último mensaje del usuario.
```

Respuestas:

| Código | Cuerpo | Cuándo |
|---|---|---|
| 200 | `{ "reply": "<markdown>", "remaining_today": <int> }` | Respuesta generada (incluye negativas del modelo, con un texto amable). |
| 401 | estándar de `auth:api` | Sin token o token inválido. |
| 403 | `{ "message": "..." }` | El usuario no pertenece a una empresa (p. ej. administrador de plataforma) o la empresa no está activa, con el mismo criterio que `EnsurePlanAccess`. |
| 422 | errores de validación de Laravel | `messages` mal formado o imagen inválida. |
| 429 | `{ "code": "chat_limit_reached", "message": "..." }` | Se alcanzó el límite diario de la empresa. |
| 429 | `{ "code": "chat_rate_limited", "message": "..." }` | Throttle por usuario (más de 10 por minuto). |
| 503 | `{ "code": "assistant_unavailable", "message": "..." }` | Falla de Claude, timeout, falta de saldo o configuración ausente. |

Las imágenes no se almacenan: se envían en base64 a Claude y se descartan al
terminar la petición.

## Resumen de la empresa (`GreenhouseSnapshotService`)

Entrada: `company_id` del usuario autenticado (nunca un dato del cliente).
Salida: arreglo serializado a JSON compacto con:

- `generated_at` (fecha y hora del servidor) y nombre de la empresa.
- Por invernadero: nombre, `crop_type`, ubicación; métricas actuales de
  temperatura, humedad del suelo y humedad ambiental (valor, estado, umbral
  mínimo y máximo),
  calculadas con la misma lógica del dashboard.
- Por zona: nombre, estado y riego automático (activo, duración, litros).
- Alertas activas: las 10 más recientes (tipo, severidad, mensaje, zona y fecha)
  y el total de alertas activas.
- Último evento de riego por invernadero: zona, modo, litros, humedad antes y
  después, fecha.

Se excluyen IDs, usuarios, correos, datos del plan y los recursos con
`plan_restricted = true`, igual que en el dashboard. Si la empresa no tiene
invernaderos, el resumen lo indica y el asistente puede dar consejos generales.

## Instrucciones del asistente

Bloque fijo en español (va primero y se cachea con `cache_control`):

1. Eres el asistente agronómico de LumaTek; respondes en español, claro y breve.
2. Para hablar de la empresa usas únicamente el resumen adjunto; si un dato no
   está, lo dices y no inventas.
3. Puedes dar consejos agronómicos, relacionándolos con el cultivo y las lecturas
   cuando aplique.
4. No ejecutas acciones; si el usuario las pide, indicas en qué pantalla hacerlo.
5. Con una foto: diagnóstico probable, nivel de confianza, señales que lo
   respaldan y pasos sugeridos, cerrando con un aviso de que es orientativo y
   conviene confirmarlo con un agrónomo. Si la foto no muestra una planta, lo dices.
6. Temas ajenos a la agricultura o a LumaTek: rediriges con amabilidad.
7. Formato: markdown sencillo (negritas, listas, saltos de línea).

Después va el resumen de la empresa (bloque variable) y luego el historial.

## Llamada a Claude

- Modelo: `config('services.anthropic.model')`, por defecto `claude-opus-5-5`.
- Sin streaming; timeout de 30 s; `max_tokens` de 2048.
- Esfuerzo `low` (respuestas de chat).
- En modelos que lo admiten, `fallbacks: "default"` del lado del servidor
  (beta `server-side-fallback-2026-07-01`), para que una negativa por falso
  positivo se reintente con otro modelo.
- Se revisa `stop_reason`: si es `refusal`, se responde con un texto amable fijo.

## Límite de uso

- Por empresa al día: `CHAT_DAILY_LIMIT` (por defecto 50), con un contador en la
  caché de Laravel (`chat:{company_id}:{Y-m-d}`) que expira al final del día.
  Solo se incrementa cuando la respuesta es exitosa.
- Por usuario: `throttle:10,1`.
- Se responde `429` antes de llamar a Claude si el contador ya llegó al límite.

Costo máximo aproximado por empresa al día, con 50 mensajes (≈5k tokens de
entrada y 1k de salida por mensaje): Opus 5.5 ≈ $2.00, Sonnet 5.5 ≈ $1.00,
Haiku 5.5 ≈ $0.05. Se recomienda configurar además un tope de gasto mensual en
la consola de Anthropic.

## Errores

| Situación | Respuesta | Log |
|---|---|---|
| Error de conexión, timeout, 429/5xx de Anthropic, saldo insuficiente | 503 `assistant_unavailable` | `warning` con la clase de la excepción |
| `ANTHROPIC_API_KEY` vacía | 503 `assistant_unavailable` | `error` indicando la configuración faltante |
| `stop_reason = refusal` | 200 con texto amable | `info` con la categoría |
| Falla al construir el resumen | Se continúa con "sin datos disponibles" | `warning` |

Ningún error consume el límite diario.

## Interfaz web (`/assistant`)

- Encabezado con "Quedan N hoy" y el botón "Nueva conversación".
- Estado vacío con sugerencias: "Resumen de mis invernaderos", "¿Qué alertas debo
  atender?" y "Analizar foto de una plaga".
- Lista de mensajes; caja de texto; botón para adjuntar imagen con vista previa
  y opción de quitarla.
- El historial se guarda en `sessionStorage` (`lumatek_chat_history`) sin
  imágenes, que se reemplazan por la marca "📷 imagen enviada", y se envían los
  últimos 20 mensajes.
- Antes de subir una imagen, el navegador la reduce a 1568 px como máximo
  (canvas, JPEG).
- Markdown seguro: se escapa todo el HTML y luego se convierten solo
  negritas, listas y saltos de línea, sin librerías.
- Mientras espera, se muestra "Analizando…" y se desactiva el envío. Los errores
  429 y 503 se muestran dentro del chat y se conserva el texto escrito.
- El diseño responsive es el del layout existente.

## Pruebas (PHPUnit, SQLite en memoria)

`tests/Feature/ChatEndpointTest.php` (con `ChatAssistant` sustituido por un falso):
- 401 sin token.
- 403 para un usuario sin empresa y para una empresa suspendida.
- 422 por `messages` ausente, JSON inválido, más de 20 elementos, roles que no
  se alternan, último mensaje que no es `user` o imagen con tipo o tamaño inválido.
- 200 con `reply` y `remaining_today` correctos.
- La imagen y el resumen de la empresa llegan al asistente.
- 429 al alcanzar el límite, sin llamar al asistente.
- 503 cuando el asistente falla, sin consumir el límite.

`tests/Feature/GreenhouseSnapshotServiceTest.php`:
- Solo incluye datos de la empresa indicada.
- Excluye los recursos con `plan_restricted`.
- Empresa sin invernaderos.

`tests/Feature/DashboardSnapshotRegressionTest.php`:
- `GET /api/dashboard` mantiene su estructura y valores tras la extracción.

`tests/Feature/AssistantPageTest.php`:
- `/assistant` redirige si no hay sesión y responde 200 con sesión activa.

No se hacen llamadas reales a la API en las pruebas. La verificación manual
con una API key real queda como paso aparte, con autorización.

## Riesgos

- Costo: mitigado con el límite diario, el throttle y un tope de gasto en la consola.
- La detección de plagas es orientativa, no un diagnóstico; se aclara siempre.
- La extracción desde `DashboardController` puede romper el dashboard; mitigado
  con la prueba de regresión, que se escribe antes de mover la lógica.
- La rama parte de `ccd60b2` y `origin/develop` ya está en `2cb0527`; conviene
  actualizarla antes de implementar.
