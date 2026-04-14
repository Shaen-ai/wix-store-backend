<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('tenants')->where('plan', 'business_pro')->update(['plan' => 'business-pro']);
    }

    public function down(): void
    {
        DB::table('tenants')->where('plan', 'business-pro')->update(['plan' => 'business_pro']);
    }
};
