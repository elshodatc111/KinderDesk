<?php

namespace App\Services\News;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Post muqovasi: yuklangan rasm tekshiriladi, 1600px gacha kichraytiriladi va WebP (yoki JPEG) qilib
 * yopiq storage'ga saqlanadi (cPanel'da public papka/symlink muammosi bo'lmaydi; rasm kontroller orqali beriladi).
 */
class CoverImage
{
    public const MAX_PIXELS = 16_000_000;

    public const MAX_WIDTH = 1600;

    public static function store(UploadedFile $file, string $field = 'cover'): string
    {
        $path = $file->getRealPath();
        $info = $path ? @getimagesize($path) : false;
        if (! $info || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            throw ValidationException::withMessages([$field => __("Rasm JPG, PNG yoki WebP bo'lishi kerak")]);
        }
        if (($info[0] * $info[1]) > self::MAX_PIXELS) {
            throw ValidationException::withMessages([$field => __("Rasm juda katta (16 megapikselgacha)")]);
        }
        $src = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            default => @imagecreatefromwebp($path),
        };
        if (! $src) {
            throw ValidationException::withMessages([$field => __("Rasmni o'qib bo'lmadi")]);
        }

        // JPEG: kameradan kelgan aylantirish (EXIF) hisobga olinadi
        if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $exif = @exif_read_data($path);
            $rot = [3 => 180, 6 => -90, 8 => 90][$exif['Orientation'] ?? 0] ?? 0;
            if ($rot) {
                $rotated = imagerotate($src, $rot, 0);
                if ($rotated) {
                    $src = $rotated;
                }
            }
        }

        $w = imagesx($src);
        $h = imagesy($src);
        if ($w > self::MAX_WIDTH) {
            $scaled = imagescale($src, self::MAX_WIDTH, (int) round($h * self::MAX_WIDTH / $w), IMG_BICUBIC);
            if ($scaled) {
                $src = $scaled;
            }
        }
        imagepalettetotruecolor($src);

        $webp = function_exists('imagewebp');
        $name = 'posts/'.Str::random(28).($webp ? '.webp' : '.jpg');
        ob_start();
        if ($webp) {
            imagealphablending($src, false);
            imagesavealpha($src, true);
            imagewebp($src, null, 82);
        } else {
            $flat = imagecreatetruecolor(imagesx($src), imagesy($src));
            imagefill($flat, 0, 0, imagecolorallocate($flat, 255, 255, 255));
            imagecopy($flat, $src, 0, 0, 0, 0, imagesx($src), imagesy($src));
            imagejpeg($flat, null, 85);
        }
        $bytes = ob_get_clean();
        if (! $bytes) {
            throw ValidationException::withMessages([$field => __("Rasmni saqlab bo'lmadi")]);
        }
        Storage::disk('local')->put($name, $bytes);

        return $name;
    }

    public static function delete(?string $path): void
    {
        if ($path && str_starts_with($path, 'posts/') && ! str_contains($path, '..')) {
            Storage::disk('local')->delete($path);
        }
    }
}
