<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Log;

class ImageService
{
    private const CONVERTIBLE_EXTENSIONS = ['heic', 'heif', 'avif', 'webp'];

    /**
     * Convert HEIC/HEIF/AVIF/WebP to JPEG or PNG for Meshy.ai compatibility.
     * Tries PHP GD, sips (macOS), then ImageMagick. Falls back gracefully.
     *
     * @return string Path to converted image, or original path if conversion not needed/failed.
     */
    public function convertToPngOrJpeg(string $inputPath): string
    {
        $ext = strtolower(pathinfo($inputPath, PATHINFO_EXTENSION));

        if (!in_array($ext, self::CONVERTIBLE_EXTENSIONS)) {
            return $inputPath;
        }

        $outputPath = preg_replace('/\.(heic|heif|avif|webp)$/i', '.jpg', $inputPath);

        // Try PHP GD (supports AVIF in PHP 8.1+ with libavif)
        if (function_exists('imagecreatefromavif') && in_array($ext, ['avif'])) {
            $img = @imagecreatefromavif($inputPath);
            if ($img !== false) {
                if (imagejpeg($img, $outputPath, 90)) {
                    imagedestroy($img);
                    @unlink($inputPath);
                    return $outputPath;
                }
                imagedestroy($img);
            }
        }
        if (function_exists('imagecreatefromwebp') && in_array($ext, ['webp'])) {
            $img = @imagecreatefromwebp($inputPath);
            if ($img !== false) {
                if (imagejpeg($img, $outputPath, 90)) {
                    imagedestroy($img);
                    @unlink($inputPath);
                    return $outputPath;
                }
                imagedestroy($img);
            }
        }

        // Try sips (macOS built-in) – supports HEIC, may support AVIF on newer macOS
        $result = Process::timeout(30)->run(
            ['sips', '-s', 'format', 'jpeg', $inputPath, '--out', $outputPath]
        );

        if ($result->successful() && file_exists($outputPath)) {
            @unlink($inputPath);
            return $outputPath;
        }

        // Try ImageMagick (v7+ uses 'magick', older versions use 'convert')
        foreach (['magick', 'convert'] as $cmd) {
            $args = $cmd === 'magick' ? [$cmd, 'convert', $inputPath, $outputPath] : [$cmd, $inputPath, $outputPath];
            $result = Process::timeout(30)->run($args);

            if ($result->successful() && file_exists($outputPath)) {
                @unlink($inputPath);
                return $outputPath;
            }
        }

        Log::warning('Image conversion failed, returning original', ['path' => $inputPath, 'ext' => $ext]);
        return $inputPath;
    }

    /**
     * @deprecated Use convertToPngOrJpeg() instead. Kept for backward compatibility.
     */
    public function convertHeicToJpeg(string $inputPath): string
    {
        return $this->convertToPngOrJpeg($inputPath);
    }
}
