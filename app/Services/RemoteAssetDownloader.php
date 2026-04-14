<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Streams a remote URL to disk without buffering the entire body in memory.
 */
final class RemoteAssetDownloader
{
    /**
     * Download bytes from URL into storage at $path on $disk.
     *
     * @throws \Illuminate\Http\Client\RequestException
     */
    public static function streamUrlToDisk(string $url, string $disk, string $path, int $timeoutSeconds = 300): void
    {
        Storage::disk($disk)->makeDirectory(dirname($path));

        $tmp = tempnam(sys_get_temp_dir(), 'dl_');
        if ($tmp === false) {
            throw new \RuntimeException('Could not create temp file for download');
        }

        try {
            /** @var Response $response */
            $response = Http::timeout($timeoutSeconds)->sink($tmp)->get($url);
            $response->throw();

            $stream = fopen($tmp, 'rb');
            if ($stream === false) {
                throw new \RuntimeException('Could not open temp file for upload');
            }
            try {
                Storage::disk($disk)->put($path, $stream);
            } finally {
                fclose($stream);
            }
        } finally {
            @unlink($tmp);
        }
    }
}
