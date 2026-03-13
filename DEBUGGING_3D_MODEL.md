# Debugging 3D Model Generation (Stuck on "queued" or "processing")

When `generation_status` stays **"queued"** or **"processing"** and never moves to "done", the queue worker may not be running. Follow these steps to find the cause.

**Quick check (no tinker needed):** Run `php check-queue.php` from the backend directory.

---

## 1. Check if the queue worker is running

**Most common cause:** Laravel queues require a worker process. Jobs are stored in the database but nothing processes them until you run:

```bash
cd backend
php artisan queue:work
```

Keep this terminal running. For production, use a process manager (supervisor, systemd) to keep the worker alive.

**Note:** With `QUEUE_CONNECTION=sync`, `GenerateModelFromImage` runs immediately, but `PollModelGeneration` (which checks Meshy every 30–60s until done) does not re-run. Status will stay "processing" forever. Use `QUEUE_CONNECTION=database` and run `queue:work`.

**Lazy poll:** The `/model/status` endpoint polls Meshy when status is "processing", so refreshing the status page can update to "done" even if the queue worker is slow. Meshy 3D generation typically takes 2–5 minutes.

---

## 2. Verify queue configuration

```bash
# Quick check (no tinker needed)
php check-queue.php

# Or, if tinker is installed: php artisan tinker --execute="echo config('queue.default');"
# Should be "database" (or "sync" for immediate execution)
```

Ensure `.env` has:
```
QUEUE_CONNECTION=database
```

---

## 3. Inspect queued and failed jobs

```bash
cd backend

# Count pending jobs in the database
php check-queue.php
# Or: php artisan tinker --execute="echo \DB::table('jobs')->count();"  (requires: composer require laravel/tinker --dev)

# List failed jobs (if any)
php artisan queue:failed
# Shows exception and payload for each failed job

# Retry a failed job
php artisan queue:retry <job-id>
# Or retry all: php artisan queue:retry all
```

---

## 4. Check model status API for error details

The status endpoint now returns `generation_error` and `generation_meta` when a model has failed or has debug info:

```
GET /api/v1/products/{id}/model/status
```

Example failed response:
```json
{
  "data": {
    "id": 6,
    "generation_status": "failed",
    "glb_url": null,
    "generation_error": "No image-to-3D API key configured",
    "generation_meta": { "error": "No image-to-3D API key configured" }
  }
}
```

---

## 5. Verify tenant settings (Meshy API key)

The job requires `image_to_3d_api_key` in `tenant_settings` for the product's tenant:

```bash
php artisan tinker
>>> \App\Models\TenantSetting::where('tenant_id', 1)->first(['tenant_id', 'image_to_3d_api_key']);
```

If the key is null or empty, the job will set status to `failed` with error "No image-to-3D API key configured".

---

## 6. Verify source images exist

The job needs at least one image in `source_images_json` or `source_image_path`. Check the product model:

```bash
php artisan tinker
>>> $m = \App\Models\ProductModel::find(6);
>>> $m->only(['source_type', 'source_image_path', 'source_images_json']);
```

Ensure the files exist on disk at the paths returned.

---

## 7. Run the job manually (for debugging)

```bash
php artisan tinker
>>> $model = \App\Models\ProductModel::find(6);
>>> \App\Jobs\GenerateModelFromImage::dispatchSync($model);
```

This runs the job synchronously. Check `storage/logs/laravel.log` for any errors.

---

## 8. Check Laravel logs

```bash
tail -f backend/storage/logs/laravel.log
```

Then trigger a new product creation or run the job manually. Look for:
- `Image-to-3D submit failed` – error from Meshy provider
- Any stack traces

---

## Summary checklist

| Check | Command / Action |
|-------|------------------|
| Queue worker running? | `php artisan queue:work` in a terminal |
| Jobs in queue? | `php check-queue.php` or `php artisan tinker --execute="echo \DB::table('jobs')->count();"` |
| Failed jobs? | `php artisan queue:failed` |
| API key set? | Check `tenant_settings.image_to_3d_api_key` |
| Source images? | Check `product_models.source_images_json` and file existence |
| Logs | `tail -f storage/logs/laravel.log` |
