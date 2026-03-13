<?php

namespace App\Services;

use App\Contracts\ImageTo3DProvider;
use Illuminate\Support\Facades\Http;

class MeshyProvider implements ImageTo3DProvider
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseUrl = 'https://api.meshy.ai/openapi/v1',
    ) {}

    public function submit(array $imagePaths, ?string $texturePrompt = null): string
    {
        $imagePaths = array_slice($imagePaths, 0, 4);
        if (empty($imagePaths)) {
            throw new \RuntimeException('At least one image path is required');
        }

        $firstPath = $imagePaths[0];
        if (!file_exists($firstPath)) {
            throw new \RuntimeException("Source image not found: {$firstPath}");
        }

        $ext = strtolower(pathinfo($firstPath, PATHINFO_EXTENSION));
        if (in_array($ext, ['avif', 'webp'])) {
            throw new \RuntimeException('Image must be JPEG or PNG for Meshy. AVIF/WebP conversion failed. Ensure ImageMagick or PHP GD with libavif is installed.');
        }
        $mime = match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            default => 'image/jpeg',
        };

        $imageData = base64_encode(file_get_contents($firstPath));
        $imageUrl = "data:{$mime};base64,{$imageData}";

        $payload = [
            'image_url' => $imageUrl,
            'should_texture' => true,
        ];

        if ($texturePrompt !== null && $texturePrompt !== '') {
            $payload['texture_prompt'] = mb_substr($texturePrompt, 0, 600);
        }

        $response = Http::timeout(60)
            ->withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type' => 'application/json',
            ])
            ->post("{$this->baseUrl}/image-to-3d", $payload);

        $response->throw();

        return $response->json('result');
    }

    public function poll(string $jobId): array
    {
        $response = Http::timeout(15)
            ->withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
            ])
            ->get("{$this->baseUrl}/image-to-3d/{$jobId}");

        $response->throw();

        $data = $response->json();
        $providerStatus = $data['status'] ?? 'unknown';

        $statusMap = [
            'PENDING' => 'queued',
            'IN_PROGRESS' => 'processing',
            'SUCCEEDED' => 'done',
            'FAILED' => 'failed',
        ];

        $glbUrl = $data['model_urls']['glb'] ?? $data['model_url'] ?? null;

        return [
            'status' => $statusMap[$providerStatus] ?? 'processing',
            'glb_download_url' => $glbUrl,
        ];
    }
}
