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

        $payload = $this->buildImageTo3dPayload($imageUrl, $texturePrompt);

        $response = Http::timeout(120)
            ->withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type' => 'application/json',
            ])
            ->post("{$this->baseUrl}/image-to-3d", $payload);

        $response->throw();

        return $response->json('result');
    }

    /**
     * @return array<string, mixed>
     */
    private function buildImageTo3dPayload(string $imageUrl, ?string $texturePrompt): array
    {
        $cfg = config('services.image_to_3d.meshy', []);
        $modelType = strtolower((string) ($cfg['model_type'] ?? 'standard')) === 'lowpoly' ? 'lowpoly' : 'standard';

        $shouldTexture = (bool) ($cfg['should_texture'] ?? true);
        $enablePbr = (bool) ($cfg['enable_pbr'] ?? false);

        $payload = [
            'image_url' => $imageUrl,
            'target_formats' => ['glb'],
            'should_texture' => $shouldTexture,
        ];
        if ($shouldTexture) {
            $payload['enable_pbr'] = $enablePbr;
        }

        if ($modelType === 'lowpoly') {
            $payload['model_type'] = 'lowpoly';
        } else {
            $payload['model_type'] = 'standard';
            $aiModel = $cfg['ai_model'] ?? 'latest';
            $aiModel = in_array($aiModel, ['meshy-5', 'meshy-6', 'latest'], true) ? $aiModel : 'latest';
            $payload['ai_model'] = $aiModel;

            // Preserve reference silhouette: Meshy defaults image_enhancement=true which reprocesses the photo.
            if (in_array($aiModel, ['meshy-6', 'latest'], true)) {
                $payload['image_enhancement'] = (bool) ($cfg['image_enhancement'] ?? false);
            }

            $shouldRemesh = (bool) ($cfg['should_remesh'] ?? false);
            $payload['should_remesh'] = $shouldRemesh;

            if ($shouldRemesh) {
                $topology = ($cfg['topology'] ?? 'triangle') === 'quad' ? 'quad' : 'triangle';
                $payload['topology'] = $topology;
                $poly = (int) ($cfg['target_polycount'] ?? 30_000);
                $payload['target_polycount'] = max(100, min(300_000, $poly));
            }

            $sym = trim((string) ($cfg['symmetry_mode'] ?? ''));
            if ($sym !== '' && in_array($sym, ['off', 'auto', 'on'], true)) {
                $payload['symmetry_mode'] = $sym;
            }

            $savePre = (bool) ($cfg['save_pre_remeshed_model'] ?? false)
                || (bool) ($cfg['prefer_pre_remeshed_glb'] ?? false);
            if ($shouldRemesh && $savePre) {
                $payload['save_pre_remeshed_model'] = true;
            }
        }

        $texturePrompt = $texturePrompt !== null ? trim($texturePrompt) : '';
        if ($shouldTexture && $texturePrompt !== '') {
            // Optional user notes only — do not send title/description here (it steers colors away from the photos).
            $payload['texture_prompt'] = mb_substr($texturePrompt, 0, 600);
        }

        return $payload;
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

        $modelUrls = is_array($data['model_urls'] ?? null) ? $data['model_urls'] : [];
        $glbUrl = $modelUrls['glb'] ?? $data['model_url'] ?? null;

        if (
            (bool) config('services.image_to_3d.meshy.prefer_pre_remeshed_glb', false)
            && !empty($modelUrls['pre_remeshed_glb'])
        ) {
            $glbUrl = $modelUrls['pre_remeshed_glb'];
        }

        return [
            'status' => $statusMap[$providerStatus] ?? 'processing',
            'glb_download_url' => $glbUrl,
        ];
    }
}
