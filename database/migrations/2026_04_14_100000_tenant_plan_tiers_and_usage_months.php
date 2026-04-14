<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_usage_months', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('period_yyyymm', 7);
            $table->unsignedInteger('image_to_3d_count')->default(0);
            $table->timestamps();
            $table->unique(['tenant_id', 'period_yyyymm']);
        });

        if (!Schema::hasColumn('tenants', 'plan')) {
            return;
        }

        Schema::table('tenants', function (Blueprint $table) {
            $table->string('plan_new', 32)->default('basic');
        });

        DB::table('tenants')->update([
            'plan_new' => DB::raw("CASE plan WHEN 'free' THEN 'basic' WHEN 'premium' THEN 'business' ELSE plan END"),
        ]);

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('plan');
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->renameColumn('plan_new', 'plan');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_usage_months');

        if (!Schema::hasColumn('tenants', 'plan')) {
            return;
        }

        Schema::table('tenants', function (Blueprint $table) {
            $table->string('plan_old', 32)->default('free');
        });

        DB::table('tenants')->update([
            'plan_old' => DB::raw("CASE plan WHEN 'basic' THEN 'free' ELSE 'premium' END"),
        ]);

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('plan');
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->renameColumn('plan_old', 'plan');
        });
    }
};
