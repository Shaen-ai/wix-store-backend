<?php

namespace App\Jobs;

use App\Contracts\ImageTo3DProvider;
use App\Models\ProductModel;
use App\Models\TenantSetting;
use App\Services\ImageService;
use App\Services\MeshyProvider;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GenerateModelFromImage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        private readonly ProductModel $productModel,
    ) {}

    public function handle(ImageService $imageService): void
    {
        $model = $this->productModel->fresh();

        if (!$model || $model->generation_status === 'done') {
            return;
        }

        $settings = TenantSetting::where('tenant_id', $model->tenant_id)->first();
        $apiKey = $settings?->image_to_3d_api_key
            ?: config('services.image_to_3d.api_key')
            ?: env('IMAGE_TO_3D_API_KEY');

        if (!$apiKey) {
            $model->update([
                'generation_status' => 'failed',
                'generation_meta_json' => ['error' => 'No image-to-3D API key configured'],
            ]);
            return;
        }

        $provider = $this->resolveProvider($apiKey);

        try {
            $model->update(['generation_status' => 'processing']);

            $disk = $model->glb_disk ?: config('filesystems.default', 'local');
            $imagePaths = $model->source_images_json ?? ($model->source_image_path ? [$model->source_image_path] : []);
            $imagePaths = array_map(fn (string $p) => Storage::disk($disk)->path($p), $imagePaths);

            // Convert AVIF/WebP/HEIC to JPEG before sending to Meshy
            $imagePaths = array_map(fn (string $p) => $imageService->convertToPngOrJpeg($p), $imagePaths);

            if (empty($imagePaths)) {
                throw new \RuntimeException('No source images found');
            }

            $product = $model->product;
            $texturePrompt = null;
            $userNotes = $model->generation_meta_json['user_notes'] ?? null;
            if ($userNotes && trim($userNotes) !== '') {
                $texturePrompt = trim($userNotes);
            } elseif ($product) {
                $parts = array_filter([$product->title ?? '', $product->description ?? '']);
                if (!empty($parts)) {
                    $texturePrompt = implode('. ', $parts);
                }
            }

            $jobId = $provider->submit($imagePaths, $texturePrompt);

            $model->update([
                'generation_meta_json' => array_merge(
                    $model->generation_meta_json ?? [],
                    ['provider_job_id' => $jobId],
                ),
            ]);

            PollModelGeneration::dispatch($model)->delay(now()->addSeconds(30));
        } catch (\Throwable $e) {
            Log::error('Image-to-3D submit failed', ['error' => $e->getMessage()]);
            $model->update([
                'generation_status' => 'failed',
                'generation_meta_json' => array_merge(
                    $model->generation_meta_json ?? [],
                    ['error' => $e->getMessage()]
                ),
            ]);
        }
    }

    private function resolveProvider(string $apiKey): ImageTo3DProvider
    {
        return new MeshyProvider(apiKey: $apiKey);
    }
}
