/*
 * LumaTek IoT - ESP32 DevKit V1 - Firmware de laboratorio (Fases 1 y 2)
 *
 * - WiFi + NTP + HTTPS validado con CA local de Caddy
 * - Vinculacion por desafio con aprobacion desde LumaTek (monitor serial USB)
 * - Credencial guardada en Preferences/NVS para sobrevivir reinicios
 * - Descarga y aplica /api/iot/config (IDs, GPIO, intervalos, calibracion)
 * - Lee DHT22 y sensor analogo del suelo
 * - Envia POST /api/iot/readings con UUID por lote
 * - Reintenta el MISMO cuerpo y UUID si falla una solicitud temporalmente
 *
 * ATENCION: Preferences/NVS no esta cifrada por defecto. Esta version NO
 * constituye un aprovisionamiento seguro de produccion. Configurar cifrado
 * NVS/flash, arranque seguro, instalacion WiFi y servidor antes de desplegar.
 * NO poner contrasenas reales en GitHub.
 *
 * Bibliotecas: ArduinoJson v7, DHT sensor library (Adafruit), dependencias
 * Adafruit Unified Sensor, y core ESP32 Arduino 3.x.
 */

#include <Arduino.h>
#include <WiFi.h>
#include <WiFiClientSecure.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <Preferences.h>
#include <DHT.h>
#include <esp_system.h>
#include <math.h>
#include <time.h>

// CONFIGURAR SOLO EN EL EQUIPO LOCAL (no subir credenciales al repositorio).
const char* WIFI_SSID = "CONFIGURAR_WIFI";
const char* WIFI_PASSWORD = "CONFIGURAR_PASSWORD";

// Entorno de pruebas; la direccion definitiva sera configurable en produccion.
const char* IOT_BASE_URL = "https://192.168.1.175:8443";

// Certificado PUBLICO de la CA local de Caddy utilizada en esta prueba.
const char* CADDY_ROOT_CA = R"EOF(
-----BEGIN CERTIFICATE-----
MIIBpTCCAUqgAwIBAgIRAPqO0TdMaNZaCddbvoB3N8UwCgYIKoZIzj0EAwIwMDEu
MCwGA1UEAxMlQ2FkZHkgTG9jYWwgQXV0aG9yaXR5IC0gMjAyNiBFQ0MgUm9vdDAe
Fw0yNjEwMjMwMjU1MDNaFw0zNjA4MzEwMjU1MDNaMDAxLjAsBgNVBAMTJUNhZGR5
IExvY2FsIEF1dGhvcml0eSAtIDIwMjYgRUNDIFJvb3QwWTATBgcqhkjOPQIBBggq
hkjOPQMBBwNCAARcalm8HQXADGehDRWFhVMY/QSUaAD7XewvHgYuwutiwTf1mTLq
wH12esh4mTYGfIjDubtAA7nGk5Vby9tdV8ABo0UwQzAOBgNVHQ8BAf8EBAMCAQYw
EgYDVR0TAQH/BAgwBgEB/wIBATAdBgNVHQ4EFgQUsSxbueIyUpos+ho1qW7MHYdt
aiwwCgYIKoZIzj0EAwIDSQAwRgIhAMypPL4k7kt4hAtNrnlBPwi6XBgi5MgBx2pQ
cgiLAvsXAiEAp+BoD7CbM7FxM00nczCZngqEgLSROaKZygamHznB6dw=
-----END CERTIFICATE-----
)EOF";

const size_t MAX_SENSORS = 16;
const size_t MAX_DHT = 4;
const unsigned long CONFIG_REFRESH_MS = 60000UL;
const unsigned long NETWORK_RETRY_MS = 10000UL;
const unsigned long BATCH_RETRY_MS = 20000UL;
const unsigned long FIRST_READING_DELAY_MS = 3000UL;
const unsigned long PAIRING_EXPIRY_MS = 600000UL;

enum SensorKind { KIND_TEMPERATURE, KIND_AMBIENT_HUMIDITY, KIND_SOIL };

struct SensorConfig {
  int id = 0;
  SensorKind kind = KIND_TEMPERATURE;
  uint8_t pin = 0;
  String unit;
  bool calibrated = false;
  int dry = 0;
  int wet = 0;
};

struct DhtUnit {
  uint8_t pin = 0;
  DHT* instance = nullptr;
  float temperature = NAN;
  float humidity = NAN;
};

SensorConfig sensors[MAX_SENSORS];
size_t sensorCount = 0;
DhtUnit dhtUnits[MAX_DHT];
size_t dhtCount = 0;
unsigned long measurementIntervalMs = 60000UL;
unsigned long lastConfigCheck = 0;
unsigned long nextReadingAt = 0;
unsigned long lastWiFiRetry = 0;
unsigned long lastBatchAttempt = 0;
unsigned long pairingStartedAt = 0;
String configurationVersion;
String deviceCredential;
String pairingId;
String exchangeSecret;
String pendingBatchBody;
Preferences preferences;
bool preferencesReady = false;
bool configurationLoaded = false;
bool wifiWasConnected = false;
bool ntpWasReady = false;

bool tieneHoraValida() {
  return time(nullptr) > 1700000000;
}

bool wifiYHoraDisponibles() {
  if (WiFi.status() != WL_CONNECTED) {
    return false;
  }
  return tieneHoraValida();
}

// Cada request valida TLS con CA; no desactivar verificacion. No imprimir Authorization.
int peticionHttps(const String& method, const String& path,
                  const String& requestBody, bool authenticated,
                  String& responseBody) {
  responseBody = "";
  if (!wifiYHoraDisponibles()) return -100;
  if (authenticated && deviceCredential.isEmpty()) return -101;

  WiFiClientSecure client;
  client.setCACert(CADDY_ROOT_CA);
  HTTPClient http;
  http.setConnectTimeout(8000);
  http.setTimeout(12000);
  String url = String(IOT_BASE_URL) + path;
  if (!http.begin(client, url)) return -102;

  http.addHeader("Accept", "application/json");
  if (authenticated) {
    http.addHeader("Authorization", "Bearer " + deviceCredential);
  }
  int code;
  if (method == "POST") {
    http.addHeader("Content-Type", "application/json");
    code = http.POST(requestBody);
  } else {
    code = http.GET();
  }
  if (code > 0) responseBody = http.getString();
  http.end();
  return code;
}

void olvidarDesafio() {
  pairingId = "";
  exchangeSecret = "";
  pairingStartedAt = 0;
}

void solicitarDesafio() {
  if (!deviceCredential.isEmpty()) {
    Serial.println("Ya hay credencial local. Revoca primero en LumaTek.");
    return;
  }
  olvidarDesafio();
  String response;
  int code = peticionHttps("POST", "/api/iot/pairings", "{}", false, response);
  Serial.printf("Desafio HTTP: %d\n", code);
  if (code != 201) return;

  JsonDocument doc;
  if (deserializeJson(doc, response)) {
    Serial.println("Error al interpretar desafio JSON.");
    return;
  }
  String id = doc["pairing_id"] | "";
  String proof = doc["operator_proof"] | "";
  String secret = doc["exchange_secret"] | "";
  if (id.isEmpty() || proof.isEmpty() || secret.isEmpty()) {
    Serial.println("Desafio incompleto.");
    return;
  }
  pairingId = id;
  exchangeSecret = secret;
  pairingStartedAt = millis();
  Serial.println("=== VINCULACION LUMATEK (SOLO USB LOCAL) ===");
  Serial.print("ID desafio: "); Serial.println(pairingId);
  Serial.print("Prueba operador: "); Serial.println(proof);
  Serial.println("Introduce ambos en Vincular/renovar desde LumaTek.");
  Serial.println("Despues escribe 'c'. El desafio vence en 10 minutos.");
  // No imprimir exchangeSecret ni incluirlo en logs.
}

void canjearCredencial() {
  if (pairingId.isEmpty() || exchangeSecret.isEmpty() ||
      millis() - pairingStartedAt >= PAIRING_EXPIRY_MS) {
    olvidarDesafio();
    Serial.println("Sin desafio vigente. Solicita uno con 'v'.");
    return;
  }
  JsonDocument doc;
  doc["pairing_id"] = pairingId;
  doc["exchange_secret"] = exchangeSecret;
  String request;
  serializeJson(doc, request);
  String response;
  int code = peticionHttps("POST", "/api/iot/pairings/exchange", request,
                           false, response);
  Serial.printf("Canje HTTP: %d\n", code);
  if (code != 200) return;

  JsonDocument result;
  if (deserializeJson(result, response)) {
    Serial.println("Error al interpretar respuesta de canje.");
    return;
  }
  String credential = result["credential"] | "";
  if (credential.length() != 64) {
    Serial.println("Credencial con formato inesperado.");
    return;
  }
  deviceCredential = credential;
  olvidarDesafio();

  if (preferencesReady && preferences.putString("token", deviceCredential) > 0) {
    Serial.println("ESP32 vinculado. Credencial guardada en NVS (LAB: NO CIFRADA).");
  } else {
    Serial.println("ESP32 vinculado SOLO en RAM: no se pudo guardar credencial.");
  }
  configurationLoaded = false;
  lastConfigCheck = millis() - CONFIG_REFRESH_MS;
}

bool gpioAdc1(uint8_t pin) {
  return pin == 32 || pin == 33 || pin == 34 ||
         pin == 35 || pin == 36 || pin == 39;
}

bool gpioDhtPermitido(uint8_t pin) {
  // Compatible con el contrato inicial del backend: DHT22 sobre GPIO4.
  // Soporte para otros GPIO requiere validacion tambien en Laravel.
  return pin == 4;
}

void limpiarDht() {
  for (size_t i = 0; i < dhtCount; i++) {
    delete dhtUnits[i].instance;
    dhtUnits[i].instance = nullptr;
  }
  dhtCount = 0;
}

bool aplicarConfiguracion(const String& body) {
  JsonDocument doc;
  DeserializationError error = deserializeJson(doc, body);
  if (error) {
    Serial.println("Configuracion JSON invalida.");
    return false;
  }
  String board = doc["board_model"] | "";
  String version = doc["configuration_version"] | "";
  int intervalSec = doc["measurement_interval"] | 0;
  JsonArrayConst arr = doc["sensors"].as<JsonArrayConst>();
  if (board != "esp32_devkit_v1" || version.length() != 64 ||
      intervalSec < 10 || intervalSec > 3600 || arr.isNull() ||
      arr.size() > MAX_SENSORS) {
    Serial.println("Configuracion fuera del contrato de LumaTek.");
    return false;
  }
  if (configurationLoaded && version == configurationVersion) return true;

  SensorConfig proposed[MAX_SENSORS];
  size_t proposedCount = 0;
  int proposedDhtPins[MAX_DHT];
  size_t proposedDhtCount = 0;
  bool usedAnalog[40] = {};
  bool seenTemp = false;
  bool seenHumidity = false;

  for (JsonObjectConst item : arr) {
    int id = item["id"] | 0;
    int pinNumber = item["pin"] | -1;
    String driver = item["driver"] | "";
    String measurement = item["measurement"] | "";
    String unit = item["unit"] | "";
    if (id <= 0 || pinNumber < 0 || pinNumber > 39) {
      Serial.println("Sensor con ID o pin invalido.");
      return false;
    }
    for (size_t j = 0; j < proposedCount; j++) {
      if (proposed[j].id == id) {
        Serial.println("Sensor duplicado en configuracion.");
        return false;
      }
    }
    SensorConfig& s = proposed[proposedCount];
    s.id = id;
    s.pin = (uint8_t)pinNumber;
    s.unit = unit;

    if (driver == "DHT22" && gpioDhtPermitido(s.pin)) {
      if (measurement == "temperature" && unit == "\xC2\xB0" "C") {
        if (seenTemp) return false;
        seenTemp = true;
        s.kind = KIND_TEMPERATURE;
      } else if (measurement == "ambient_humidity" && unit == "%") {
        if (seenHumidity) return false;
        seenHumidity = true;
        s.kind = KIND_AMBIENT_HUMIDITY;
      } else {
        Serial.println("Medicion DHT22 incompatible.");
        return false;
      }
      bool exists = false;
      for (size_t j = 0; j < proposedDhtCount; j++) {
        if (proposedDhtPins[j] == s.pin) exists = true;
      }
      if (!exists) {
        if (proposedDhtCount >= MAX_DHT) return false;
        proposedDhtPins[proposedDhtCount++] = s.pin;
      }
    } else if (driver == "analog_soil" && measurement == "soil_humidity" &&
               gpioAdc1(s.pin)) {
      if (usedAnalog[s.pin]) return false;
      usedAnalog[s.pin] = true;
      s.kind = KIND_SOIL;
      JsonVariantConst cal = item["calibration"];
      if (!cal.isNull()) {
        s.dry = cal["dry"] | -1;
        s.wet = cal["wet"] | -1;
        if (unit != "%" || s.dry < 0 || s.dry > 4095 ||
            s.wet < 0 || s.wet > 4095 || s.dry == s.wet) {
          Serial.println("Calibracion del suelo invalida.");
          return false;
        }
        s.calibrated = true;
      } else if (unit != "raw") {
        Serial.println("Sensor RAW sin unidad raw.");
        return false;
      }
    } else {
      Serial.println("Driver, pin o medicion no compatible.");
      return false;
    }
    proposedCount++;
  }

  // Validacion cruzada: pin analogico no puede compartir la linea DHT.
  for (size_t i = 0; i < proposedDhtCount; i++) {
    if (proposedDhtPins[i] < 40 && usedAnalog[proposedDhtPins[i]]) return false;
  }

  // Solo cambiamos el estado real si TODO el JSON es compatible.
  limpiarDht();
  sensorCount = proposedCount;
  for (size_t i = 0; i < sensorCount; i++) sensors[i] = proposed[i];
  for (size_t i = 0; i < proposedDhtCount; i++) {
    dhtUnits[i].pin = (uint8_t)proposedDhtPins[i];
    dhtUnits[i].instance = new DHT(dhtUnits[i].pin, DHT22);
    if (!dhtUnits[i].instance) {
      limpiarDht();
      sensorCount = 0;
      configurationLoaded = false;
      return false;
    }
    dhtUnits[i].instance->begin();
    dhtCount++;
  }
  analogReadResolution(12);
  for (size_t i = 0; i < sensorCount; i++) {
    if (sensors[i].kind == KIND_SOIL) pinMode(sensors[i].pin, INPUT);
  }
  measurementIntervalMs = (unsigned long)intervalSec * 1000UL;
  configurationVersion = version;
  configurationLoaded = true;
  nextReadingAt = millis() + FIRST_READING_DELAY_MS;
  Serial.printf("Configuracion aplicada: %u sensores, cada %d s.\n",
                (unsigned int)sensorCount, intervalSec);
  for (size_t i = 0; i < sensorCount; i++) {
    Serial.printf("  Sensor %d, GPIO%d, unidad %s\n",
                  sensors[i].id, sensors[i].pin, sensors[i].unit.c_str());
  }
  return true;
}

bool descargarConfiguracion() {
  if (deviceCredential.isEmpty()) {
    Serial.println("No hay credencial. Vincula con 'v'.");
    return false;
  }
  String response;
  int code = peticionHttps("GET", "/api/iot/config", "", true, response);
  Serial.printf("Configuracion HTTP: %d\n", code);
  if (code == 200) {
    return aplicarConfiguracion(response);
  }
  if (code == 401) {
    Serial.println("Credencial revocada/no reconocida. Revoca en LumaTek y vincula de nuevo.");
  } else if (code == 422 || code == 403) {
    Serial.println("Configuracion, plan o estado del dispositivo restringido.");
  }
  return false;
}

DhtUnit* buscarDht(uint8_t pin) {
  for (size_t i = 0; i < dhtCount; i++) {
    if (dhtUnits[i].pin == pin) return &dhtUnits[i];
  }
  return nullptr;
}

String uuidV4() {
  uint8_t b[16];
  esp_fill_random(b, sizeof(b));
  b[6] = (b[6] & 0x0f) | 0x40;
  b[8] = (b[8] & 0x3f) | 0x80;
  char id[37];
  snprintf(id, sizeof(id),
           "%02x%02x%02x%02x-%02x%02x-%02x%02x-%02x%02x-%02x%02x%02x%02x%02x%02x",
           b[0], b[1], b[2], b[3], b[4], b[5], b[6], b[7],
           b[8], b[9], b[10], b[11], b[12], b[13], b[14], b[15]);
  return String(id);
}

bool prepararLote() {
  if (!configurationLoaded || sensorCount == 0) return false;
  JsonDocument batch;
  batch["batch_id"] = uuidV4();
  JsonArray readings = batch["readings"].to<JsonArray>();

  for (size_t i = 0; i < dhtCount; i++) {
    dhtUnits[i].temperature = dhtUnits[i].instance->readTemperature();
    dhtUnits[i].humidity = dhtUnits[i].instance->readHumidity();
  }

  for (size_t i = 0; i < sensorCount; i++) {
    const SensorConfig& s = sensors[i];
    float value = NAN;
    if (s.kind == KIND_TEMPERATURE || s.kind == KIND_AMBIENT_HUMIDITY) {
      DhtUnit* d = buscarDht(s.pin);
      if (d) {
        value = s.kind == KIND_TEMPERATURE ? d->temperature : d->humidity;
      }
      if (isnan(value)) {
        Serial.printf("Fallo de lectura DHT22, sensor %d.\n", s.id);
        continue;
      }
      if (s.kind == KIND_AMBIENT_HUMIDITY && (value < 0 || value > 100)) {
        Serial.println("Humedad ambiental fuera de rango; omitida.");
        continue;
      }
    } else if (s.kind == KIND_SOIL) {
      int raw = analogRead(s.pin);
      if (raw < 0 || raw > 4095) continue;
      if (s.calibrated) {
        value = 100.0f * (raw - s.dry) / (s.wet - s.dry);
        if (value < 0) value = 0;
        if (value > 100) value = 100;
      } else {
        value = (float)raw;
      }
    }
    if (!isfinite(value)) continue;
    // Unidad y tipo proceden de la configuracion validada, no de constantes de IDs.
    JsonObject r = readings.add<JsonObject>();
    r["sensor_id"] = s.id;
    r["measurement"] = s.kind == KIND_TEMPERATURE ? "temperature"
                      : s.kind == KIND_AMBIENT_HUMIDITY ? "ambient_humidity"
                      : "soil_humidity";
    r["unit"] = s.unit;
    if (s.kind == KIND_SOIL && !s.calibrated) {
      r["value"] = (int)value;
    } else {
      r["value"] = roundf(value * 100.0f) / 100.0f;
    }
    Serial.printf("Sensor %d (GPIO%d): %.2f %s\n",
                  s.id, s.pin, value, s.unit.c_str());
  }
  if (readings.size() == 0) {
    Serial.println("No hubo lecturas validas; no se envia lote.");
    return false;
  }
  pendingBatchBody = "";
  serializeJson(batch, pendingBatchBody);
  return !pendingBatchBody.isEmpty();
}

void enviarLotePendiente() {
  if (pendingBatchBody.isEmpty()) return;
  lastBatchAttempt = millis();
  String response;
  int code = peticionHttps("POST", "/api/iot/readings",
                           pendingBatchBody, true, response);
  Serial.printf("POST /api/iot/readings -> HTTP %d\n", code);
  if (code >= 200 && code < 300) {
    Serial.println("Lote confirmado por LumaTek.");
    pendingBatchBody = "";
  } else if (code == 401 || code == 403 || code == 409 || code == 422) {
    Serial.println("Lote rechazado permanentemente (revisar credencial, plan o configuracion).");
    pendingBatchBody = "";
    // Una futura consulta de configuracion puede aclarar sensores desactivados.
    lastConfigCheck = millis() - CONFIG_REFRESH_MS;
  } else {
    Serial.println("Error temporal: se reintentara el MISMO lote (mismo UUID y JSON).");
  }
}

void imprimirAyuda() {
  Serial.println("\nComandos seriales (laboratorio):");
  Serial.println("v = solicitar desafio (sin credencial local)");
  Serial.println("c = canjear despues de aprobar en LumaTek");
  Serial.println("g = consultar configuracion inmediatamente");
  Serial.println("m = medir y enviar una vez (si no hay lote pendiente)");
  Serial.println("x = olvidar credencial local (REVOCAR primero en la web)");
  Serial.println("h = ayuda");
}

void manejarSerial() {
  while (Serial.available()) {
    char key = (char)Serial.read();
    if (key == 'v') solicitarDesafio();
    else if (key == 'c') canjearCredencial();
    else if (key == 'g') descargarConfiguracion();
    else if (key == 'm') {
      if (pendingBatchBody.isEmpty() && prepararLote()) enviarLotePendiente();
      else if (!pendingBatchBody.isEmpty()) enviarLotePendiente();
    } else if (key == 'x') {
      if (preferencesReady) preferences.remove("token");
      deviceCredential = "";
      olvidarDesafio();
      configurationLoaded = false;
      configurationVersion = "";
      pendingBatchBody = "";
      limpiarDht();
      sensorCount = 0;
      Serial.println("Credencial local borrada. Revocacion en Laravel es una operacion SEPARADA.");
    } else if (key == 'h') imprimirAyuda();
  }
}

void setup() {
  Serial.begin(115200);
  delay(1300);
  Serial.println("\n===== LUMATEK ESP32 IoT FASE 2 (LAB) =====");
  preferencesReady = preferences.begin("lumatek-iot", false);
  if (preferencesReady) {
    deviceCredential = preferences.getString("token", "");
  } else {
    Serial.println("AVISO: no se pudo abrir NVS. Credencial solo en RAM.");
  }
  if (deviceCredential.length() == 64) {
    Serial.println("Credencial local encontrada en NVS (no se imprime).");
  } else {
    deviceCredential = "";
    Serial.println("Dispositivo sin credencial. Debes vincularlo desde la web.");
  }
  WiFi.mode(WIFI_STA);
  WiFi.setAutoReconnect(true);
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
  configTime(0, 0, "pool.ntp.org", "time.google.com");
  Serial.println("Conectando Wi-Fi y sincronizando NTP...");
  imprimirAyuda();
}

void loop() {
  manejarSerial();
  if (WiFi.status() != WL_CONNECTED) {
    if (wifiWasConnected) {
      wifiWasConnected = false;
      Serial.println("Wi-Fi desconectado.");
    }
    if (millis() - lastWiFiRetry >= NETWORK_RETRY_MS) {
      lastWiFiRetry = millis();
      Serial.println("Wi-Fi sin conexion, intentando reconectar...");
      WiFi.reconnect();
    }
    delay(50);
    return;
  }
  if (!wifiWasConnected) {
    wifiWasConnected = true;
    Serial.print("Wi-Fi conectado. IP ESP32: ");
    Serial.println(WiFi.localIP());
  }
  if (!tieneHoraValida()) {
    static unsigned long lastTimeMessage = 0;
    if (millis() - lastTimeMessage >= 15000UL) {
      lastTimeMessage = millis();
      Serial.println("Esperando sincronizacion NTP antes de usar HTTPS...");
    }
    delay(50);
    return;
  }
  if (!ntpWasReady) {
    ntpWasReady = true;
    Serial.println("Hora NTP sincronizada. TLS listo.");
  }
  if (deviceCredential.isEmpty()) {
    delay(50);
    return;
  }

  const unsigned long nowMs = millis();
  if (lastConfigCheck == 0 || millis() - lastConfigCheck >= CONFIG_REFRESH_MS) {
    lastConfigCheck = millis();
    descargarConfiguracion();
    // Si la API falla, volver a intentar en aproximadamente un minuto.
    if (!configurationLoaded) {
      delay(50);
      return;
    }
  }

  // Si existe un lote cuya respuesta se perdio, reintentar sin generar otro.
  if (!pendingBatchBody.isEmpty()) {
    if (millis() - lastBatchAttempt >= BATCH_RETRY_MS) enviarLotePendiente();
    delay(50);
    return;
  }

  if (configurationLoaded && sensorCount > 0 &&
      (int32_t)(nowMs - nextReadingAt) >= 0) {
    if (prepararLote()) enviarLotePendiente();
    nextReadingAt = millis() + measurementIntervalMs;
  }
  delay(50);
}
