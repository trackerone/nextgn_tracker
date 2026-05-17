# Staging demo seeding

`Database\Seeders\StagingDemoSeeder` creates deterministic, believable non-production data for local, staging, and screenshot environments. It is not a production demo mode and does not change tracker behavior, policies, moderation flows, or the admin UI.

## Factory/seeder audit

Existing factories already cover the core models needed for isolated tests: users, roles, categories, torrents, torrent metadata/external metadata, peers, user torrent stats, follows, topics/posts, and private messages. Existing seeders only create base roles/categories and very small demo content, so this slice adds a dedicated staging seeder instead of widening `DatabaseSeeder` or changing app behavior.

## What it seeds

- Users with sysop, moderator, uploader, power-user, member, and newbie profiles.
- Browseable torrents with deterministic names, upload dates, sizes, metadata, release groups, and external metadata.
- Grouped release families, including multiple versions of the same movie/episode from different release groups.
- Healthy, weak, and dead swarm examples with plausible seeders, leechers, snatches, peers, and per-user stats.
- Freeleech and internal releases via existing torrent flags/tags.
- Forum topics/posts, private-message conversations/messages, and torrent follows that match seeded releases.
- Moderation states: published/approved, pending, and rejected/banned examples.

## Running locally or in staging

```bash
php artisan migrate
php artisan db:seed --class=StagingDemoSeeder
```

Seeded demo users use the password `password` and `example.test` email addresses. The seeder is deterministic enough for repeatable screenshots and can be re-run; it upserts the known demo records and refreshes related demo swarm/forum/message rows.

## Production guard

The seeder aborts when `APP_ENV=production`. Laravel's explicit `--force` flag is still recognized for rare approved non-production clones that intentionally keep `APP_ENV=production`:

```bash
php artisan db:seed --class=StagingDemoSeeder --force
```

Do not run this against a real production tracker database.
