<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('notification_email')->nullable();
            $table->string('paypal_receiver_email')->nullable();
            $table->string('base_currency', 3)->default('EUR');
            $table->json('allowed_currencies_json')->nullable();
            $table->string('fx_provider')->default('exchangerate');
            $table->string('fx_api_key')->nullable();
            $table->string('image_to_3d_provider')->default('meshy');
            $table->string('image_to_3d_api_key')->nullable();
            $table->timestamps();

            $table->unique('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_settings');
    }
};
