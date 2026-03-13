# 3D Store Backend

Laravel 11 API backend for 3D Store Gallery.

## Production: Queue Worker Required

Image-to-3D generation uses queued jobs. **You must run a queue worker** or products will stay stuck on "Processing":

```bash
php artisan queue:work
```

Use Supervisor, systemd, or your platform's process manager to keep it running.

### Quick diagnostics

```bash
php check-queue.php
```

Shows pending jobs, failed jobs, and models stuck on processing. See `DEBUGGING_3D_MODEL.md` for full troubleshooting.
