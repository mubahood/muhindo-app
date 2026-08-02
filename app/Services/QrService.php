<?php

namespace App\Services;

use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

/**
 * QR generation for certificates and documents. Uses endroid (GD backend — no
 * imagick dependency). Encodes the public verification URL.
 *
 * Two settings here exist because these codes get printed, photocopied, faxed
 * and photographed off a desk under bad light — not scanned off a screen:
 *
 *  - Quartile error correction. Around a quarter of the symbol can be lost to
 *    a smudge, a fold or a staple and it still reads. The default (Low) tops
 *    out near 7%.
 *  - ISO-8859-1 rather than UTF-8. A URL is ASCII, and UTF-8 makes endroid
 *    emit an ECI header that some scanners handle poorly — OpenCV warns about
 *    exactly this. Dropping it costs nothing and removes a class of failure.
 */
class QrService
{
    public function png(string $data, int $size = 320): string
    {
        $qr = new QrCode(
            data: $data,
            encoding: new Encoding('ISO-8859-1'),
            errorCorrectionLevel: ErrorCorrectionLevel::Quartile,
            size: $size,
            margin: 8,
        );

        return (new PngWriter)->write($qr)->getString();
    }

    public function pngDataUri(string $data, int $size = 320): string
    {
        return 'data:image/png;base64,'.base64_encode($this->png($data, $size));
    }
}
