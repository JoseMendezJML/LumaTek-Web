# Asistente LumaTek: contrato para la web y la app móvil

El asistente resume el estado de los invernaderos de la empresa, da consejos
agronómicos y analiza fotos para orientar la detección de plagas. La web
(`/assistant`) y la app móvil usan el mismo endpoint.

El servidor **no guarda conversaciones**: cada cliente guarda su historial y lo
envía completo en cada mensaje.

## Endpoint

```
POST /api/chat
Authorization: Bearer <access_token>      (el mismo JWT de /api/login)
Accept: application/json
Content-Type: multipart/form-data
```

| Campo | Tipo | Reglas |
|---|---|---|
| `messages` | texto (JSON) | Requerido. Lista de 1 a 20 objetos `{ "role": "user" \| "assistant", "content": "..." }`. Los roles se alternan, el primero y el último son `user`. `content` no vacío: hasta 4000 caracteres en mensajes `user` y 20 000 en `assistant`. |
| `image` | archivo | Opcional. JPEG, PNG o WEBP, máximo 5 MB. Se adjunta al último mensaje del usuario. Recomendado: reducirla a 1568 px en el lado mayor antes de subirla. |

El campo `messages` va como **texto JSON** dentro del multipart, no como
arreglo de campos.

## Respuestas

| Código | Cuerpo | Qué hacer en la app |
|---|---|---|
| 200 | `{ "reply": "<markdown>", "remaining_today": 37 }` | Agregar la pregunta y `reply` al historial; mostrar "Quedan N hoy". |
| 401 | — | Sesión expirada: volver al login. |
| 403 | `{ "message": "..." }` | Usuario sin empresa, inactivo o empresa suspendida. |
| 422 | errores de validación de Laravel | Historial mal formado o imagen inválida. |
| 429 | `{ "code": "chat_limit_reached", "message": "..." }` | Se alcanzó el límite diario de la empresa. Mostrar `message`. |
| 429 | `{ "code": "chat_rate_limited", "message": "..." }` | Más de 10 mensajes por minuto del mismo usuario. Mostrar `message` y esperar el `Retry-After`. |
| 503 | `{ "code": "assistant_unavailable", "message": "..." }` | Falla temporal. Mostrar `message` y conservar lo que el usuario escribió. |

`reply` usa markdown sencillo: `**negritas**`, listas con `- ` y saltos de
línea. Escapa el texto antes de darle formato; nunca lo insertes como HTML.

## Historial en el dispositivo

- Guarda `{ role, content }` de cada pregunta y respuesta exitosa. No guardes
  la imagen; si quieres, una marca como "📷 imagen enviada".
- Al enviar, manda los últimos 20 mensajes (contando la pregunta nueva) y
  descarta los del inicio hasta que el primero sea `user`.
- No agregues al historial las preguntas que terminaron en error, para que los
  roles sigan alternándose.
- Borra el historial al cerrar sesión.

## Ejemplo

```bash
curl -X POST http://localhost:8000/api/chat \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" \
  -F 'messages=[{"role":"user","content":"Dame un resumen de mis invernaderos."}]' \
  -F 'image=@hoja.jpg'
```

## Configuración del servidor (.env)

| Variable | Por defecto | Descripción |
|---|---|---|
| `ANTHROPIC_API_KEY` | — | API key de la Claude Developer Platform. Sin ella el endpoint responde 503. |
| `ANTHROPIC_MODEL` | `claude-opus-5-5` | Modelo de Claude. Alternativas más económicas: `claude-sonnet-5-5`, `claude-haiku-5-5`. |
| `CHAT_DAILY_LIMIT` | `50` | Mensajes exitosos por empresa al día. |
