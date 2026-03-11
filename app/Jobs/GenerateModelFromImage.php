<?php

namespace App\Jobs;

use App\Contracts\ImageTo3DProvider;
use App\Models\ProductModel;
use App\Models\TenantSetting;
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

    public function handle(): void
    {
        $model = $this->productModel->fresh();

        if (!$model || $model->generation_status === 'done') {
            return;
        }

        $settings = TenantSetting::where('tenant_id', $model->tenant_id)->first();
        $apiKey = $settings?->image_to_3d_api_key;

        if (!$apiKey) {
            $model->update([
                'generation_status' => 'failed',
                'generation_meta_json' => ['error' => 'No image-to-3D API key configured'],
            ]);
            return;
        }

        $provider = $this->resolveProvider($settings);

        try {
            $model->update(['generation_status' => 'processing']);

            $disk = $model->glb_disk ?: config('filesystems.default', 'local');
            $imagePaths = $model->source_images_json ?? ($model->source_image_path ? [$model->source_image_path] : []);
            $imagePaths = array_map(fn (string $p) => Storage::disk($disk)->path($p), $imagePaths);

            if (empty($imagePaths)) {
                throw new \RuntimeException('No source images found');
            }

            $product = $model->product;
            $texturePrompt = null;
            if ($product) {
                $parts = array_filter([$product->title ?? '', $product->description ?? '']);
                if (!empty($parts)) {
                    $texturePrompt = implode('. ', $parts);
                }
            }

            $jobId = $provider->submit($imagePaths, $texturePrompt);

            $model->update([
                'generation_meta_json' => array_merge(
                    $model->generation_meta_json ?? [],
                    ['provider_job_id' => $jobId]
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

    private function resolveProvider(TenantSetting $settings): ImageTo3DProvider
    {
        return new MeshyProvider(
            apiKey: $settings->image_to_3d_api_key,
        );
    }
}
