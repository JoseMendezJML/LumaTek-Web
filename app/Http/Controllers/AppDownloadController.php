<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Descarga de la app móvil (destino del QR de la portada).
 *
 * Android recibe el APK; iPhone y iPad, un aviso, porque la app todavía no se
 * distribuye para iOS. Si aún no se ha publicado el APK, se muestra "Próximamente".
 */
class AppDownloadController extends Controller
{
    public function __invoke(Request $request): Response
    {
        if (preg_match('/iPhone|iPad|iPod/i', (string) $request->userAgent())) {
            return response()->view('app-download', ['state' => 'ios']);
        }

        $apk = (string) config('mobile_app.apk_path');

        if (! is_file($apk)) {
            return response()->view('app-download', ['state' => 'unavailable'], 404);
        }

        return response()->download($apk, 'LumaTek.apk', [
            'Content-Type' => 'application/vnd.android.package-archive',
        ]);
    }
}
