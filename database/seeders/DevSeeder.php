<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class DevSeeder extends Seeder
{
    /**
     * Seed sample data for local development (dev-local tenant).
     */
    public function run(): void
    {
        $tenant = Tenant::firstOrCreate(
            ['wix_site_id' => config('services.wix.dev_instance_id', 'dev-local')],
            ['plan' => 'free']
        );

        $tenant->settings()->firstOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'base_currency' => 'EUR',
            ]
        );

        if ($tenant->products()->count() > 0) {
            return; // Already has products
        }

        $products = [
            [
                'title' => '3D Printed Vase',
                'description' => 'Elegant ceramic-style vase, perfect for modern interiors.',
                'base_price_cents' => 2999,
                'base_currency' => 'EUR',
            ],
            [
                'title' => 'Minimalist Lamp',
                'description' => 'Sleek desk lamp with adjustable brightness.',
                'base_price_cents' => 4599,
                'base_currency' => 'EUR',
            ],
            [
                'title' => 'Geometric Planter',
                'description' => 'Hexagonal planter for succulents and small plants.',
                'base_price_cents' => 1899,
                'base_currency' => 'EUR',
            ],
        ];

        foreach ($products as $p) {
            Product::create([
                'tenant_id' => $tenant->id,
                'title' => $p['title'],
                'description' => $p['description'],
                'base_price_cents' => $p['base_price_cents'],
                'base_currency' => $p['base_currency'],
                'is_active' => true,
            ]);
        }
    }
}
