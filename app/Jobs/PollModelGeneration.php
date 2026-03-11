<?php

namespace App\Jobs;

use App\Models\ProductModel;
use App\Models\TenantSetting;
use App\Services\MeshyProvider;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PollModelGeneration implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 30;

    public function __construct(
        private readonly ProductModel $productModel,
    ) {}

    public function handle(): void
    {
        $model = $this->productModel->fresh();

        if (!$model || in_array($model->generation_status, ['done', 'failed'])) {
            return;
        }

        $settings = TenantSetting::where('tenant_id', $model->tenant_id)->first();
        $jobId = $model->generation_meta_json['provider_job_id'] ?? null;
        $apiKey = $settings?->image_to_3d_api_key
            ?: config('services.image_to_3d.api_key')
            ?: env('IMAGE_TO_3D_API_KEY');

        if (!$jobId || !$apiKey) {
            $model->update(['generation_status' => 'failed']);
            return;
        }

        try {
            $provider = new MeshyProvider(apiKey: $apiKey);
            $result = $provider->poll($jobId);

            if ($result['status'] === 'done' && !empty($result['glb_download_url'])) {
                $glbContent = Http::timeout(120)->get($result['glb_download_url'])->body();
                $disk = config('filesystems.default', 'local');
                $path = "tenants/{$model->tenant_id}/models/{$model->product_id}_generated.glb";

                Storage::disk($disk)->put($path, $glbContent);

                $model->update([
                    'generation_status' => 'done',
                    'glb_disk' => $disk,
                    'glb_path' => $path,
                ]);

                Log::info('3D model generated successfully', ['model_id' => $model->id]);
                return;
            }

            if ($result['status'] === 'failed') {
                $model->update([
                    'generation_status' => 'failed',
                    'generation_meta_json' => array_merge(
                        $model->generation_meta_json ?? [],
                        ['poll_error' => 'Provider returned failed']
                    ),
                ]);
                return;
            }

            // Still processing — re-release with exponential backoff
            $pollCount = ($model->generation_meta_json['poll_count'] ?? 0) + 1;
            $model->update([
                'generation_meta_json' => array_merge(
                    $model->generation_meta_json ?? [],
                    ['poll_count' => $pollCount]
                ),
            ]);

            if ($pollCount >= 60) {
                $model->update([
                    'generation_status' => 'failed',
                    'generation_meta_json' => array_merge(
                        $model->generation_meta_json ?? [],
                        ['error' => 'Max poll attempts exceeded']
                    ),
                ]);
                return;
            }

            $delay = min(60 * $this->attempts(), 300);
            $this->release($delay);
        } catch (\Throwable $e) {
            Log::error('Poll model generation failed', ['error' => $e->getMessage()]);

            if ($this->attempts() >= $this->tries) {
                $model->update([
                    'generation_status' => 'failed',
                    'generation_meta_json' => array_merge(
                        $model->generation_meta_json ?? [],
                        ['error' => $e->getMessage()]
                    ),
                ]);
            } else {
                $this->release(60);
            }
        }
    }
}
