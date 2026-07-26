<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;

class AvatarService
{
    private ImageManager $manager;

    public function __construct()
    {
        $this->manager = ImageManager::gd();
    }

    /**
     * Resize an uploaded image to a square `$size`×`$size` JPEG and store it on
     * the public disk. Returns the stored relative path (e.g. "avatars/abc.jpg").
     *
     * Falls back to a plain store if image processing fails for any reason.
     */
    public function storeResized(UploadedFile $file, string $dir = 'avatars', int $size = 200): string
    {
        $filename = $dir.'/'.Str::uuid()->toString().'.jpg';

        try {
            $encoded = $this->manager->read($file->getRealPath())
                ->cover($size, $size)
                ->toJpeg(85);

            Storage::disk('public')->put($filename, (string) $encoded);

            return $filename;
        } catch (\Throwable $e) {
            // Processing failed (corrupt file, unsupported format) — store as-is.
            return $file->store($dir, 'public');
        }
    }
}
