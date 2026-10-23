"""Static regression checks only: not a C++ compiler or physical security validation."""
import hashlib
import re
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SKETCH = ROOT / "LumaTek_Distribution"
LAB = ROOT.parent / "laboratorio" / "LumaTek_IoT_ESP32_Fase2.ino"
ORIGINAL_SHA = "48f41169c0549600f419ff880980b1ec5845f4a6ffded81f45ca15e092220fb2"


def normalized_sensor_code(text):
    text = text[text.index("bool gpioAdc1("):text.index("bool descargarConfiguracion(")]
    text = re.sub(r"Serial\.(?:printf|println|print)\(.*?\);", "", text, flags=re.S)
    text = re.sub(r"/\*.*?\*/|//[^\n]*", "", text, flags=re.S)
    return re.sub(r"\s+", "", text)


class SourceContractTest(unittest.TestCase):
    def setUp(self):
        self.cpp = (SKETCH / "Firmware.cpp").read_text(encoding="utf-8-sig")
        self.usb = (SKETCH / "usb_provisioning.h").read_text(encoding="utf-8-sig")

    def test_laboratory_is_unchanged(self):
        self.assertEqual(hashlib.sha256(LAB.read_bytes()).hexdigest(), ORIGINAL_SHA)

    def test_sensor_configuration_logic_preserved(self):
        lab = LAB.read_text(encoding="utf-8-sig")
        self.assertEqual(normalized_sensor_code(lab), normalized_sensor_code(self.cpp))
        self.assertIn("100.0f * (raw - s.dry) / (s.wet - s.dry)", self.cpp)
        self.assertIn('r["value"] = (int)value', self.cpp)

    def test_lab_origin_and_certificate_not_copied(self):
        lab = LAB.read_text(encoding="utf-8-sig")
        origin = re.search(r'IOT_BASE_URL\s*=\s*"([^"]+)"', lab).group(1)
        ca = re.search(r"-----BEGIN CERTIFICATE-----\s*([A-Za-z0-9+/=\s]+)\s*-----END CERTIFICATE-----", lab).group(1).strip()
        distribution = self.cpp + self.usb + (SKETCH / "server_profile.h").read_text()
        self.assertNotIn(origin, distribution)
        self.assertNotIn(ca, distribution)
        self.assertNotIn("PRIVATE KEY-----", distribution)
        self.assertNotRegex(distribution, r'"[a-fA-F0-9]{64}"')

    def test_https_fails_closed(self):
        self.assertIn("if (!serverProfileReady()) return -103", self.cpp)
        self.assertIn("client.setCACert(kTrustedRootCa)", self.cpp)
        self.assertIn("HTTPC_DISABLE_FOLLOW_REDIRECTS", self.cpp)
        self.assertNotIn("setInsecure(", self.cpp)
        profile = (SKETCH / "server_profile.h").read_text()
        self.assertIn('kServerOrigin[] = ""', profile)
        self.assertIn('kTrustedRootCa[] = ""', profile)

    def test_usb_is_bounded_and_does_not_emit_persistent_secrets(self):
        self.assertIn("USB_WINDOW_MS = 180000", self.usb)
        self.assertIn("usbFrame[1025]", self.usb)
        self.assertIn('usbNonce != command["nonce"]', self.usb)
        self.assertIn("memset(usbFrame, 0, sizeof(usbFrame))", self.usb)
        for key in ["exchange_secret", "credential", "password", "ssid"]:
            self.assertNotIn(f'reply["{key}"]', self.usb)
        self.assertIn('reply["operator_proof"] = proof', self.usb)
        self.assertIn('"revoke_and_pair_again"', self.usb)

    def test_persistence_is_guarded_and_no_actuators(self):
        self.assertIn("WiFi.persistent(false)", self.usb)
        self.assertIn("esp_wifi_set_storage(WIFI_STORAGE_RAM)", self.usb)
        self.assertIn("CONFIG_NVS_ENCRYPTION", self.usb)
        self.assertIn("esp_flash_encryption_enabled()", self.usb)
        for forbidden in ["digitalWrite(", "analogWrite(", "OUTPUT)"]:
            self.assertNotIn(forbidden, self.cpp + self.usb)
        self.assertIn('identity["origin"]', self.usb)

    def test_retries_and_build_profile(self):
        self.assertIn('batch["recorded_at"] = measuredAt', self.cpp)
        self.assertIn("pendingBatchBody, true, response", self.cpp)
        profile = (SKETCH / "sketch.yaml").read_text()
        for dependency in ["esp32:esp32 (3.0.7)", "ArduinoJson (7.2.1)", "DHT sensor library (1.4.6)", "Adafruit Unified Sensor (1.1.15)"]:
            self.assertIn(dependency, profile)


if __name__ == "__main__":
    unittest.main()
