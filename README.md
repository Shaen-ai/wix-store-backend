# 3D Store Backend

Laravel 11 API backend for 3D Store Gallery.

## Production Deployment

### 1. Storage (required for 3D generation)

Run after each deploy so the queue worker can save generated GLB files:

```bash
php artisan storage:prepare
sudo chown -R www-data:www-data storage
sudo chmod -R 775 storage
```

### 2. Queue Worker (required for 3D generation)

Image-to-3D generation uses queued jobs. **Set up Supervisor once** so the worker runs automatically:

```bash
# One-time setup (adjust path if your app is elsewhere)
sudo cp deployment/supervisor-queue-worker.conf /etc/supervisor/conf.d/wix-3dstore-worker.conf
# Edit the config if your app path is not /var/www/wix-3dstore/backend
sudo nano /etc/supervisor/conf.d/wix-3dstore-worker.conf

sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start wix-3dstore-worker
```

The worker will start on boot and restart if it crashes. Check status:

```bash
sudo supervisorctl status wix-3dstore-worker
```

**If Supervisor is not installed:** `sudo apt install supervisor` (Ubuntu/Debian)

**Alternative (systemd):** Use `deployment/queue-worker.service` instead.

### Quick diagnostics

```bash
php check-queue.php
```

Shows pending jobs, failed jobs, and models stuck on processing. See `DEBUGGING_3D_MODEL.md` for full troubleshooting.
