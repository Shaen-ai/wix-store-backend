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

        $files = $request->file('images') ?? ($request->file('image') ? [$request->file('image')] : null);
        $request->merge(['images' => $files]);

        $request->validate([
            'images' => 'required|array|min:1|max:4',
            'images.*' => 'required|file|mimes:jpg,jpeg,png,heic,heif|max:20480', // 20 MB each, 1-4 images
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

        $model = ProductModel::updateOrCreate(
            ['product_id' => $product->id],
            [
                'tenant_id' => $tenant->id,
                'source_type' => 'generated_from_image',
                'source_image_path' => $imagePaths[0] ?? null,
                'source_images_json' => $imagePaths,
                'generation_status' => 'queued',
                'generation_meta_json' => [],
            ]
        );

        GenerateModelFromImage::dispatch($model);

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

        return response()->json([
            'data' => [
                'id' => $model->id,
                'generation_status' => $model->generation_status,
                'glb_url' => $model->generation_status === 'done' ? $model->getGlbTemporaryUrl() : null,
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

        return response($content, 200, [
            'Content-Type' => 'model/gltf-binary',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
