<?php

namespace App\Services;

use App\Contracts\ImageTo3DProvider;
use Illuminate\Support\Facades\Http;

class MeshyProvider implements ImageTo3DProvider
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseUrl = 'https://api.meshy.ai/v1',
    ) {}

    public function submit(array $imagePaths, ?string $texturePrompt = null): string
    {
        $imagePaths = array_slice($imagePaths, 0, 4);
        if (empty($imagePaths)) {
            throw new \RuntimeException('At least one image path is required');
        }

        foreach ($imagePaths as $path) {
            if (!file_exists($path)) {
                throw new \RuntimeException("Source image not found: {$path}");
            }
        }

        $keys = ['image', 'image_2', 'image_3', 'image_4'];
        $request = Http::withHeaders([
            'Authorization' => "Bearer {$this->apiKey}",
        ]);

        $params = ['output_format' => 'glb'];
        if ($texturePrompt !== null && $texturePrompt !== '') {
            $params['texture_prompt'] = mb_substr($texturePrompt, 0, 600);
        }
        foreach ($imagePaths as $i => $imagePath) {
            $key = $keys[$i];
            $request = $request->attach($key, fopen($imagePath, 'r'), basename($imagePath));
        }

        $response = $request->post("{$this->baseUrl}/image-to-3d", $params);

        $response->throw();

        return $response->json('result');
    }

    public function poll(string $jobId): array
    {
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->apiKey}",
        ])->get("{$this->baseUrl}/image-to-3d/{$jobId}");

        $response->throw();

        $data = $response->json();
        $providerStatus = $data['status'] ?? 'unknown';

        $statusMap = [
            'PENDING' => 'queued',
            'IN_PROGRESS' => 'processing',
            'SUCCEEDED' => 'done',
            'FAILED' => 'failed',
        ];

        return [
            'status' => $statusMap[$providerStatus] ?? 'processing',
            'glb_download_url' => $data['model_url'] ?? null,
        ];
    }
}
