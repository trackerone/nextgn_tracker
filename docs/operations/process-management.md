# Process Management (Ubuntu 24.04 LTS)

Runbook for managing persistent NextGN background processes in production.

## Required process model

NextGN production normally needs separate long-running processes:

- Web server (Nginx + PHP-FPM).
- Queue worker process (`php artisan queue:work`).
- Scheduler trigger (`php artisan schedule:run` every minute).

## Queue worker baseline

Recommended worker command:

```bash
cd /path/to/nextgn_tracker
php artisan queue:work --tries=3 --timeout=90 --sleep=3 --max-time=3600
```

Notes:

- `--tries=3` prevents infinite retry loops.
- `--timeout=90` should stay below external job timeouts.
- `--max-time=3600` supports graceful periodic recycle.

After each deployment, always run:

```bash
php artisan queue:restart
```

## Supervisor example

`/etc/supervisor/conf.d/nextgn-worker.conf`:

```ini
[program:nextgn-worker]
process_name=%(program_name)s_%(process_num)02d
command=/usr/bin/php /path/to/nextgn_tracker/artisan queue:work --tries=3 --timeout=90 --sleep=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/nextgn/worker.log
stopwaitsecs=360
```

Apply:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status
```

## systemd example

`/etc/systemd/system/nextgn-queue.service`:

```ini
[Unit]
Description=NextGN Queue Worker
After=network.target

[Service]
Type=simple
User=www-data
Group=www-data
Restart=always
RestartSec=5
WorkingDirectory=/path/to/nextgn_tracker
ExecStart=/usr/bin/php artisan queue:work --tries=3 --timeout=90 --sleep=3 --max-time=3600
ExecReload=/usr/bin/php artisan queue:restart
KillSignal=SIGTERM
TimeoutStopSec=360

[Install]
WantedBy=multi-user.target
```

Enable and start:

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now nextgn-queue.service
sudo systemctl status nextgn-queue.service
```

## Scheduler management

Use cron (or platform scheduler) every minute:

```cron
* * * * * cd /path/to/nextgn_tracker && php artisan schedule:run >> /dev/null 2>&1
```

If using systemd timers instead of cron, ensure one-minute cadence equivalent.

## Restart policies

Baseline policy:

- Queue workers: always restart on crash.
- Short restart backoff (3–10 seconds).
- Graceful stop timeout long enough for in-flight jobs.
- Automatic worker recycle (`--max-time`) to mitigate memory growth.

## Worker scaling basics

Start with 1–2 workers, then scale by observed queue load:

- Increase worker count when queue depth remains elevated during normal traffic.
- Scale down if workers stay idle and host resources are constrained.
- Keep worker concurrency aligned with DB/Redis capacity.

Quick scaling examples:

- Supervisor: increase `numprocs`.
- systemd: run templated units (for example `nextgn-queue@1.service`, `nextgn-queue@2.service`) if your ops model supports it.

## Horizon notes

If Laravel Horizon is introduced later:

- Use Horizon as the worker supervisor for Redis queues.
- Monitor queue throughput/failures in Horizon dashboard.
- Keep deployment hook `php artisan queue:restart` or Horizon-specific terminate flow aligned with your Horizon configuration.

If Horizon is not installed, continue with `queue:work` + Supervisor/systemd.

## Log handling

Queue and runtime logs should be rotated:

- App logs: `storage/logs/laravel.log`, `storage/logs/security.log`.
- Process logs: Supervisor/systemd journal logs.

Example logrotate snippet `/etc/logrotate.d/nextgn-worker`:

```text
/var/log/nextgn/*.log {
    daily
    rotate 14
    compress
    missingok
    notifempty
    copytruncate
}
```

## Operational checks

Useful commands:

```bash
php artisan queue:failed
php artisan queue:retry all
php artisan queue:flush
sudo supervisorctl status
sudo systemctl status nextgn-queue.service
```

Use `queue:flush` with care; it clears failed-job records.
