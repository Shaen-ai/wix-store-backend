<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_models', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('glb_disk')->default('local');
            $table->string('glb_path')->nullable();
            $table->enum('source_type', ['uploaded_glb', 'generated_from_image']);
            $table->string('source_image_path')->nullable();
            $table->enum('generation_status', ['queued', 'processing', 'done', 'failed'])->default('queued');
            $table->json('generation_meta_json')->nullable();
            $table->timestamps();

            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_models');
    }
};
