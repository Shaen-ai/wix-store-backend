<?php

namespace App\Console\Commands;

use App\Models\TenantSetting;
use Illuminate\Console\Command;

class SyncImageTo3dKey extends Command
{
    protected $signature = '3dstore:sync-image-to-3d-key';

    protected $description = 'Copy IMAGE_TO_3D_API_KEY from .env to tenant_settings for tenants that have none';

    public function handle(): int
    {
        $envKey = env('IMAGE_TO_3D_API_KEY');
        if (empty($envKey)) {
            $this->error('IMAGE_TO_3D_API_KEY is not set in .env');
            return 1;
        }

        $updated = 0;
        TenantSetting::all()->each(function (TenantSetting $s) use ($envKey, &$updated) {
            if (empty($s->image_to_3d_api_key)) {
                $s->image_to_3d_api_key = $envKey;
                $s->save();
                $updated++;
                $this->line("Updated tenant {$s->tenant_id}");
            }
        });

        $this->info("Synced API key to {$updated} tenant(s). Queue worker will now find the key in DB.");
        return 0;
    }
}
