# SeAT Member Engagement Module

A SeAT plugin that tracks corporation member activity — mining, PvP kills/losses,
PvE bounty income, and industry tax — and presents it to directors as a
corporation-wide activity feed, with per-metric visibility and weighting controls.

## Features

- **Mining** — quantity mined per character, per day
- **PvP kills / losses** — raw counts, plus a separate "Fleet Participation"
  metric (kills with N+ attackers, director-configurable minimum, deduplicated
  to once per hour)
- **PvE Bounty & Tax** — bounty prizes and daily goal payouts, attributed to
  the character who earned them
- **Industry Tax** — facility tax from industry jobs, attributed to the
  character who ran the job (cross-referenced from the wallet entry's Job ID
  against SeAT's industry job cache)
- **Character/corporation/alliance name resolution** — most characters never
  get a local SeAT record (SeAT only syncs characters that have authenticated
  directly), so this plugin resolves names via ESI's public endpoints in the
  background and displays `Character (Corporation) [Alliance]`
- **Director dashboard** — raw activity feed across the corporation
- **Settings page** — per-metric visibility (Directors / Members / Both) and
  weighting, plus the Fleet Participation minimum size, configurable per
  corporation

The member-facing "My Activities" page currently shows no data by design —
data collection and the director feed are unaffected.

## Prerequisites

- SeAT 5.x (Laravel 10)
- PHP 8.1+
- Corp Wallet Manager (or equivalent) already syncing
  `corporation_wallet_journals` for the corporation(s) you want to track
- Killmail and industry job syncing enabled in SeAT (standard SeAT/ESI setup)
- Outbound HTTPS access from the SeAT containers to `esi.evetech.net` (name
  resolution calls ESI directly; no API key needed, these are public endpoints)

## Installation

The package isn't on Packagist yet, so install it via a VCS repository
pointing at this GitHub repo.

1. **Add the repository and require the package**

   In your SeAT installation's `composer.json`:
   ```json
   "repositories": [
       {
           "type": "vcs",
           "url": "https://github.com/rational-chaos-inc/seat-mrp.git"
       }
   ]
   ```
   ```bash
   composer require rci/member-engagement
   ```

   If you're running the official `eveseat/docker` compose setup, add
   `rci/member-engagement` to the `SEAT_PLUGINS` env var instead — but note
   the `repositories` entry above still needs to be present in the image's
   `composer.json` for that to resolve, since the package isn't on Packagist.
   The simplest way to do that in the docker setup today is the `packages/`
   dev-install path it already supports (mount this repo under
   `packages/member-engagement` and add a `packages/override.json` declaring
   the autoload/provider) — ask if you want help wiring that up for a
   specific docker-compose layout.

2. **Run migrations**
   ```bash
   php artisan migrate
   ```

3. **Assign permissions**

   Permissions aren't pre-seeded — they appear in SeAT's Access Management
   once the plugin boots, and get created the first time you save a role with
   them checked:
   - SeAT → Settings → Access Management
   - Assign `member-engagement.view_own_activities` to your member role
   - Assign `member-engagement.view_all_activities` to your director role

4. **Configure the Director Settings page**

   Visit `/member-engagement/settings` as a director to set per-metric
   visibility, weighting, and the Fleet Participation minimum size for each
   corporation. Sensible defaults apply automatically if you skip this.

Scheduled jobs register themselves automatically (via SeAT's own schedule
seeding on plugin boot) — no manual seeding step needed.

## Routes

- **Member Dashboard:** `/member-engagement/dashboard`
- **Director Dashboard:** `/member-engagement/director` (requires
  `view_all_activities`)
- **Settings:** `/member-engagement/settings` (requires `view_all_activities`)

## Scheduled Commands

Registered automatically once the plugin boots:

| Command | Schedule | Purpose |
|---|---|---|
| `member-engagement:sync --days=2` | every 15 min | Pull new activity from SeAT's locally-synced mining/killmail/wallet data |
| `member-engagement:aggregate --days=3` | every 30 min | Roll activities up into daily per-character stats |
| `member-engagement:resolve-names --limit=1000` | every 20 min | Resolve character/corp/alliance names via ESI |

Each is also runnable manually, e.g. `php artisan member-engagement:sync --days=90`
for a larger backfill on first install.

## Configuration

`config/member-engagement.php`:

```php
return [
    'enabled' => env('MEMBER_ENGAGEMENT_ENABLED', true),
    'aggregation_cache_ttl' => env('MEMBER_ENGAGEMENT_CACHE_TTL', 0),
    'time_windows' => ['day' => 1, 'week' => 7, 'month' => 30, 'quarter' => 90, 'year' => 365],
    'activity_types' => ['mining', 'pvp_kill', 'pvp_loss', 'pve_bounty_tax', 'industry_tax'],
];
```

## Known Limitations

- **Industry Tax coverage**: SeAT's local industry job cache only retains a
  limited window of jobs. Wallet tax entries whose job has aged out of that
  cache can't be attributed to a character and are skipped (not guessed at).
- **Name resolution takes time on a fresh install**: at 1000 characters per
  20-minute cycle, a corporation with several thousand distinct characters in
  its activity history will take a few hours to fully resolve. Run
  `php artisan member-engagement:resolve-names --limit=10000` manually for a
  faster one-time backfill.
- **Member dashboard shows no data currently** — this is deliberate, not a
  bug; the director feed and underlying data collection are unaffected.

## Troubleshooting

**No activities showing up:**
```bash
php artisan member-engagement:sync --days=90
php artisan member-engagement:aggregate --days=90
```
Check `storage/logs/laravel-*.log` for errors from the sync/aggregate commands.

**Characters showing as "Unknown (id)":**
```bash
php artisan member-engagement:resolve-names --limit=5000
```
If it stays unresolved, that character ID may no longer exist on ESI (e.g. a
deleted character).

**Scheduled jobs not running:** confirm SeAT's own scheduler/cron is running
(`php artisan schedule:list` should show the three commands above once the
plugin has booted at least once).

## Uninstallation

```bash
composer remove rci/member-engagement
php artisan migrate:rollback --path=vendor/rci/member-engagement/database/migrations
```

## License

MIT
