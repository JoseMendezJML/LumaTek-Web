# Firmware distribuible y configuración inicial — propuesta de fase 2

Actualización: posteriormente se incorporó la fuente de laboratorio en `firmware/esp32-devkit-v1/laboratorio/`. La búsqueda sin resultados descrita abajo corresponde a la entrega original. La adaptación experimental aislada y sus limitaciones actuales se documentan en [distribucion/README.md](../firmware/esp32-devkit-v1/distribucion/README.md); aún no constituye firmware aprobado ni compilado.

**Estado: diseño pendiente de disponer del firmware fuente. No es una versión instalable.**

## 1. Resultado de la búsqueda

Rama comprobada: `feature/iot-vinculacion-configuracion`. Se conservan los cambios existentes.

En el espacio de trabajo LumaTek-Web no se encontraron `.ino`, `platformio.ini`, `sdkconfig*`, `idf_component.yml`, `CMakeLists.txt` ni tablas de particiones de firmware. La búsqueda incluyó archivos ocultos e ignorados, excluyendo dependencias de terceros y Git. Tampoco aparecieron fuentes propias con los controladores Wi-Fi/DHT o las APIs de ESP32 buscadas. No se exploraron otras carpetas del ordenador ni se abrió `.env`.

Sí están los contratos Laravel en `IotController`, `IotConfiguration`, `IotReadings`, las rutas API, las pruebas y el catálogo de instalación. Eso permite diseñar el cliente, pero no conocer las implementaciones reales de sensores, TLS, almacenamiento, tiempos o recuperación del firmware de laboratorio.

No se crearon fuentes `.ino`/C++, binarios ni manifiestos ficticios. No se modificó ni se sustituyó el firmware funcional. El catálogo permanece en preparación y las descargas/USB desactivadas.

### Material que hace falta

| Material | Motivo |
|---|---|
| Proyecto fuente completo del ESP32, en copia saneada | Conservar exactamente su comportamiento antes de adaptarlo; incluir archivos auxiliares y cabeceras sin secretos |
| Entorno y versiones de compilación | Arduino core/ESP-IDF/PlatformIO, bibliotecas DHT/JSON/TLS y sus versiones; hoy se desconocen |
| Identificación de la placa física | Módulo, revisión de silicio, flash, puente USB-UART, esquema y pin de recuperación; DevKit V1 no determina por sí solo todos estos datos |
| Tabla de particiones y parámetros de carga | No se deben adivinar offsets, tamaño de flash, modo o frecuencia |
| Perfil público de servidor de producción | Dominio HTTPS autorizado, cadena CA y política de renovación; no se necesitan claves privadas del servidor |
| Capturas saneadas de solicitudes/respuestas | Contrastar el cliente físico con los contratos revisados, sin Bearer, Wi-Fi, secretos de canje ni credenciales |
| Equipo de ensayo separado | No sobrescribir el ESP32 de laboratorio que ya funciona |

Si el proyecto está fuera de este espacio de trabajo, hace falta su ubicación o una copia sin secretos. No se requiere recibir contraseñas Wi-Fi ni la credencial del dispositivo actual.

## 2. Arquitectura propuesta

Separar la adquisición y los contratos existentes de la configuración inicial. Cuando se obtenga la fuente, preparar una copia nueva, por ejemplo `firmware/distribution/`, sin apuntar compilación/carga al proyecto de laboratorio. Los siguientes nombres son **módulos propuestos, no archivos encontrados o implementados**:

| Módulo propuesto | Responsabilidad |
|---|---|
| `ProvisioningController` | Ventana de configuración con presencia física, sin monitor serial ni portal HTTP |
| `UsbProvisioningTransport` | Intercambio limitado con el asistente web; no consola de comandos arbitrarios |
| `SecureSettingsStore` | Wi-Fi y credencial individual en NVS cifrada, cambios atómicos y recuperación |
| `TrustedServerProfile` / `TlsClient` | Origen autorizado, CA, validación de identidad y gestión del reloj |
| `PairingClient` | Desafío, espera de aprobación y canje usando los contratos actuales |
| `RemoteConfigurationClient` | Sensores/pines/unidades/intervalo/versionado descargados desde Laravel |
| `SensorSampler` | Una adquisición DHT22 compartida y lectura ADC; nada de actuadores |
| `ReadingsUploader` | Lotes inmutables, UUID, reintentos y cola acotada |
| `RecoveryController` | Cambio de Wi-Fi y recuperación local con confirmación física |

El firmware distribuible será genérico: sin empresa, IDs de sensores, red personal, JWT de usuario ni credenciales preinstaladas. Cada agricultor selecciona su dispositivo registrado en LumaTek y la autorización empresarial se resuelve en el servidor.

Estados propuestos: **sin configurar → configuración local autorizada → comprobación Wi-Fi/TLS → pendiente de vinculación → configuración remota válida → transmisión**. Los errores llevan a espera/reintento o recuperación explícita; nunca a HTTP, TLS inseguro o una vinculación automática con otra empresa.

## 3. Configuración inicial recomendada: USB desde la web

Se recomienda primero un ordenador y navegador compatible con Web Serial. No se exige abrir monitor serial ni editar/compilar contraseñas. La web, servida por HTTPS, solicita el puerto tras una acción del usuario; la compatibilidad debe detectarse y no se promete funcionamiento universal en móviles. [Documentación oficial de Web Serial](https://developer.chrome.com/docs/capabilities/serial).

Flujo propuesto:

1. El administrador inicia sesión, selecciona un dispositivo propio activo y conecta físicamente el ESP32 por USB.
2. Una acción física en la placa abre una ventana de configuración de tres minutos. El pin/gesto se definirá tras comprobar el hardware; no se asigna GPIO a ciegas ni se utiliza una salida de riego.
3. La web selecciona explícitamente el puerto y negocia versión/capacidades y un nonce de sesión. Se confirma modelo y equipo seleccionado. El USB-UART por sí solo no es una identidad criptográfica del equipo: la posesión física y la confirmación son parte de la confianza.
4. El usuario introduce Wi-Fi en la página HTTPS; se envía exclusivamente al ESP32 por el USB local, no al backend, URLs, analítica, logs, almacenamiento del navegador o archivos descargables. Se requiere un ordenador confiable: HTTPS no cifra por sí mismo el tramo USB ni protege frente a malware local.
5. El ESP32 verifica conectividad y servidor. Solo confirma la nueva configuración después de validarla. Un corte de energía debe dejar el perfil anterior utilizable o permitir recuperación, no un estado parcialmente escrito.
6. Se vacían campos y buffers sensibles, se cierra el puerto y se deshabilita el canal de provisión al terminar, cancelar, vencer el plazo o desconectar. Los mensajes deben tener límites de tamaño, validación estricta y protección frente a repetición por sesión.

El protocolo de aplicación USB y su interfaz web todavía no existen y deben implementarse y revisarse; no se presenta esta propuesta como una capacidad automática de ESP Web Tools. Durante operación normal no se aceptan comandos de lectura de secretos ni reconfiguración por USB sin reapertura física.

### Alternativa inalámbrica

Un SoftAP con WPA2 no resuelve por sí solo la prohibición de HTTP inseguro. No se propone un portal cautivo HTTP ni aceptar certificados locales ignorando advertencias.

Si se necesita configuración sin cable, evaluar BLE con el aprovisionamiento autenticado/cifrado de Espressif Security 2. Utiliza SRP6a y AES-GCM; requiere un secreto de posesión único y un cliente compatible. Debe definirse su entrega física segura (por ejemplo etiqueta individual), sin contraseña universal ni secretos incluidos en el binario genérico. Falta diseñar ese enrolamiento y verificar soporte de plataforma. [Protocomm oficial](https://docs.espressif.com/projects/esp-idf/en/stable/esp32/api-reference/provisioning/protocomm.html).

Un AP HTTPS solo sería alternativa después de resolver identidad y confianza del certificado individual sin omitir validación. No se habilita ningún AP en esta fase. Si se implementa después: apertura por presencia física, contraseña única, plazo breve, pocos intentos y cierre/borrado de datos de sesión obligatorio.

## 4. Vinculación sin monitor serial, conservando la API

| Contrato existente | Uso propuesto |
|---|---|
| `POST /api/iot/pairings` | El ESP32 obtiene `pairing_id`, `operator_proof`, `exchange_secret` y plazo de 600 segundos por HTTPS |
| `POST /api/devices/{device}/pairing` | La web, con autenticación de administrador existente, aprueba ID y prueba para un dispositivo de su empresa |
| `POST /api/iot/pairings/exchange` | Solo el ESP32 canjea ID y secreto; recibe `device_id` y credencial individual |
| `DELETE /api/devices/{device}/pairing` | Revocación administrativa existente |
| `GET /api/iot/config` | Credencial individual como Bearer; no JWT de usuario |
| `POST /api/iot/readings` | Credencial individual, lotes UUID y mediciones válidas |

El ESP32 transfiere por la sesión USB únicamente el ID y la **prueba temporal del operador**. El asistente los envía a la ruta de aprobación ya existente, después de la confirmación del administrador, sin pedir copiarlos del monitor serial. La prueba es una capacidad sensible de corta duración: no mostrarla como QR público, persistirla ni registrarla. Solo permanece el tiempo necesario en memoria del navegador y en la solicitud HTTPS de aprobación.

El `exchange_secret` y la credencial permanente **no salen del ESP32 al navegador**. El JWT del usuario tampoco se envía al ESP32. El nonce local vincula los mensajes a la conexión USB seleccionada; no reemplaza los controles empresariales ni el desafío del servidor. No añadir un endpoint público de “reclamar dispositivo” ni usar su MAC como prueba de propiedad.

Esperar aprobación con reintentos acotados y backoff respetando los límites actuales: desafío y canje tienen throttling de 6/minuto; configuración/lecturas de 60/minuto. No consultar el canje en un bucle rápido. Un desafío vencido se reemplaza y sus secretos temporales se limpian. Una aprobación no debe revocar una vinculación existente silenciosamente.

**Caso a resolver sin inventar garantías:** el canje actual entrega la credencial una sola vez y Laravel conserva su hash. Si se pierde la respuesta o la alimentación antes de guardar la credencial, el mismo secreto no recupera esa credencial. El asistente debe explicar la situación y permitir al administrador revocar e iniciar un desafío nuevo. No cambiar ese contrato ni asumir canje idempotente en esta fase.

## 5. Configuración remota y adquisición

Mantener `device_id`, `board_model`, `measurement_interval`, `configuration_version` y sensores con `id`, `driver`, `pin`, `measurement`, `unit`, `calibration`. Validar la configuración completa y sustituirla atómicamente; conservar la última válida ante una descarga incompleta, sin continuar enviando IDs eliminados después de un rechazo del servidor.

El intervalo se descarga, no se fija a 60 o 300. Consultar configuración al arrancar y con una cadencia de actualización documentada; usar temporización monotónica para medir intervalos y hora UTC para fechar lotes. No bloquear la adquisición con esperas prolongadas de red. Registrar la política efectiva de actualización en el futuro manual de versión.

Conservar DHT22 en GPIO4: una muestra física permite producir temperatura y humedad ambiental para sus respectivos IDs remotos. El suelo analógico probado usa GPIO34 y entrega ADC RAW sin calibración. El servidor admite otros pines analógicos compatibles; el cliente deberá validar los que realmente soporte, no fingir compatibilidad.

No transmitir NaN, infinitos ni lecturas inventadas ante un sensor ausente. No etiquetar ADC como `%`. Para suelo calibrado, hay que recuperar y revisar la fórmula real del firmware antes de adaptarla; el backend valida unidad/calibración, pero eso no prueba cuál era esa implementación. Hasta validarla, informar incompatibilidad en vez de fabricar porcentajes.

Cada lote conserva UUID, valores, IDs y fecha durante sus reintentos. Un contenido diferente requiere UUID nuevo. Respetar 201/200 y `duplicate`, conflictos 409 y validaciones 422. La cola debe ser acotada y no cambiar fechas para eludir validaciones: actualmente el servidor acepta nuevas mediciones de las últimas 24 horas y hasta 5 minutos en el futuro. No prometer envío ilimitado de meses offline con este contrato. Ante 401 detener envío autenticado y solicitar recuperación de vinculación; ante 403/422 mostrar diagnóstico y revisar configuración, sin reintentos agresivos ni asignar otra empresa.

No inicializar relés, bombas o pines de actuación. Este firmware es de medición.

## 6. Credenciales, almacenamiento y TLS

Guardar la credencial por dispositivo y Wi-Fi en NVS cifrada; verificar también el almacenamiento que usa el propio driver Wi-Fi. Usar `Preferences` o una NVS corriente no acredita cifrado. Para ESP32 clásico debe evaluarse el esquema de NVS con partición de claves protegida por Flash Encryption; no asumir prestaciones de otros chips. [NVS Encryption oficial](https://docs.espressif.com/projects/esp-idf/en/stable/esp32/api-reference/storage/nvs_encryption.html).

Claves de cifrado únicas por equipo, generadas/provisionadas de forma controlada y nunca incluidas en el `.bin`, repositorio o catálogo genérico. Para producción, evaluar Secure Boot junto con Flash Encryption según la revisión del silicio. Eso requiere un proceso de fabricación y recuperación: configurar eFuses puede ser irreversible y limitar la recarga USB. No se ejecutan acciones sobre eFuses ni se propone aplicarlas al equipo de laboratorio. [Guía de Flash Encryption](https://docs.espressif.com/projects/esp-idf/en/latest/esp32/security/flash-encryption.html).

Todas las conexiones de red del cliente a LumaTek serán HTTPS, validando cadena CA, vigencia y hostname/SNI; queda prohibido `setInsecure`, saltar nombre del servidor o aceptar cualquier certificado. El reloj inicial debe validarse contra límites razonables y último tiempo confiable; un reloj inválido impide autenticación y da diagnóstico, no desactiva TLS. La política exacta de sincronización/defensa frente a manipulación se debe validar en el dispositivo. [ESP-TLS oficial](https://docs.espressif.com/projects/esp-idf/en/stable/esp32/api-reference/protocols/esp_tls.html).

El servidor de producción será un perfil público configurable dentro de orígenes HTTPS autorizados, con sus CA y política de rotación. No permitir URL arbitraria por USB que pueda recibir la credencial. Vincular credencial y perfil de origen en almacenamiento; no reenviar Bearer al seguir redirecciones hacia otro origen. Un servidor nuevo requiere revisión del perfil y nueva autorización, no reutilizar la credencial de otro entorno. No se conoce todavía el dominio final y no se inventa.

Distribuir certificados CA y claves públicas de verificación es distinto de distribuir claves privadas. Preparar actualización firmada de confianza antes del vencimiento/rotación, conservando validación y un procedimiento de recuperación. No publicar claves de firma ni imágenes NVS personales. Los logs y volcados de fallo deben excluir Wi-Fi, Bearer y secretos de sesión.

## 7. Recuperación

- **Cambio de Wi-Fi:** presencia física abre el asistente USB; validar la red nueva antes de sustituir la anterior. Mantener la credencial IoT si sigue válida y pertenece al mismo servidor.
- **Wi-Fi incorrecto o servidor inaccesible:** backoff limitado, diagnóstico sin secretos y reapertura local por botón. No abrir AP automáticamente por una simple caída de Internet.
- **NVS dañada o configuración perdida:** modo de recuperación explícito, nunca cargar valores personales de fábrica. Si no queda credencial utilizable, el administrador revoca el vínculo anterior y repite el desafío.
- **Revocación:** detener autenticación tras 401 y no intentar volver a reclamar automáticamente el mismo dispositivo.
- **Restablecimiento completo/traspaso:** confirmación física adicional y revocación administrativa del vínculo anterior. Borrar datos personales y verificar su inaccesibilidad; no prometer borrado forense solo por eliminar una clave NVS. No borrar claves/eFuses de seguridad a ciegas.

El pin y duración del gesto, señalización LED, límites de intentos y formato de mensajes USB se definirán tras revisar esquema y fuente. No modificar el circuito que ya funciona para probar una suposición.

## 8. Compilación reproducible y artefactos

Antes de producir `.bin`, fijar: revisión/flash real de la placa, framework y toolchain, bibliotecas y licencias, tabla de particiones, opciones de seguridad, certificados públicos, perfil autorizado y versión del protocolo local. Registrar versiones exactas y lockfiles; no adoptar automáticamente la última versión del SDK ni migrar de Arduino a ESP-IDF sin revisar la fuente.

Si se mantiene Arduino, usar su FQBN y opciones reales comprobadas. Si se adopta ESP-IDF o Arduino como componente, documentar el cambio y los ensayos de equivalencia. No hay ahora un FQBN, `sdkconfig`, baud rate ni offsets contrastados del firmware actual, por lo que no se ofrece un comando supuestamente compilable con valores inventados.

En una compilación futura aislada, guardar inventario de fuentes, versiones, hashes SHA-256, tamaño, licencia y resultado de revisión. Obtener imágenes de aplicación, bootloader, particiones y parámetros de carga desde la salida del build. Un `.bin` de aplicación no equivale necesariamente a una imagen de instalación completa. No incluir NVS ni particiones con redes o claves de equipos reales.

Separar perfil de banco, sin programación irreversible de seguridad, del perfil de producción verificado. Validar estrategia de actualización firmada, protección de claves y compatibilidad de recuperación antes de bloquear equipos. No ejecutar carga, borrado de flash ni fuses como parte de esta propuesta.

## 9. Integración futura con ESP Web Tools y catálogo

Conservar el catálogo actual sin publicar. Tras pasar los ensayos físicos y la revisión, registrar versión, fecha, modelo, instrucciones, aprobador, revisión de secretos y hash. `.ino` solo será descargable si existe una fuente distribuible saneada real; no crear un envoltorio ficticio para un proyecto de otro framework.

Para USB, añadir un manifiesto aprobado con familia de chip y lista de imágenes/offsets obtenidos del build real. ESP Web Tools requiere HTTPS y puede borrar datos durante instalación; la interfaz deberá distinguir primera instalación de actualización con conservación de configuración y explicar qué ocurre con NVS. No usar offsets copiados de un ejemplo. [Documentación oficial de ESP Web Tools](https://esphome.github.io/esp-web-tools/).

El catálogo actual ofrece formatos `.ino`/`.bin`; no sirve todavía manifiestos ni todas las partes de un build. Hacen falta rutas privadas adicionales, revisión integral del conjunto e integridad por imagen. Preferir mismo origen y sesión autorizada para manifiesto/imágenes; comprobar en navegador que cada fetch conserva la autorización, sin colocar JWT o enlaces secretos en JSON, URLs o firmware. Una revocación de publicación debe impedir nuevas descargas de todas sus partes.

ESP Web Tools instala imágenes; no implementa automáticamente nuestro flujo empresarial. El asistente de provisión propuesto se integra después de finalizar instalación y liberar el puerto, o antes si el firmware compatible ya está instalado. No asumir que Improv Serial entrega la vinculación Laravel ni que sustituye nuestro diseño de seguridad.

Existe una decisión de producción pendiente: un equipo protegido con Flash Encryption/Secure Boot puede restringir la instalación USB genérica. La matriz de chips, modo de seguridad y procedimientos de fabricación/actualización debe demostrar compatibilidad; el botón USB no se habilita solo porque exista un `.bin`.

## 10. Plan de aceptación antes de publicar

Usar un **segundo ESP32**, sin actuadores, y un backend HTTPS de ensayo separado autorizado expresamente. No usar `lumatek_actual`, `lumatek_pruebas_migracion` ni las credenciales de laboratorio. Mientras tanto, las pruebas Laravel se ejecutan solo en SQLite `:memory:`.

| Ensayo | Resultado exigido |
|---|---|
| Instalación en placa limpia | Arranca sin red, empresa ni credencial precompiladas |
| Configuración USB | Funciona sin monitor serial, solo tras acción física y permiso de puerto; cierra al terminar/vencer |
| Inspección de secretos | No aparecen en archivos de distribución, URLs, logs, navegador persistente ni respuestas diagnósticas |
| Wi-Fi erróneo/cambio/corte de energía | Recuperación controlada, sin configuración parcial ni AP abierto |
| Vinculación entre dos empresas | Solo el administrador correcto aprueba el dispositivo; ID/prueba vencidos o repetidos no permiten apropiación |
| Pérdida de respuesta al canje | Se detecta y se guía revocación/nuevo desafío, sin reclamar idempotencia inexistente |
| Revocación y reinicio | Credencial anterior rechazada y persistencia correcta de la nueva tras reautorización |
| TLS negativo | Rechaza CA inválida, hostname diferente, certificado no vigente y redirección a otro origen |
| Configuración remota | IDs dinámicos, GPIO4 compartido y GPIO34; cambio de 60 a 300 y viceversa sin recompilar |
| Mediciones | DHT22 comparable a referencia; suelo conserva ADC RAW y su procedencia; sin lecturas falsas ante fallo |
| Duplicados y desconexión | Mismo UUID/payload no duplica; conflicto no se sobrescribe; límites de antigüedad respetados |
| Memoria/estabilidad | Ensayo prolongado con reconexiones, NVS y memoria revisadas; sin bloqueos ni crecimiento de cola |
| Protección de producción | Confirmar cifrado real de datos personales, revisión de chip y recuperación con el perfil elegido |
| Instalador web | Manifiesto/partes revisados; modelo incorrecto, hash alterado y versión retirada no se instalan |
| Cero actuación | Ningún comando o prueba energiza relés/bombas ni cambia reglas de riego/alertas |

Guardar evidencias saneadas, hashes de los artefactos exactos, placa y toolchain utilizados, acta de revisión y aprobación explícita. Solo después se podrá habilitar publicación y descarga. Esta propuesta no constituye esa aprobación.

## 11. Alcance de esta entrega

Único archivo nuevo: este documento. Sin cambios en firmware, catálogo, interfaz operativa, contratos API, `.env` o bases de datos. No se compiló, flasheó ni probó físicamente un firmware que no está disponible. La siguiente entrada necesaria es el proyecto fuente saneado y su entorno de compilación para preparar una adaptación aislada verificable.

Verificación de los contratos existentes: **40 pruebas PHP y 681 aserciones aprobadas** (`IotConfigurationTest`, `IotReadingsTest`, `IotInstallationTest`), exclusivamente con SQLite `:memory:`. `git diff --check` sin errores. Estas pruebas acreditan el backend y los controles de distribución existentes; no acreditan compilación, TLS del cliente, cifrado NVS ni funcionamiento físico de un firmware nuevo.
