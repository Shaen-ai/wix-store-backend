<?php

namespace App\Providers;

use App\Contracts\FxRateProvider;
use App\Contracts\ImageTo3DProvider;
use App\Services\ExchangeRateFxProvider;
use App\Services\MeshyProvider;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(FxRateProvider::class, function () {
            return new ExchangeRateFxProvider(
                apiKey: config('services.fx.api_key', ''),
            );
        });

        $this->app->bind(ImageTo3DProvider::class, function () {
            return new MeshyProvider(
                apiKey: config('services.image_to_3d.api_key', ''),
            );
        });
    }

    public function boot(): void
    {
        //
    }
}
