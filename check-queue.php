<?php
/**
 * Quick queue/model debug script - run with: php check-queue.php
 * No tinker required.
 */
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Queue driver: " . config('queue.default') . "\n";
echo "Pending jobs: " . \DB::table('jobs')->count() . "\n";

$failed = \DB::table('failed_jobs')->count();
echo "Failed jobs: " . $failed . "\n";
if ($failed > 0) {
    echo "  Run: php artisan queue:failed\n";
}

$processing = \App\Models\ProductModel::where('generation_status', 'processing')->get();
echo "\nModels stuck on 'processing': " . $processing->count() . "\n";
$anyWithoutJobId = false;
foreach ($processing as $m) {
    $jobId = $m->generation_meta_json['provider_job_id'] ?? null;
    if (!$jobId) $anyWithoutJobId = true;
    echo "  - product_id={$m->product_id}, provider_job_id=" . ($jobId ?: 'NOT SET (job never ran)') . "\n";
}

echo "\n";
if ($anyWithoutJobId) {
    echo ">>> Queue worker is likely NOT running. Start it with: php artisan queue:work\n";
}
