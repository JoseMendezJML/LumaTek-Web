<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Instalador de la app móvil (Android)
    |--------------------------------------------------------------------------
    |
    | Ruta del APK que se entrega en /app (QR de la portada). No se sube a git:
    | se genera con `flutter build apk --release` en LumaTek-Mobile y se copia
    | aquí. Ver docs/app-movil.md.
    |
    */

    'apk_path' => env('MOBILE_APK_PATH', storage_path('app/descargas/lumatek.apk')),

];
