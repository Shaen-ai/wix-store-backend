<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateModelFromImage;
use App\Models\Product;
use App\Models\ProductModel;
use App\Services\ImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class ProductModelController extends Controller
{
    public function __construct(
        private readonly ImageService $imageService,
    ) {}

    public function uploadGlb(Request $request, int $productId): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');
        $product = Product::where('tenant_id', $tenant->id)->findOrFail($productId);

        $request->validate([
            'glb' => 'required|file|max:51200|mimes:glb,bin|mimetypes:model/gltf-binary,application/octet-stream', // 50 MB, GLB files
        ]);

        $file = $request->file('glb');
        $disk = config('filesystems.default', 'local');
        $path = $file->store("tenants/{$tenant->id}/models", $disk);

        $model = ProductModel::updateOrCreate(
            ['product_id' => $product->id],
            [
                'tenant_id' => $tenant->id,
                'glb_disk' => $disk,
                'glb_path' => $path,
                'source_type' => 'uploaded_glb',
                'generation_status' => 'done',
            ]
        );

        return response()->json([
            'data' => [
                'id' => $model->id,
                'generation_status' => $model->generation_status,
                'glb_url' => $model->getGlbTemporaryUrl(),
            ],
        ]);
    }

    public function uploadImage(Request $request, int $productId): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');
        $product = Product::where('tenant_id', $tenant->id)->findOrFail($productId);

        $files = $request->file('images') ?? $request->file('images[]') ?? ($request->file('image') ? [$request->file('image')] : null);
        if (is_array($files)) {
            $files = array_values(array_filter($files, fn ($f) => $f instanceof \Illuminate\Http\UploadedFile && $f->isValid()));
        }
        $request->merge(['images' => $files ?: []]);

        $request->validate([
            'images' => 'required|array|min:1|max:4',
            'notes' => 'nullable|string|max:600',
            'images.*' => [
                'required',
                'file',
                'max:20480',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $ext = strtolower($value->getClientOriginalExtension() ?? '');
                    $allowed = ['jpg', 'jpeg', 'png', 'heic', 'heif', 'avif', 'webp'];
                    if (!in_array($ext, $allowed)) {
                        $fail('Invalid image format. Allowed: JPEG, PNG, HEIC, AVIF, WebP.');
                    }
                },
            ],
        ]);

        $disk = config('filesystems.default', 'local');
        $imagePaths = [];

        foreach ($request->file('images') as $file) {
            $imagePath = $file->store("tenants/{$tenant->id}/source-images", $disk);
            $fullPath = Storage::disk($disk)->path($imagePath);
            $convertedPath = $this->imageService->convertHeicToJpeg($fullPath);

            if ($convertedPath !== $fullPath) {
                $relativePath = str_replace(Storage::disk($disk)->path(''), '', $convertedPath);
                $imagePath = $relativePath;
            }
            $imagePaths[] = $imagePath;
        }

        $userNotes = $request->input('notes') ? mb_substr(trim($request->input('notes')), 0, 600) : null;

        $model = ProductModel::updateOrCreate(
            ['product_id' => $product->id],
            [
                'tenant_id' => $tenant->id,
                'source_type' => 'generated_from_image',
                'source_image_path' => $imagePaths[0] ?? null,
                'source_images_json' => $imagePaths,
                'generation_status' => 'processing',
                'generation_meta_json' => array_filter(['user_notes' => $userNotes]),
            ]
        );

        GenerateModelFromImage::dispatch($model)->afterResponse();

        return response()->json([
            'data' => [
                'id' => $model->id,
                'generation_status' => $model->generation_status,
            ],
        ]);
    }

    public function status(Request $request, int $productId): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');
        $product = Product::where('tenant_id', $tenant->id)->findOrFail($productId);

        $model = ProductModel::where('product_id', $product->id)->first();

        if (!$model) {
            return response()->json([
                'data' => ['generation_status' => 'none'],
            ]);
        }

        $data = [
            'id' => $model->id,
            'generation_status' => $model->generation_status,
            'glb_url' => $model->generation_status === 'done' ? $model->getGlbTemporaryUrl() : null,
        ];

        // When processing, trigger a poll to Meshy so status updates (helps when queue worker may be slow)
        if ($model->generation_status === 'processing' && !empty($model->generation_meta_json['provider_job_id'])) {
            try {
                $settings = \App\Models\TenantSetting::where('tenant_id', $model->tenant_id)->first();
                $apiKey = $settings?->image_to_3d_api_key ?: config('services.image_to_3d.api_key') ?: env('IMAGE_TO_3D_API_KEY');
                if ($apiKey) {
                    $provider = new \App\Services\MeshyProvider(apiKey: $apiKey);
                    $result = $provider->poll($model->generation_meta_json['provider_job_id']);
                    if ($result['status'] === 'done' && !empty($result['glb_download_url'])) {
                        $glbContent = \Illuminate\Support\Facades\Http::timeout(120)->get($result['glb_download_url'])->body();
                        $disk = config('filesystems.default', 'local');
                        $path = "tenants/{$model->tenant_id}/models/{$model->product_id}_generated.glb";
                        \Illuminate\Support\Facades\Storage::disk($disk)->put($path, $glbContent);
                        $model->update(['generation_status' => 'done', 'glb_disk' => $disk, 'glb_path' => $path]);
                        $data['generation_status'] = 'done';
                        $data['glb_url'] = $model->fresh()->getGlbTemporaryUrl();
                    } elseif ($result['status'] === 'failed') {
                        $model->update(['generation_status' => 'failed', 'generation_meta_json' => array_merge($model->generation_meta_json ?? [], ['poll_error' => 'Provider returned failed'])]);
                        $data['generation_status'] = 'failed';
                        $data['generation_error'] = 'Provider returned failed';
                    }
                }
            } catch (\Throwable $e) {
                // Ignore; queue worker will eventually poll
            }
        }

        // Include error/debug info when failed or stuck (helps debugging)
        if ($model->generation_status === 'failed' && $model->generation_meta_json) {
            $data['generation_error'] = $model->generation_meta_json['error']
                ?? $model->generation_meta_json['poll_error']
                ?? 'Unknown error';
            $data['generation_meta'] = $model->generation_meta_json;
        } elseif (in_array($model->generation_status, ['queued', 'processing']) && $model->generation_meta_json) {
            $data['generation_meta'] = $model->generation_meta_json;
        }

        return response()->json(['data' => $data]);
    }

    public function retry(Request $request, int $productId): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');
        $product = Product::where('tenant_id', $tenant->id)->findOrFail($productId);

        $model = ProductModel::where('product_id', $product->id)->first();

        if (!$model || $model->source_type !== 'generated_from_image') {
            return response()->json(['error' => 'No image-based model to retry'], 422);
        }

        $existingNotes = $model->generation_meta_json['user_notes'] ?? null;
        $model->update([
            'generation_status' => 'queued',
            'generation_meta_json' => array_filter(['user_notes' => $existingNotes]),
        ]);

        GenerateModelFromImage::dispatch($model)->afterResponse();

        return response()->json([
            'data' => [
                'id' => $model->id,
                'generation_status' => 'queued',
            ],
        ]);
    }

    public function downloadGlb(Request $request, int $productId): Response
    {
        $tenant = $request->attributes->get('tenant');
        $product = Product::where('tenant_id', $tenant->id)->findOrFail($productId);
        $model = ProductModel::where('product_id', $product->id)->first();

        if (!$model || !$model->glb_path || $model->generation_status !== 'done') {
            abort(404);
        }

        $disk = Storage::disk($model->glb_disk);
        if (!$disk->exists($model->glb_path)) {
            abort(404);
        }

        $content = $disk->get($model->glb_path);
        $filename = basename($model->glb_path);
        $disposition = $request->query('download') ? 'attachment' : 'inline';

        return response($content, 200, [
            'Content-Type' => 'model/gltf-binary',
            'Content-Disposition' => $disposition . '; filename="' . $filename . '"',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
