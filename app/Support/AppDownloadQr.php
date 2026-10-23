<?php

namespace App\Support;

use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * QR (SVG) para descargar la app móvil desde la portada.
 */
class AppDownloadQr
{
    public function url(): string
    {
        return route('app.download');
    }

    public function svg(int $size = 200): string
    {
        $style = new RendererStyle(
            $size,
            1,
            null,
            null,
            Fill::uniformColor(new Rgb(255, 255, 255), new Rgb(18, 86, 44))
        );

        $svg = (new Writer(new ImageRenderer($style, new SvgImageBackEnd())))->writeString($this->url());

        // Sin la declaración XML para poder incrustarlo en el HTML.
        return trim(preg_replace('/^<\?xml[^>]*\?>/', '', $svg));
    }
}
