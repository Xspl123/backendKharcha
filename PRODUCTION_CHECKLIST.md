# Production Checklist

- Set `APP_ENV=production` and `APP_DEBUG=false`.
- Confirm `APP_KEY`, DB, mail, queue, and cache env vars are set correctly.
- For Redis-based performance, set `CACHE_STORE=redis`, `QUEUE_CONNECTION=redis`, and optionally `SESSION_DRIVER=redis`.
- Ensure either the PHP `redis` extension is installed or a Redis client package such as `predis/predis` is available.
- Ensure `storage/` and `bootstrap/cache/` are writable by the app user.
- Run `php artisan migrate --force`.
- Run `php artisan db:seed --class=PermissionSeeder --force`.
- Run `php artisan db:seed --class=RolePresetSeeder --force`.
- If deploying existing org data, verify `org_id` backfill counts after migration.
- Cache configuration and routes with `php artisan config:cache` and `php artisan route:cache`.
- Run queue worker or supervisor for queued jobs if the app depends on queues.
- If using Redis queues, prefer `php artisan queue:work redis --queue=exports,default --sleep=1 --tries=3`.
- Verify Sanctum token auth and CORS settings against the frontend domain.
- Smoke test critical APIs: login, `/api/me`, user management, leads, invoices, purchases.
- Review logs after deploy for permission-denied spikes or missing permission names.
