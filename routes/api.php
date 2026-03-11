<?php

use App\Http\Controllers\Api\V1\CheckoutController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\PayPalIpnController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ProductModelController;
use App\Http\Controllers\Api\V1\SettingsController;
use App\Http\Controllers\Api\V1\TenantController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1 Routes
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    // ── Public (no Wix auth) ─────────────────────────────────
    Route::post('paypal/ipn', [PayPalIpnController::class, 'handle'])
        ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

    // ── Authenticated (Wix token) ────────────────────────────
    Route::middleware('wix.auth')->group(function () {

        // Tenant
        Route::get('tenant/me', [TenantController::class, 'me']);

        // Settings (unified: tenant + widget, use comp_id & instance query params)
        Route::get('settings', [SettingsController::class, 'show']);
        Route::put('settings', [SettingsController::class, 'update']);

        // Products
        Route::get('products', [ProductController::class, 'index']);
        Route::post('products', [ProductController::class, 'store']);
        Route::get('products/{id}', [ProductController::class, 'show']);
        Route::put('products/{id}', [ProductController::class, 'update']);
        Route::delete('products/{id}', [ProductController::class, 'destroy']);

        // Product models (3D)
        Route::post('products/{productId}/model/upload-glb', [ProductModelController::class, 'uploadGlb']);
        Route::post('products/{productId}/model/upload-image', [ProductModelController::class, 'uploadImage']);
        Route::get('products/{productId}/model/status', [ProductModelController::class, 'status']);
        Route::get('products/{productId}/model/glb', [ProductModelController::class, 'downloadGlb']);

        // Orders
        Route::get('orders', [OrderController::class, 'index']);
        Route::get('orders/export.csv', [OrderController::class, 'exportCsv']);

        // Checkout
        Route::post('checkout/paypal', [CheckoutController::class, 'paypal'])
            ->middleware('throttle:20,1');
        Route::post('checkout/paypal/cart', [CheckoutController::class, 'paypalCart'])
            ->middleware('throttle:20,1');
    });
});
