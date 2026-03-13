<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PrepareStorage extends Command
{
    protected $signature = 'storage:prepare';

    protected $description = 'Create storage directories for all tenants (run after deploy, before queue worker)';

    public function handle(): int
    {
        $disk = config('filesystems.default', 'local');
        $root = Storage::disk($disk)->path('');

        if (!is_dir($root)) {
            if (!@mkdir($root, 0755, true)) {
                $this->error("Cannot create storage root: {$root}");
                return 1;
            }
            $this->line("Created storage root: {$root}");
        }
        if (!is_writable($root)) {
            $this->error("Storage root is not writable: {$root}");
            $this->line('Run: sudo chown -R www-data:www-data storage && sudo chmod -R 775 storage');
            return 1;
        }

        $tenantIds = Tenant::pluck('id')->unique()->values();
        if ($tenantIds->isEmpty()) {
            $tenantIds = collect([1]);
            $this->line('No tenants in DB; creating default structure for tenant 1.');
        }

        $created = 0;
        foreach ($tenantIds as $tenantId) {
            foreach (['models', 'source-images'] as $subdir) {
                $path = "tenants/{$tenantId}/{$subdir}";
                if (!Storage::disk($disk)->exists($path)) {
                    Storage::disk($disk)->makeDirectory($path);
                    $this->line("Created: {$path}");
                    $created++;
                }
            }
        }

        $this->info("Storage ready. Created {$created} directory(ies).");
        return 0;
    }
}
