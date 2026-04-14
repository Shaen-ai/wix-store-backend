<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Tenant;
use App\Models\TenantUsageMonth;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

class TenantPlanService
{
    public function normalizedPlanKey(?string $plan): string
    {
        $p = $plan ?? 'basic';
        $normalized = match ($p) {
            'free' => 'basic',
            'premium' => 'business',
            'business_pro' => 'business-pro',
            default => $p,
        };

        $definitions = config('plans.definitions', []);

        return isset($definitions[$normalized]) ? $normalized : 'basic';
    }

    public function definitionForTenant(Tenant $tenant): array
    {
        $key = $this->normalizedPlanKey($tenant->plan);
        $definitions = config('plans.definitions', []);

        return $definitions[$key] ?? $definitions['basic'];
    }

    public function showPoweredByForTenant(Tenant $tenant): bool
    {
        return (bool) ($this->definitionForTenant($tenant)['show_powered_by'] ?? false);
    }

    public function currentPeriodYyyymm(): string
    {
        return now('UTC')->format('Y-m');
    }

    /**
     * @return array{plan: string, limits: array{max_products: int|null, monthly_image_to_3d: int, show_powered_by: bool}, usage: array{products_count: int, image_to_3d_this_month: int}, usage_period_yyyymm: string}
     */
    public function usageSummary(Tenant $tenant): array
    {
        $key = $this->normalizedPlanKey($tenant->plan);
        $def = $this->definitionForTenant($tenant);
        $period = $this->currentPeriodYyyymm();

        $stats = DB::selectOne(
            'SELECT
                (SELECT COUNT(*) FROM products WHERE tenant_id = ?) AS products_count,
                (SELECT image_to_3d_count FROM tenant_usage_months WHERE tenant_id = ? AND period_yyyymm = ? LIMIT 1) AS image_to_3d_this_month',
            [$tenant->id, $tenant->id, $period]
        );

        return [
            'plan' => $key,
            'limits' => [
                'max_products' => array_key_exists('max_products', $def) ? $def['max_products'] : 5,
                'monthly_image_to_3d' => (int) ($def['monthly_image_to_3d'] ?? 0),
                'show_powered_by' => (bool) ($def['show_powered_by'] ?? false),
            ],
            'usage' => [
                'products_count' => (int) ($stats->products_count ?? 0),
                'image_to_3d_this_month' => (int) ($stats->image_to_3d_this_month ?? 0),
            ],
            'usage_period_yyyymm' => $period,
        ];
    }

    public function assertCanAddProduct(Tenant $tenant): void
    {
        $def = $this->definitionForTenant($tenant);
        $max = $def['max_products'] ?? null;
        if ($max === null) {
            return;
        }

        $n = Product::query()->where('tenant_id', $tenant->id)->count();
        if ($n >= $max) {
            throw new HttpResponseException(response()->json([
                'error' => 'product_limit_reached',
                'message' => "You've reached the maximum of {$max} products on your current plan. Delete a product or upgrade to add more.",
            ], 422));
        }
    }

    public function consumeImageTo3dGenerationOrFail(Tenant $tenant): void
    {
        $def = $this->definitionForTenant($tenant);
        $limit = (int) ($def['monthly_image_to_3d'] ?? 0);
        $period = $this->currentPeriodYyyymm();

        DB::transaction(function () use ($tenant, $limit, $period) {
            $row = TenantUsageMonth::query()
                ->where('tenant_id', $tenant->id)
                ->where('period_yyyymm', $period)
                ->lockForUpdate()
                ->first();

            $count = $row?->image_to_3d_count ?? 0;
            if ($count >= $limit) {
                throw new HttpResponseException(response()->json([
                    'error' => 'generation_limit_reached',
                    'message' => "You've reached your plan's limit of {$limit} image-to-3D generations this month. Upgrade for more. If something seems wrong, contact us at info@nextechspires.com.",
                ], 422));
            }

            if ($row) {
                $row->increment('image_to_3d_count');
            } else {
                TenantUsageMonth::query()->create([
                    'tenant_id' => $tenant->id,
                    'period_yyyymm' => $period,
                    'image_to_3d_count' => 1,
                ]);
            }
        });
    }
}
