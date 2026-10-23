# LumaTek ESP32 — candidato experimental de distribución

**No compilado, no probado físicamente y no aprobado para distribución ni producción.** Ningún archivo de esta carpeta se incorporó al catálogo web. No hay `.bin` ni manifiesto publicados.

## Origen e inmutabilidad del laboratorio

Adaptación aislada de `../laboratorio/LumaTek_IoT_ESP32_Fase2.ino`, 618 líneas. Su SHA-256 se conserva:

`48f41169c0549600f419ff880980b1ec5845f4a6ffded81f45ca15e092220fb2`

No se modificó el original ni la copia externa de OneDrive. Se conserva la arquitectura de `docs/iot-firmware-fase-2-arquitectura.md`; aquel documento describía una búsqueda anterior sin fuentes disponibles. Este proyecto no sustituye ese laboratorio.

## Auditoría de secretos y limitaciones

Se revisó el archivo completo sin imprimir valores sensibles. No se detectaron bloques de clave privada, credenciales IoT literales ni secretos de intercambio precompilados. La credencial y el secreto se obtienen en tiempo de ejecución. La prueba temporal del operador sí se imprime en el monitor serial del original; eso se sustituye por un mensaje USB acotado en esta variante.

El laboratorio contiene una dirección local y un certificado **público** de la CA local. No son una clave privada, pero son específicos del entorno y se excluyeron totalmente de la distribución. Sus valores no se reproducen aquí.

El autor indicó haber sustituido SSID/contraseña por ejemplos. Hay dos literales de Wi-Fi en el original: las comprobaciones heurísticas de placeholders no bastan para certificar su naturaleza. Por tanto, no se declara el archivo original automáticamente saneado para Git: antes de incluirlo, revisar personalmente esos dos valores, además de la conveniencia de publicar dirección/CA locales. Esta advertencia no implica que se haya encontrado una contraseña activa. No se cambió el original para ocultar o sustituir información.

Una búsqueda de patrones no equivale a una auditoría de seguridad exhaustiva ni a una aprobación de producción. No copiar volcados NVS, archivos personales, SSID, contraseñas, JWT, secretos o claves privadas a este proyecto.

## Archivos y comportamiento implementado

| Archivo | Función |
|---|---|
| `LumaTek_Distribution/LumaTek_Distribution.ino` | Entrada de sketch; evita lógica dependiente del autogenerador de prototipos |
| `LumaTek_Distribution/Firmware.cpp` | Configuración remota, controladores DHT/ADC, HTTPS y lotes derivados del laboratorio |
| `LumaTek_Distribution/usb_provisioning.h` | Protocolo USB, sesión breve, candidato Wi-Fi, desafío/canje y recuperación |
| `LumaTek_Distribution/server_profile.h` | Perfil público de despliegue, vacío por defecto |
| `LumaTek_Distribution/sketch.yaml` | Plataforma y bibliotecas fijadas |
| `build.ps1` | Compilación local sin carga, publicación ni programación de eFuses |
| `tests/test_source_contract.py` | Regresión estática de código y separación del laboratorio |

La variante elimina SSID/contraseña compilados, dirección local y CA local. El perfil vacío **impide las peticiones API**: se deberá proporcionar un origen HTTPS público revisado y su cadena CA pública antes de ensayar la conexión. El servidor no es configurable desde un comando USB, para evitar enviar credenciales a un origen arbitrario.

Se conserva el DHT22 compartido en GPIO4, los pines ADC admitidos, los IDs y unidades remotos y los intervalos. Se conserva exactamente la fórmula existente **solo si hay calibración explícita**: `100 * (raw - dry) / (wet - dry)`, limitada a 0–100. Sin calibración se envía el entero RAW. La prueba estática compara el bloque de validación/configuración con el original, retirando únicamente sus mensajes de consola.

Se conserva el mismo cuerpo/UUID durante reintentos. Se añade fecha UTC de medición al lote para no transformar un reintento en una muestra nueva por su fecha de recepción. El backend ya admite ese campo. Los lotes siguen siendo RAM, no una cola histórica persistente; un reinicio puede perder un lote y no se promete recuperación offline ilimitada.

En caso de 401 se elimina la credencial local y se detiene adquisición autenticada hasta volver a vincular. No se añade riego, actuadores, OTA o comandos arbitrarios.

## Entorno reproducible propuesto

La fuente original solo declara core Arduino ESP32 **3.x** y ArduinoJson **v7**; no permite deducir sus versiones exactas. Estas son versiones **seleccionadas para el candidato**, no una afirmación sobre el entorno físico ya probado:

| Recurso | Versión/perfil fijado |
|---|---|
| Arduino CLI | 1.2.2, comprobado por el script |
| Placa/FQBN | `esp32:esp32:esp32` — ESP32 Dev Module, candidato para DevKit V1 |
| Arduino-ESP32 | 3.0.7 |
| ArduinoJson | 7.2.1 |
| Adafruit DHT sensor library | 1.4.6 |
| Adafruit Unified Sensor | 1.1.15 |
| USB-UART de aplicación | 115200 baudios |

Los [perfiles de Arduino CLI](https://docs.arduino.cc/arduino-cli/sketch-project-file) fijan las plataformas y bibliotecas. Referencias de versiones: [Arduino-ESP32](https://github.com/espressif/arduino-esp32/releases/tag/3.0.7), [ArduinoJson](https://github.com/bblanchon/ArduinoJson/releases/tag/v7.2.1), [DHT](https://github.com/adafruit/DHT-sensor-library/releases/tag/1.4.6), [Unified Sensor](https://github.com/adafruit/Adafruit_Sensor/releases/tag/1.1.15), [CLI](https://github.com/arduino/arduino-cli/releases/tag/v1.2.2).

La fijación de versiones permite repetir el intento de build, pero no acredita ausencia de vulnerabilidades ni equivalencia física. Antes de publicar hay que revisar avisos de seguridad y confirmar módulo/revisión de silicio, tamaño de flash y particiones reales. El FQBN usa los valores por defecto de esa plataforma; no se afirma que sean los de la placa de laboratorio. La reproducibilidad binaria bit a bit aún no se comprobó.

No hay Arduino CLI, PlatformIO, ESP-IDF ni compilador C++ encontrados en PATH de esta sesión. No se instalaron herramientas o dependencias automáticamente y no se ejecutó ninguna carga.

Con Arduino CLI 1.2.2 instalado y el entorno autorizado:

```powershell
powershell -ExecutionPolicy Bypass -File firmware/esp32-devkit-v1/distribucion/build.ps1
```

El perfil podrá descargar dependencias fijadas durante el build. Las imágenes irán a `.build/`, ignorada en Git, y se mostrarán sus hashes. El script no llama a `upload`, no genera un manifiesto ni publica. Confirmar en los artefactos reales el bootloader, particiones, aplicación, offsets y parámetros de escritura; no inferirlos de este README. No habilitar descargas si solo hay código fuente sin build válido.

## Protocolo de configuración USB v1

NDJSON UTF-8, una petición/respuesta JSON por línea, máximo 1024 bytes por petición. Primera iteración para redes protegidas con SSID de 1–32 bytes y contraseña de 8–63 bytes. No soporta todavía redes empresariales, redes abiertas ni todas las variantes WPA. No hay portal HTTP, AP, Bluetooth ni credenciales Wi-Fi en un `.ino` distribuido.

Al encender/reiniciar físicamente, se abre **una ventana de tres minutos**, sin reapertura remota. Es un mecanismo experimental de presencia local, no una prueba criptográfica de propiedad. No se reserva a ciegas GPIO0 ni otro pin como botón de recuperación. Un gesto dedicado requiere revisar el hardware y probarlo después.

`hello` devuelve versión, modelo, banderas de estado y un nonce efímero solo durante la ventana. El resto de comandos requiere `nonce` coincidente; no devuelve contraseña, SSID, credencial permanente ni secreto de canje. El nonce no debe confundirse con una credencial IoT ni persistirse en la web.

| Comando | Parámetros adicionales | Resultado |
|---|---|---|
| `hello` | Ninguno | Estado no secreto y nonce de sesión si sigue abierta |
| `wifi.set` | `ssid`, `password` | Candidato en RAM; validación Wi-Fi/reloj/HTTPS antes de persistir |
| `pair.start` | Ninguno | ID del desafío y prueba temporal del operador |
| `pair.exchange` | Ninguno | Canje interno tras aprobación web; devuelve solo resultado |
| `wifi.forget` | Ninguno | Olvida el perfil Wi-Fi, conserva identidad IoT |
| `identity.forget` | Ninguno | Olvida identidad local; no revoca la del servidor |
| `session.close` | Ninguno | Cierra sesión y limpia el buffer/nonce/desafío |

Las tramas demasiado grandes se descartan completas, sin ejecutar un sufijo truncado. Los campos desconocidos y mensajes anidados fuera del límite se rechazan. El canal solo procesa una cantidad acotada de bytes por iteración. No existe un comando para leer secretos o configurar pines de salida.

Wi-Fi se valida con la conexión real y una respuesta 401 de `GET /api/iot/config` **sin token**, que verifica alcance HTTPS sin entregar identidad. El perfil persistido anterior se conserva hasta guardar un candidato validado. La validación tiene plazo de 60 segundos y se cancela al cerrar la sesión. Un reinicio puede recuperar el perfil previo si había persistencia cifrada; en RAM no se conserva tras perder alimentación.

Limitaciones: HTTP del ESP32 aún usa las llamadas bloqueantes del laboratorio (hasta sus timeouts), el nonce y los objetos JSON usan memoria dinámica, y limpiar buffers/String no garantiza borrado de todas las copias que hagan ArduinoJson/SDK/heap. Deben revisarse esas copias, el parser, tiempos y resiliencia antes de aprobar seguridad. Los mensajes de arranque del core/ROM pueden preceder al protocolo; el futuro cliente debe negociar `hello` y no tratar ese texto como una respuesta válida.

## Almacenamiento y TLS

El Wi-Fi SDK se configura con `WiFi.persistent(false)`. La persistencia de esta variante solo se intenta si el SDK fue construido con `CONFIG_NVS_ENCRYPTION` y el chip informa Flash Encryption activa. En otro caso **todo queda en RAM** y los estados lo indican. No se sustituye esa condición con una escritura en Preferences sin cifrar.

Además, se exige `WIFI_STORAGE_RAM` y se vacía la configuración Wi-Fi del driver en RAM al iniciar, para no adoptar perfiles de otro sketch. No se borra el espacio NVS del laboratorio. Si esa preparación falla, no se permite configurar Wi-Fi ni operar el cliente autenticado.

El core Arduino precompilado habitual puede no ofrecer esa configuración. Hay que preparar/verificar un SDK apropiado (por ejemplo Arduino como componente ESP-IDF), particiones de NVS/claves y proceso de fabricación para la persistencia de producción. Esta guarda es preliminar: no prueba por sí sola la integridad de todas las particiones ni su recuperación. No se queman eFuses ni se activa Flash Encryption/Secure Boot por software en esta entrega.

Wi-Fi se guarda como un registro y la identidad como otro; ambos llevan el origen HTTPS. Una identidad persistida no se reutiliza para un perfil de servidor distinto. No se guarda el nonce USB ni el secreto temporal de canje.

Todas las peticiones conservan validación de CA y hostname y desactivan redirecciones. No hay `setInsecure`. Se mantiene el mecanismo NTP del laboratorio y su comprobación básica de reloj: sigue pendiente reforzar arranque/recuperación de reloj confiable, renovar raíces y revisar TLS en hardware. No se publica ninguna CA de laboratorio ni clave privada; el perfil público viene vacío deliberadamente.

## Cambios necesarios en LumaTek-Web

**No implementados ni activados en esta entrega:**

1. Asistente Web Serial autorizado dentro de Instalación IoT: selección de puerto, handshake, formulario Wi-Fi sin persistencia/logs/analítica, estados de validación y cierre seguro.
2. Selección de dispositivo propio y confirmación administrativa; recepción interna de `pairing_id`/`operator_proof` y llamada autenticada a la ruta existente de aprobación. No enviar el JWT al ESP32 ni mostrar el secreto de intercambio.
3. Tras aprobar, invocar `pair.exchange` por USB respetando el intervalo mínimo del cliente de 11 segundos y los límites API. El secreto de intercambio se mantiene en el ESP32. No se necesita cambiar contratos Laravel para esto.
4. Manejo de expiración/cierre y de respuesta de canje perdida: la credencial del servidor no se puede recuperar por hash; guiar revocación y nueva vinculación. Una aprobación pendiente abandonada también puede requerir revocación o esperar vencimiento antes de aprobar otra.
5. Integración ESP Web Tools y manifiesto con artefactos/offsets reales, compatibilidad del navegador, cierre/liberación del puerto y pruebas de sesión autorizada en cada descarga.

Las rutas web, API y catálogo permanecen como estaban. No se añadió un asistente parcialmente operativo a la página ni se habilitó el botón USB. El código USB de esta carpeta es una interfaz preparada para ese desarrollo, no una funcionalidad ya disponible al agricultor.

## Pruebas y aprobación pendiente

Sin hardware:

```powershell
python -X utf8 -m unittest discover -s firmware/esp32-devkit-v1/distribucion/tests -v
```

Son **pruebas estáticas**, no una compilación ni un simulador del ESP32. Comprueban hash del laboratorio, preservación de la validación de sensores, separación de CA/URL, ausencia de salida de secretos permanentes, restricciones USB, guardas de persistencia, ausencia de actuadores y versiones fijadas.

Antes de habilitar descargas, compilar realmente y ensayar en **otro ESP32** sin relés ni bombas: revisión de placa y particiones; Wi-Fi correcto/incorrecto/cambio/corte de energía; sesión/nonce/overflow/cierre/timeout; TLS inválido y nombre incorrecto; DHT22/ADC RAW/calibración; cambio de intervalo 60↔300; deduplicación y antigüedad de lotes; revocación; canje perdido; NVS cifrada real y perfil RAM; aislamiento de dos empresas; estabilidad prolongada. Usar un backend HTTPS de pruebas autorizado aparte, no las bases actuales.

Conservar evidencia saneada, hash del build exacto y revisión de seguridad. Solo después registrar aprobación y artefactos reales en el catálogo. Esta carpeta no concede esa aprobación.

Resultados de esta entrega: **7 pruebas estáticas Python aprobadas** y **40 pruebas Laravel / 681 aserciones aprobadas**, estas últimas exclusivamente con SQLite `:memory:`. La comprobación de hash confirma el laboratorio intacto. El intento de ejecutar el script de build fue bloqueado por la política de ejecución de PowerShell; no se cambió esa política. Tampoco se encontró Arduino CLI en PATH. Por tanto no hay compilación C++ realizada ni binarios generados, y estas pruebas no sustituyen la compilación ni el ensayo físico.
