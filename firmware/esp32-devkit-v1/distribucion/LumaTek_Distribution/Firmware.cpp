/* Experimental distribution candidate. NOT compiled or approved for deployment.
 * Derived from laboratory SHA256 48f41169c0549600f419ff880980b1ec5845f4a6ffded81f45ca15e092220fb2.
 * The laboratory source remains unchanged. No Wi-Fi credentials or laboratory CA/origin.
 */
#include <Arduino.h>
#include <WiFi.h>
#include <WiFiClientSecure.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <Preferences.h>
#include <DHT.h>
#include <esp_system.h>
#include <esp_wifi.h>
#include <esp_flash_encrypt.h>
#include <math.h>
#include <ctype.h>
#include <time.h>
#include "server_profile.h"

void wipeString(String& value) {
  for (size_t i = 0; i < value.length(); ++i) value.setCharAt(i, '\0');
  value = "";
}
bool wifiRamStorageReady = false;

bool serverProfileReady() {
  const String origin(kServerOrigin);
  return origin.startsWith("https://") && origin.length() > 8 &&
    origin.indexOf('/', 8) < 0 && origin.indexOf('@') < 0 && origin.indexOf('?') < 0 &&
    origin.indexOf('#') < 0 && origin.indexOf('\n') < 0 &&
    String(kTrustedRootCa).indexOf("-----BEGIN CERTIFICATE-----") >= 0;
}
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

  if (!serverProfileReady()) return -103;
  WiFiClientSecure client;
  client.setCACert(kTrustedRootCa);
  HTTPClient http;
  http.setFollowRedirects(HTTPC_DISABLE_FOLLOW_REDIRECTS);
  http.setConnectTimeout(8000);
  http.setTimeout(12000);
  String url = String(kServerOrigin) + path;
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
  wipeString(exchangeSecret);
  pairingStartedAt = 0;
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
    /* Diagnostic omitted from the USB data channel. */
    return false;
  }
  String board = doc["board_model"] | "";
  String version = doc["configuration_version"] | "";
  int intervalSec = doc["measurement_interval"] | 0;
  JsonArrayConst arr = doc["sensors"].as<JsonArrayConst>();
  if (board != "esp32_devkit_v1" || version.length() != 64 ||
      intervalSec < 10 || intervalSec > 3600 || arr.isNull() ||
      arr.size() > MAX_SENSORS) {
    /* Diagnostic omitted from the USB data channel. */
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
      /* Diagnostic omitted from the USB data channel. */
      return false;
    }
    for (size_t j = 0; j < proposedCount; j++) {
      if (proposed[j].id == id) {
        /* Diagnostic omitted from the USB data channel. */
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
        /* Diagnostic omitted from the USB data channel. */
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
          /* Diagnostic omitted from the USB data channel. */
          return false;
        }
        s.calibrated = true;
      } else if (unit != "raw") {
        /* Diagnostic omitted from the USB data channel. */
        return false;
      }
    } else {
      /* Diagnostic omitted from the USB data channel. */
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
  /* Diagnostic omitted from the USB data channel. */
  for (size_t i = 0; i < sensorCount; i++) {
    /* Diagnostic omitted from the USB data channel. */
  }
  return true;
}

bool descargarConfiguracion() {
  if (deviceCredential.isEmpty()) {
    /* Diagnostic omitted from the USB data channel. */
    return false;
  }
  String response;
  int code = peticionHttps("GET", "/api/iot/config", "", true, response);
  /* Diagnostic omitted from the USB data channel. */
  if (code == 200) {
    return aplicarConfiguracion(response);
  }
  if (code == 401) {
    wipeString(deviceCredential);
    if (preferencesReady) preferences.remove("identity");
    configurationLoaded = false;
    pendingBatchBody = "";
    /* Diagnostic omitted from the USB data channel. */
  } else if (code == 422 || code == 403) {
    configurationLoaded = false;
    /* Diagnostic omitted from the USB data channel. */
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
  char measuredAt[21];
  time_t measured = time(nullptr);
  struct tm utc;
  gmtime_r(&measured, &utc);
  strftime(measuredAt, sizeof(measuredAt), "%Y-%m-%dT%H:%M:%SZ", &utc);
  batch["recorded_at"] = measuredAt;
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
        /* Diagnostic omitted from the USB data channel. */
        continue;
      }
      if (s.kind == KIND_AMBIENT_HUMIDITY && (value < 0 || value > 100)) {
        /* Diagnostic omitted from the USB data channel. */
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
    /* Diagnostic omitted from the USB data channel. */
  }
  if (readings.size() == 0) {
    /* Diagnostic omitted from the USB data channel. */
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
  /* Diagnostic omitted from the USB data channel. */
  if (code >= 200 && code < 300) {
    /* Diagnostic omitted from the USB data channel. */
    pendingBatchBody = "";
  } else if (code == 401 || code == 403 || code == 409 || code == 422) {
    if (code == 401) {
      wipeString(deviceCredential);
      if (preferencesReady) preferences.remove("identity");
      configurationLoaded = false;
    }
    /* Diagnostic omitted from the USB data channel. */
    pendingBatchBody = "";
    // Una futura consulta de configuracion puede aclarar sensores desactivados.
    lastConfigCheck = millis() - CONFIG_REFRESH_MS;
  } else {
    /* Diagnostic omitted from the USB data channel. */
  }
}


#include "usb_provisioning.h"
