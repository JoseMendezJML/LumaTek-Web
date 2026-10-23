# Descarga de la app móvil (QR de la portada)

La portada muestra un QR que apunta a `/app` (`route('app.download')`, armado con
`APP_URL`). En celulares, en lugar del QR aparece el botón "Descargar app para
Android".

## Qué responde `/app`

| Visitante | Respuesta |
|---|---|
| Android (o cualquier navegador que no sea iOS) | Descarga `LumaTek.apk` (`application/vnd.android.package-archive`) |
| iPhone o iPad | Página "Por ahora la app de LumaTek solo está disponible para Android" |
| No hay APK publicado | Página "Próximamente" con estado 404 |

## Publicar el APK

El APK **no se sube a git**. Se coloca en `storage/app/descargas/lumatek.apk`, que
ya está ignorado, o en la ruta que indique `MOBILE_APK_PATH`.

En el proyecto LumaTek-Mobile:

```bash
flutter build apk --release \
  --dart-define=API_URL=https://<dominio>/api \
  --dart-define=GOOGLE_SERVER_CLIENT_ID=<GOOGLE_CLIENT_ID del .env>

cp build/app/outputs/flutter-apk/app-release.apk \
  ../LumaTek-Web/storage/app/descargas/lumatek.apk
```

- `API_URL` queda fija dentro del APK. Si cambia el dominio (por ejemplo, con un
  túnel rápido de Cloudflare), hay que compilarlo y publicarlo de nuevo.
- Sin `GOOGLE_SERVER_CLIENT_ID`, la app no muestra "Continuar con Google".
- El APK release se firma con la llave de depuración de quien lo compila. Para que
  Google funcione, esa SHA-1 debe estar registrada en el cliente **Android** de
  Google Cloud (paquete `com.lumatek.lumatek_mobile`). Antes de publicarlo en Play
  Store hay que crear una llave de release propia y registrar su SHA-1.
- Al instalarlo, Android pide permitir "instalar apps de fuentes desconocidas" al
  navegador. Es normal fuera de Play Store.
