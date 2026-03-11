<?php

namespace App\Jobs;

use App\Models\Product;
use App\Models\TenantSetting;
use App\Services\FxService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RefreshFxRates implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(FxService $fxService): void
    {
        $baseCurrencies = collect()
            ->merge(TenantSetting::distinct()->pluck('base_currency'))
            ->merge(Product::distinct()->pluck('base_currency'))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($baseCurrencies)) {
            $baseCurrencies = ['EUR', 'USD', 'GBP', 'ILS'];
        }

        foreach ($baseCurrencies as $base) {
            try {
                $fxService->refreshRates($base);
                Log::info("FX rates refreshed for {$base}");
            } catch (\Throwable $e) {
                Log::error("FX rate refresh failed for {$base}", ['error' => $e->getMessage()]);
            }
        }
    }
}
