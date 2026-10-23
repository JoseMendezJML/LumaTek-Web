#pragma once

// Application protocol, not a serial monitor or an ESP Web Tools implementation.
// A physical power cycle opens ONE three-minute local USB window. No remote reopen.
static const uint32_t USB_WINDOW_MS = 180000;
static char usbFrame[1025] = {};
static size_t usbFrameLength = 0;
static bool usbDiscard = false;
static bool usbWasClosed = false;
static String usbNonce;
static uint32_t lastPairAttempt = 0;
static bool pairingRecoveryRequired = false;
static String pendingSsid, pendingPassword;
static uint32_t networkStarted = 0;
static uint32_t networkProbeAt = 0;
static bool networkCandidate = false;

void clearNetworkCandidate() {
  wipeString(pendingSsid);
  wipeString(pendingPassword);
  networkCandidate = false;
}

void usbResult(const char* event, const char* status, int http = 0);

bool persistIdentity() {
  if (!preferencesReady) return false;
  JsonDocument identity;
  identity["origin"] = kServerOrigin;
  identity["credential"] = deviceCredential;
  String record;
  serializeJson(identity, record);
  bool saved = preferences.putString("identity", record) > 0;
  wipeString(record);
  return saved;
}


bool usbWindowOpen() { return !usbWasClosed && millis() < USB_WINDOW_MS; }

void usbResult(const char* event, const char* status, int http) {
  JsonDocument reply;
  reply["protocol"] = 1;
  reply["event"] = event;
  reply["status"] = status;
  if (http) reply["http_status"] = http;
  serializeJson(reply, Serial);
  Serial.println();
}

void closeUsbProvisioning() {
  usbWasClosed = true;
  if (networkCandidate) { clearNetworkCandidate(); WiFi.disconnect(false, false); }
  memset(usbFrame, 0, sizeof(usbFrame));
  usbFrameLength = 0;
  wipeString(usbNonce);
  olvidarDesafio();
}

void usbHello() {
  JsonDocument reply;
  reply["protocol"] = 1;
  reply["event"] = "hello";
  reply["board_model"] = "esp32_devkit_v1";
  reply["version"] = "0.1.0-experimental";
  reply["window_open"] = usbWindowOpen();
  reply["profile_ready"] = serverProfileReady();
  reply["wifi_connected"] = WiFi.status() == WL_CONNECTED;
  reply["time_ready"] = tieneHoraValida();
  reply["paired"] = !deviceCredential.isEmpty();
  reply["configuration_loaded"] = configurationLoaded;
  reply["persistent_storage"] = preferencesReady;
  reply["pairing_recovery_required"] = pairingRecoveryRequired;
  if (usbWindowOpen()) reply["nonce"] = usbNonce;
  serializeJson(reply, Serial);
  Serial.println();
}

bool pairAttemptAllowed() {
  if (!wifiYHoraDisponibles() || !serverProfileReady()) {
    usbResult("pairing", "network_time_or_profile_unavailable");
    return false;
  }
  if (lastPairAttempt && millis() - lastPairAttempt < 11000) {
    usbResult("pairing", "retry_later");
    return false;
  }
  lastPairAttempt = millis();
  return true;
}

void usbPairStart() {
  if (!deviceCredential.isEmpty() || pairingRecoveryRequired) {
    usbResult("pairing", "revoke_existing_binding_first");
    return;
  }
  if (!pairAttemptAllowed()) return;
  olvidarDesafio();
  String response;
  int code = peticionHttps("POST", "/api/iot/pairings", "{}", false, response);
  JsonDocument result;
  bool valid = code == 201 && !deserializeJson(result, response);
  String proof = result["operator_proof"] | "";
  if (valid) {
    pairingId = result["pairing_id"].as<String>();
    exchangeSecret = result["exchange_secret"].as<String>();
    valid = !pairingId.isEmpty() && !proof.isEmpty() && exchangeSecret.length() == 64;
  }
  if (!valid || !usbWindowOpen()) {
    olvidarDesafio();
    usbResult("pairing", "challenge_failed", code);
  } else {
    pairingStartedAt = millis();
    JsonDocument reply;
    reply["protocol"] = 1;
    reply["event"] = "pairing.challenge";
    reply["pairing_id"] = pairingId;
    // Only the short-lived operator proof crosses USB. No exchange secret/token.
    reply["operator_proof"] = proof;
    reply["expires_in"] = 600;
    serializeJson(reply, Serial);
    Serial.println();
  }
  wipeString(proof);
  wipeString(response);
  result.clear();
}

void usbPairExchange() {
  if (!deviceCredential.isEmpty() || pairingRecoveryRequired) {
    usbResult("pairing", "revoke_existing_binding_first");
    return;
  }
  if (pairingId.isEmpty() || exchangeSecret.isEmpty() || millis() - pairingStartedAt >= PAIRING_EXPIRY_MS) {
    olvidarDesafio();
    usbResult("pairing", "challenge_required");
    return;
  }
  if (!pairAttemptAllowed()) return;
  JsonDocument payload;
  payload["pairing_id"] = pairingId;
  payload["exchange_secret"] = exchangeSecret;
  String request, response;
  serializeJson(payload, request);
  int code = peticionHttps("POST", "/api/iot/pairings/exchange", request, false, response);
  wipeString(request);
  payload.clear();
  if (code == 200) {
    JsonDocument result;
    if (!deserializeJson(result, response)) {
      String credential = result["credential"] | "";
      bool valid = credential.length() == 64;
      for (size_t i = 0; i < credential.length(); ++i) valid = valid && isxdigit((unsigned char)credential[i]);
      if (valid) {
        deviceCredential = credential;
        bool saved = persistIdentity();
        usbResult("pairing", saved ? "paired_persisted" : "paired_ram_only");
        configurationLoaded = false;
        lastConfigCheck = millis() - CONFIG_REFRESH_MS;
      } else pairingRecoveryRequired = true;
      wipeString(credential);
    } else pairingRecoveryRequired = true;
    olvidarDesafio();
    if (pairingRecoveryRequired) usbResult("pairing", "revoke_and_pair_again");
  } else if (code < 0 || code >= 500) {
    // The one-time response may have been lost after the server committed.
    pairingRecoveryRequired = true;
    olvidarDesafio();
    usbResult("pairing", "revoke_and_pair_again", code);
  } else {
    usbResult("pairing", "not_approved_or_rejected", code);
  }
  wipeString(response);
}

void handleUsbFrame() {
  JsonDocument command;
  // Mutable buffer enables zero-copy JSON strings; wipe it after every command.
  if (deserializeJson(command, usbFrame, DeserializationOption::NestingLimit(3))) {
    usbResult("protocol", "invalid_json");
    return;
  }
  if (!command.is<JsonObject>() || !command["cmd"].is<const char*>()) {
    usbResult("protocol", "invalid_command");
    return;
  }
  for (JsonPair field : command.as<JsonObject>()) {
    const char* key = field.key().c_str();
    if (strcmp(key, "cmd") && strcmp(key, "nonce") && strcmp(key, "ssid") && strcmp(key, "password")) {
      usbResult("protocol", "unexpected_field");
      return;
    }
  }
  const char* cmd = command["cmd"];
  if (!strcmp(cmd, "hello")) { usbHello(); return; }
  if (!usbWindowOpen() || !command["nonce"].is<const char*>() || usbNonce != command["nonce"].as<const char*>()) {
    usbResult("protocol", "closed_or_wrong_session");
    return;
  }
  if (!strcmp(cmd, "session.close")) { closeUsbProvisioning(); usbResult("session", "closed"); }
  else if (!strcmp(cmd, "wifi.set")) {
    if (!serverProfileReady() || !wifiRamStorageReady) { usbResult("wifi", "production_profile_or_ram_storage_required"); return; }
    const char* ssid = command["ssid"] | "";
    const char* password = command["password"] | "";
    // First iteration supports protected WPA/WPA2 networks only.
    if (!strlen(ssid) || strlen(ssid) > 32 || strlen(password) < 8 || strlen(password) > 63) {
      usbResult("wifi", "invalid_parameters"); return;
    }
    clearNetworkCandidate();
    pendingSsid = ssid;
    pendingPassword = password;
    networkCandidate = true;
    networkStarted = millis();
    networkProbeAt = 0;
    WiFi.disconnect(false, false);
    WiFi.begin(pendingSsid.c_str(), pendingPassword.c_str());
    usbResult("wifi", "validating_candidate");
  } else if (!strcmp(cmd, "pair.start")) usbPairStart();
  else if (!strcmp(cmd, "pair.exchange")) usbPairExchange();
  else if (!strcmp(cmd, "wifi.forget")) {
    clearNetworkCandidate();
    WiFi.disconnect(false, true);
    if (preferencesReady) preferences.remove("network");
    usbResult("wifi", "forgotten");
  } else if (!strcmp(cmd, "identity.forget")) {
    // This does NOT revoke the server credential; the web must revoke separately.
    if (preferencesReady) preferences.remove("identity");
    wipeString(deviceCredential);
    olvidarDesafio();
    pairingRecoveryRequired = false;
    pendingBatchBody = "";
    configurationLoaded = false;
    limpiarDht();
    sensorCount = 0;
    usbResult("identity", "forgotten_revoke_on_server");
  } else usbResult("protocol", "unsupported_command");
}

void pumpUsb() {
  if (!usbWindowOpen() && !usbWasClosed) closeUsbProvisioning();
  size_t budget = 256;
  while (Serial.available() && budget--) {
    char c = Serial.read();
    if (c == '\n') {
      if (!usbDiscard && usbFrameLength) { usbFrame[usbFrameLength] = 0; handleUsbFrame(); }
      else if (usbDiscard) usbResult("protocol", "frame_too_large");
      memset(usbFrame, 0, sizeof(usbFrame));
      usbFrameLength = 0;
      usbDiscard = false;
    } else if (!usbDiscard && c != '\r') {
      if (usbFrameLength < sizeof(usbFrame) - 1) usbFrame[usbFrameLength++] = c;
      else { memset(usbFrame, 0, sizeof(usbFrame)); usbDiscard = true; }
    }
  }
}

void setup() {
  Serial.begin(115200);
  WiFi.persistent(false); // Do not let the Wi-Fi SDK persist plaintext credentials.
  WiFi.mode(WIFI_STA);
  // Never adopt a Wi-Fi profile left by another sketch. Clear RAM only, not laboratory NVS.
  wifi_config_t emptyNetwork = {};
  wifiRamStorageReady = esp_wifi_set_storage(WIFI_STORAGE_RAM) == ESP_OK &&
    esp_wifi_set_config(WIFI_IF_STA, &emptyNetwork) == ESP_OK;
  WiFi.disconnect(false, false);
  WiFi.setAutoReconnect(true);
  usbNonce = uuidV4();
  // No persistence fallback to ordinary Preferences/NVS in this candidate.
#if defined(CONFIG_NVS_ENCRYPTION) && CONFIG_NVS_ENCRYPTION
  if (esp_flash_encryption_enabled()) preferencesReady = preferences.begin("lumatek-dist", false);
#endif
  if (preferencesReady && serverProfileReady() && wifiRamStorageReady) {
    String identityRecord = preferences.getString("identity", "");
    JsonDocument identity;
    if (!deserializeJson(identity, identityRecord) && identity["origin"].as<String>() == String(kServerOrigin)) {
      String candidate = identity["credential"] | "";
      bool valid = candidate.length() == 64;
      for (size_t i = 0; i < candidate.length(); ++i) valid = valid && isxdigit((unsigned char)candidate[i]);
      if (valid) deviceCredential = candidate;
      wipeString(candidate);
    }
    wipeString(identityRecord);
    String saved = preferences.getString("network", "");
    JsonDocument network;
    if (!deserializeJson(network, saved) && network["origin"].as<String>() == String(kServerOrigin)) {
      const char* ssid = network["ssid"] | "";
      const char* password = network["password"] | "";
      if (strlen(ssid) && strlen(ssid) <= 32 && strlen(password) >= 8 && strlen(password) <= 63) WiFi.begin(ssid, password);
    }
    wipeString(saved);
  }
  configTime(0, 0, "pool.ntp.org", "time.google.com");
  usbHello();
}

void validateNetworkCandidate() {
  if (!networkCandidate) return;
  if (!usbWindowOpen() || millis() - networkStarted > 60000) {
    clearNetworkCandidate();
    WiFi.disconnect(false, false);
    usbResult("wifi", "candidate_failed_previous_record_retained");
    return;
  }
  if (!wifiYHoraDisponibles()) return;
  if (networkProbeAt && millis() - networkProbeAt < NETWORK_RETRY_MS) return;
  networkProbeAt = millis();
  String response;
  // A 401 from this protected endpoint proves TLS/server reachability without sending a token.
  int code = peticionHttps("GET", "/api/iot/config", "", false, response);
  wipeString(response);
  if (code != 401) return;
  if (preferencesReady) {
    JsonDocument network;
    network["origin"] = kServerOrigin;
    network["ssid"] = pendingSsid;
    network["password"] = pendingPassword;
    String record;
    serializeJson(network, record);
    bool saved = preferences.putString("network", record) > 0;
    wipeString(record);
    usbResult("wifi", saved ? "ready_persisted" : "ready_ram_only");
  } else usbResult("wifi", "ready_ram_only");
  clearNetworkCandidate();
}

void loop() {
  pumpUsb();
  validateNetworkCandidate();
  if (!wifiRamStorageReady || !wifiYHoraDisponibles() || !serverProfileReady() || deviceCredential.isEmpty()) { delay(5); return; }
  if (!pairingId.isEmpty() && millis() - pairingStartedAt >= PAIRING_EXPIRY_MS) olvidarDesafio();
  const uint32_t nowMs = millis();
  if (lastConfigCheck == 0 || nowMs - lastConfigCheck >= CONFIG_REFRESH_MS) {
    lastConfigCheck = millis();
    descargarConfiguracion();
  }
  if (!configurationLoaded) { delay(5); return; }
  if (!pendingBatchBody.isEmpty()) {
    if (millis() - lastBatchAttempt >= BATCH_RETRY_MS) enviarLotePendiente();
  } else if (sensorCount && (int32_t)(nowMs - nextReadingAt) >= 0) {
    if (prepararLote()) enviarLotePendiente();
    nextReadingAt = millis() + measurementIntervalMs;
  }
  delay(5);
}
