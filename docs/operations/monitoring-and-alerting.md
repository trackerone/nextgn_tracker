# Monitoring and Alerting (Ubuntu 24.04 LTS)

Operator-focused baseline for production observability and alert response.

## Monitoring goals

- Detect outages quickly.
- Detect degraded performance before users report it.
- Detect queue/backlog issues before data freshness is impacted.
- Detect abuse patterns on tracker endpoints.

## Recommended metrics

Collect at minimum:

- Uptime and latency for `GET /health`.
- HTTP response codes (`2xx`, `4xx`, `5xx`) and request rate.
- PHP-FPM process utilization and slow requests.
- Nginx request volume and upstream error rate.
- Queue depth, job runtime, and failed jobs.
- DB connectivity and query latency.
- Redis availability, memory usage, eviction count.
- Disk usage (`/`, app volume, log volume, storage volume).
- Growth of `storage/app/torrents` and `storage/app/nfo`.

## Uptime checks

Recommended external checks every 60 seconds:

- `GET /health` (primary readiness signal).
- Optional authenticated smoke check for web UI/API path.

Example shell check from a monitoring node:

```bash
curl -fsS https://your-domain.example/health
```

Alert if 3 consecutive checks fail.

## Queue health and failed jobs

Key checks:

```bash
php artisan queue:failed
php artisan queue:monitor redis:default --max=1000
```

Monitor:

- Queue depth increasing for more than 10–15 minutes.
- Oldest queued job age.
- Failed job count growth.
- Worker restart frequency.

Basic thresholds:

- Warning: queue depth > 500 for 10 minutes.
- Critical: queue depth > 2000 for 10 minutes.
- Warning: failed jobs > 0 in 5 minutes.
- Critical: failed jobs > 20 in 5 minutes.

Tune to actual traffic profile.

## Database connectivity and performance

Track:

- Connection failures/timeouts.
- Slow query count.
- Replication lag (if replicas are used).

Quick operator check:

```bash
php artisan migrate:status
```

If this fails unexpectedly in production, investigate DB availability/credentials immediately.

## Redis health

Track:

- `PING` success.
- Memory utilization trend.
- Evictions.
- Persistence failures (if AOF/RDB enabled).

Quick operator check:

```bash
redis-cli -h "$REDIS_HOST" -p "$REDIS_PORT" ping
```

Alert on Redis unavailability because queue/cache behavior can degrade quickly.

## Disk space and storage growth

Track:

- Free space on app and database volumes.
- Growth rate in `storage/app/torrents` and `storage/app/nfo`.
- Log volume growth in `storage/logs`.

Quick checks:

```bash
df -h
sudo du -sh /path/to/nextgn_tracker/storage/app/torrents
sudo du -sh /path/to/nextgn_tracker/storage/app/nfo
sudo du -sh /path/to/nextgn_tracker/storage/logs
```

Suggested thresholds:

- Warning at 80% disk usage.
- Critical at 90% disk usage.

## PHP-FPM and Nginx basics

Monitor and alert on:

- Nginx 5xx spikes.
- Nginx upstream timeouts/resets.
- PHP-FPM max children reached.
- PHP-FPM slowlog activity.

Useful service checks:

```bash
systemctl status nginx
systemctl status php8.4-fpm
```

Log tails:

```bash
sudo tail -n 200 /var/log/nginx/error.log
sudo tail -n 200 /var/log/php8.4-fpm.log
```

## Log monitoring

At minimum, centralize and parse:

- `storage/logs/laravel.log`
- `storage/logs/security.log`
- Nginx access/error logs
- PHP-FPM logs

Alert on:

- Burst of unhandled exceptions.
- Repeated authentication/authorization failures.
- Sudden increase in 500 responses.

## Suspicious tracker traffic and scrape abuse indicators

Watch for:

- Unusual spikes to `/announce/{passkey}` and `/scrape/{passkey}`.
- Excessive scrape requests with many `info_hash` values.
- High ratio of requests from few IPs/user agents.
- Repeated invalid passkey requests.

Potential response actions:

- Add temporary rate limits/WAF rules at edge.
- Block known abusive IP ranges.
- Investigate account/passkey abuse and rotate credentials where required.

## Production-check automation ideas

Automate and alert on `nextgn:production-check` regressions:

```bash
cd /path/to/nextgn_tracker
php artisan nextgn:production-check --no-interaction
```

Run this after deploy and on a schedule (for example hourly). Alert on non-zero exit code.

## Example cron jobs for monitoring hooks

```cron
*/5 * * * * cd /path/to/nextgn_tracker && php artisan queue:monitor redis:default --max=1000
0 * * * * cd /path/to/nextgn_tracker && php artisan nextgn:production-check --no-interaction
```

## Minimal alert policy baseline

Start with these alert classes:

- **Critical (page on-call):** health endpoint down, DB unavailable, Redis unavailable, sustained 5xx surge, disk > 90%.
- **Warning (ticket/slack):** queue backlog growth, failed jobs present, disk > 80%, abnormal scrape volume.

Document for each alert:

- Trigger condition.
- Immediate triage command.
- Escalation owner.
- Recovery/rollback playbook link.
