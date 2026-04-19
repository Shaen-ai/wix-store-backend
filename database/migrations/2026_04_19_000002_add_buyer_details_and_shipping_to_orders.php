<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('buyer_name')->nullable()->after('buyer_email');
            $table->string('buyer_phone')->nullable()->after('buyer_name');
            $table->json('buyer_details_json')->nullable()->after('buyer_phone');
            $table->string('tracking_number')->nullable()->after('status');
            $table->timestamp('shipped_at')->nullable()->after('tracking_number');
            $table->timestamp('delivered_at')->nullable()->after('shipped_at');
        });

        // Alter the status enum to include shipping states
        DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM('pending','paid','failed','processing','shipped','delivered') DEFAULT 'pending'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM('pending','paid','failed') DEFAULT 'pending'");

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['buyer_name', 'buyer_phone', 'buyer_details_json', 'tracking_number', 'shipped_at', 'delivered_at']);
        });
    }
};
