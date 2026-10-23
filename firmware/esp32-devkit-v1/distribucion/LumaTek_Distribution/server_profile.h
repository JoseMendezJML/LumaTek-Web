#pragma once
// Public deployment profile only. Empty defaults fail closed: no network API requests.
// Supply a reviewed production HTTPS origin (no trailing slash) and its PUBLIC CA chain.
// Never paste a laboratory URL, private CA, private key, Wi-Fi password or device token.
static const char kServerOrigin[] = "";
static const char kTrustedRootCa[] = "";
