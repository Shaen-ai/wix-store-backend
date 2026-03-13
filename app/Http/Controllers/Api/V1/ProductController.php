<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateModelFromImage;
use App\Models\Product;
use App\Models\ProductModel;
use App\Services\ImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function __construct(
        private readonly ImageService $imageService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');
        $currency = $request->query('currency');
        $search = $request->query('search');

        $query = Product::where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->with('model');

        if ($search) {
            $escaped = str_replace(['%', '_'], ['\\%', '\\_'], $search);
            $query->where(function ($q) use ($escaped) {
                $q->where('title', 'like', "%{$escaped}%")
                  ->orWhere('description', 'like', "%{$escaped}%");
            });
        }

        if ($request->query('storefront')) {
            $query->whereHas('model', fn ($q) => $q->where('generation_status', 'done'));
        }

        $products = $query->orderBy('created_at', 'desc')->paginate(50);

        $data = $products->getCollection()->map(function (Product $p) use ($currency) {
            return $this->formatProduct($p, $currency);
        });

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');
        $currency = $request->query('currency');

        $product = Product::where('tenant_id', $tenant->id)
            ->with('model')
            ->findOrFail($id);

        return response()->json(['data' => $this->formatProduct($product, $currency)]);
    }

    public function store(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        $rules = [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'base_price_cents' => 'required|integer|min:1',
            'base_currency' => 'nullable|string|size:3',
            'quantity_available' => 'nullable|integer|min:0',
        ];

        $imageFiles = $this->getImageFiles($request);
        $isMultipart = $request->hasFile('glb') || $imageFiles;
        if ($imageFiles) {
            $request->merge(['images' => $imageFiles]);
        }
        if ($isMultipart) {
            $rules['notes'] = 'nullable|string|max:600';
            $rules['glb'] = [
                'nullable',
                'file',
                'max:51200',
                function (string $attr, mixed $value, \Closure $fail): void {
                    if (!$value instanceof \Illuminate\Http\UploadedFile || !$value->isValid()) {
                        return;
                    }
                    $ext = strtolower($value->getClientOriginalExtension() ?? '');
                    $mime = $value->getMimeType();
                    $allowedExt = ['glb', 'bin'];
                    $allowedMime = ['model/gltf-binary', 'application/octet-stream'];
                    if (!in_array($ext, $allowedExt) && !in_array($mime, $allowedMime)) {
                        $fail('GLB file must have .glb/.bin extension or model/gltf-binary MIME type.');
                    }
                },
            ];
            $rules['images'] = 'nullable|array|min:1|max:4';
            $rules['images.*'] = [
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
            ];
        } else {
            $rules['images'] = 'nullable|array';
            $rules['images.*'] = 'string|url';
        }

        $validated = $request->validate($rules);

        $product = Product::create([
            'tenant_id' => $tenant->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? '',
            'base_price_cents' => $validated['base_price_cents'],
            'base_currency' => $validated['base_currency'] ?? $tenant->settings?->base_currency ?? 'EUR',
            'images_json' => $isMultipart ? [] : ($validated['images'] ?? []),
            'is_active' => true,
            'quantity_available' => $validated['quantity_available'] ?? null,
        ]);

        if ($request->hasFile('glb')) {
            $this->attachGlbToProduct($request, $tenant->id, $product);
        } elseif ($this->getImageFiles($request)) {
            $this->attachImagesToProduct($request, $tenant->id, $product, $request->input('notes'));
        }

        return response()->json(['data' => $this->formatProduct($product->fresh()->load('model'))], 201);
    }

    private function attachGlbToProduct(Request $request, int $tenantId, Product $product): void
    {
        $file = $request->file('glb');
        $disk = config('filesystems.default', 'local');
        $path = $file->store("tenants/{$tenantId}/models", $disk);

        ProductModel::updateOrCreate(
            ['product_id' => $product->id],
            [
                'tenant_id' => $tenantId,
                'glb_disk' => $disk,
                'glb_path' => $path,
                'source_type' => 'uploaded_glb',
                'generation_status' => 'done',
            ]
        );
    }

    private function getImageFiles(Request $request): ?array
    {
        $files = $request->file('images') ?? $request->file('images[]');
        if (!$files) {
            return null;
        }
        $arr = is_array($files) ? $files : [$files];
        $arr = array_values(array_filter($arr, fn ($f) => $f instanceof \Illuminate\Http\UploadedFile && $f->isValid()));
        return $arr ?: null;
    }

    private function attachImagesToProduct(Request $request, int $tenantId, Product $product, ?string $notes = null): void
    {
        $files = $this->getImageFiles($request) ?? [];
        $disk = config('filesystems.default', 'local');
        $imagePaths = [];

        foreach ($files as $file) {
            $imagePath = $file->store("tenants/{$tenantId}/source-images", $disk);
            $fullPath = Storage::disk($disk)->path($imagePath);
            $convertedPath = $this->imageService->convertHeicToJpeg($fullPath);

            if ($convertedPath !== $fullPath) {
                $relativePath = str_replace(Storage::disk($disk)->path(''), '', $convertedPath);
                $imagePath = $relativePath;
            }
            $imagePaths[] = $imagePath;
        }

        $userNotes = $notes ? mb_substr(trim($notes), 0, 600) : null;

        $model = ProductModel::updateOrCreate(
            ['product_id' => $product->id],
            [
                'tenant_id' => $tenantId,
                'source_type' => 'generated_from_image',
                'source_image_path' => $imagePaths[0] ?? null,
                'source_images_json' => $imagePaths,
                'generation_status' => 'processing',
                'generation_meta_json' => array_filter(['user_notes' => $userNotes]),
            ]
        );

        GenerateModelFromImage::dispatch($model)->afterResponse();
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        $product = Product::where('tenant_id', $tenant->id)->findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:5000',
            'base_price_cents' => 'sometimes|integer|min:1',
            'base_currency' => 'nullable|string|size:3',
            'images' => 'nullable|array',
            'images.*' => 'string|url',
            'is_active' => 'nullable|boolean',
            'quantity_available' => 'nullable|integer|min:0',
        ]);

        $updateData = [];
        foreach (['title', 'description', 'base_price_cents', 'base_currency', 'is_active', 'quantity_available'] as $field) {
            if (array_key_exists($field, $validated)) {
                $updateData[$field] = $validated[$field];
            }
        }
        if (isset($validated['images'])) {
            $updateData['images_json'] = $validated['images'];
        }

        $product->update($updateData);

        return response()->json(['data' => $this->formatProduct($product->fresh()->load('model'))]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');
        $product = Product::where('tenant_id', $tenant->id)->findOrFail($id);
        $product->delete();

        return response()->json(['message' => 'Product deleted']);
    }

    public function duplicate(Request $request, int $id): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');
        $source = Product::where('tenant_id', $tenant->id)->with('model')->findOrFail($id);

        $newProduct = Product::create([
            'tenant_id' => $tenant->id,
            'title' => $source->title . ' (Copy)',
            'description' => $source->description ?? '',
            'base_price_cents' => $source->base_price_cents,
            'base_currency' => $source->base_currency,
            'images_json' => $source->images_json ?? [],
            'is_active' => $source->is_active,
            'quantity_available' => $source->quantity_available,
        ]);

        $sourceModel = $source->model;
        if ($sourceModel && $sourceModel->generation_status === 'done' && $sourceModel->glb_path) {
            $disk = Storage::disk($sourceModel->glb_disk);
            if ($disk->exists($sourceModel->glb_path)) {
                $content = $disk->get($sourceModel->glb_path);
                $targetDisk = config('filesystems.default', 'local');
                $ext = pathinfo($sourceModel->glb_path, PATHINFO_EXTENSION) ?: 'glb';
                $newPath = "tenants/{$tenant->id}/models/" . \Illuminate\Support\Str::random(40) . '.' . $ext;
                Storage::disk($targetDisk)->put($newPath, $content);

                ProductModel::create([
                    'tenant_id' => $tenant->id,
                    'product_id' => $newProduct->id,
                    'glb_disk' => $targetDisk,
                    'glb_path' => $newPath,
                    'source_type' => 'uploaded_glb',
                    'generation_status' => 'done',
                ]);
            }
        }

        return response()->json(['data' => $this->formatProduct($newProduct->fresh()->load('model'))], 201);
    }

    private function formatProduct(Product $p, ?string $displayCurrency = null): array
    {
        $data = [
            'id' => $p->id,
            'tenant_id' => $p->tenant_id,
            'title' => $p->title,
            'description' => $p->description,
            'base_price_cents' => $p->base_price_cents,
            'base_currency' => $displayCurrency ?? $p->base_currency,
            'images' => $p->images,
            'is_active' => $p->is_active,
            'quantity_available' => $p->quantity_available,
            'model' => null,
            'created_at' => $p->created_at?->toISOString(),
            'updated_at' => $p->updated_at?->toISOString(),
        ];

        if ($p->model) {
            try {
                $glbUrl = $p->model->getGlbTemporaryUrl();
            } catch (\Throwable) {
                $glbUrl = null;
            }
            $data['model'] = [
                'id' => $p->model->id,
                'product_id' => $p->model->product_id,
                'glb_url' => $glbUrl,
                'source_type' => $p->model->source_type,
                'generation_status' => $p->model->generation_status,
                'created_at' => $p->model->created_at?->toISOString(),
            ];
        }

        return $data;
    }
}
