<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Log;

class ImageService
{
    /**
     * Convert HEIC/HEIF to JPEG.
     * Tries sips (macOS), then ImageMagick convert, then falls back gracefully.
     *
     * @return string Path to converted JPEG, or original path if conversion not needed/failed.
     */
    public function convertHeicToJpeg(string $inputPath): string
    {
        $ext = strtolower(pathinfo($inputPath, PATHINFO_EXTENSION));

        if (!in_array($ext, ['heic', 'heif'])) {
            return $inputPath;
        }

        $outputPath = preg_replace('/\.(heic|heif)$/i', '.jpg', $inputPath);

        // Try sips (macOS built-in)
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

        Log::warning('HEIC conversion failed, returning original', ['path' => $inputPath]);
        return $inputPath;
    }
}
