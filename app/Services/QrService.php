<?php

namespace App\Services;

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

/**
 * QR generation for certificates and documents. Uses endroid (GD backend —
 * no imagick dependency). Encodes short reference text (e.g. a certificate
 * number), not a public URL.
 */
class QrService
{
    public function png(string $data, int $size = 320): string
    {
        $qr = new QrCode(data: $data, size: $size, margin: 8);

        return (new PngWriter)->write($qr)->getString();
    }

    public function pngDataUri(string $data, int $size = 320): string
    {
        return 'data:image/png;base64,'.base64_encode($this->png($data, $size));
    }
}
