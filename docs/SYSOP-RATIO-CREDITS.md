# Sysop ratio credits and live member topbar

## Member experience

Signed-in members see their effective uploaded bytes (↑), downloaded bytes (↓), and ratio (R) beside **Forum** in the main navigation. The strip wraps on small screens and links to the existing ratio/completed-torrent detail page at \`/account/snatches\`. The duplicate personal-navigation and user-dropdown entries have been removed. Ratios at or above 1.00 are green; below 1.00 are red; new accounts without either transfer or credit are neutral.

## Data model and source of truth

Raw transfer accounting must never be rewritten to implement Sysop gifts or corrections.

- The integrity-credited tracker aggregate in \`user_stats\` is used when it contains recorded bytes. For older accounts without such totals, fall back to the historic \`user_torrents\` totals.
- \`user_ratio_credits\` stores separate, **signed** uploaded/downloaded adjustments, positive for gifts or charges and negative for reversals.
- Effective uploaded/downloaded totals = maximum of zero and (real credited traffic + signed adjustments). This same calculation now powers the member header, dashboard, account pages, \`GET /api/me/stats\`, user classes and ratio download enforcement.
- Torrent-level uploaded/downloaded bytes, announces, peers, HnR obligations, seeding history and snatches are not changed by administrative credits. The user never self-edits credits.

## Sysop controls

In **Site settings → Ratio credits & starting balances** (\`/sysop/ratio-credits\`):

1. Set the starting uploaded/downloading credits (in GiB) for *future registrations only*. Defaults are 0 GiB each. This does not backfill any existing member.
2. Look up an account by numeric ID or email; grant or subtract uploaded/downloaded GiB. A subtraction cannot exceed that user's current effective balance.
3. A reason is required for all changes. The audit log records actor, target, before and after balances, direction, amount and reason. Only sysop-level accounts may access these controls.

The settings use the \`site_settings\` keys \`tracker.ratio.default_uploaded_gib\` and \`tracker.ratio.default_downloaded_gib\`. New registrations apply the current defaults during the same database transaction that creates the account; imported or pre-existing accounts are not modified.

## Deployment and tests

Run \`php artisan migrate --force\` after deployment to create \`user_ratio_credits\`, then \`php artisan optimize:clear\`. No queue worker or Node build is required specifically for these Blade/PHP changes.

Automated regression coverage checks the header on member pages, role-based Sysop access, audited adjustments, negative-balance refusal, same effective figures in download enforcement and the API, and defaults only for newly registered accounts.

**Operational warning:** Ratio credit editing is a high-trust Sysop operation. It affects download eligibility, but must not be used as a substitute for reviewing HnR cases or correcting erroneous announces. Keep the audit reasons specific.
