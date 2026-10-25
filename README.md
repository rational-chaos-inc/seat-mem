# SeAT Member Engagement Module

A SeAT plugin that tracks corporation member activity — mining, PvP kills/losses,
PvE bounty income, and industry tax — and presents it as monthly leaderboards
on a Corporation View, plus each member's own figures on My Activities.

## Features

- **Mining** — quantity mined per character, per day
- **PvP kills / losses** — raw counts, plus a separate "Fleet Participation"
  metric (kills with N+ attackers, director-configurable minimum, deduplicated
  to once per hour)
- **PvE Bounty & Tax** — bounty prizes and daily goal payouts, attributed to
  the character who earned them
- **Industry Tax** — facility tax from industry jobs, attributed to the
  character who ran the job (ESI gives this directly on the wallet entry
  itself, as the paying character)
- **Character/corporation/alliance name resolution** — most characters never
  get a local SeAT record (SeAT only syncs characters that have authenticated
  directly), so this plugin resolves names via ESI's public endpoints in the
  background and displays `Character (Corporation) [Alliance]`
- **Corporation View** — monthly leaderboards for PvE Bounty & Tax, Industry
  Tax, PvP kills, and fleet participation (four separate leaderboards, not
  combined), with alts consolidated under their account's main character.
  Browse the current (live) month or any previous month with recorded data
- **My Activities** — the same four figures as Corporation View, but just
  the logged-in member's own account (main + linked alts), not ranked
  against anyone else
- **Heartbeat** — one row per registered SeAT account showing how many days
  this month they've logged in (main + linked alts combined), and when they
  last logged in if not today — surfaces inactive members even if they have
  zero activity to otherwise show up in
- **Net Worth** — a daily snapshot of each account's asset value (priced
  against SeAT's market data, excluding blueprint copies) plus ISK and PLEX,
  consolidated across main + alts, with month-on-month change. PLEX is
  recorded but excluded from net worth - it lives in the account-wide vault,
  so it is neither the wallet nor a hangar asset (tracking it is what keeps
  it out of assets, where it would otherwise show as an unknown location). Assets are resolved to the region they actually sit in, which
  means walking the containment tree - most assets are nested inside ships
  and containers, and ESI reports Upwell structures as items too
- **Home + Deployed Presence** — what share of an account's asset value
  sits in the regions you've designated as home or as the current
  deployment. The two count the same and are measured together against the
  configured minimum (default 80%); anything below is highlighted, and the
  table breaks out home and deployed separately. Clicking a member breaks
  their assets down by region as a pie; any slice that isn't a home or
  deployment region can be clicked again to list the assets behind it,
  nested as they sit. Asset safety counts as away (we know where it is, it just
  isn't usable there); customs offices and unresolvable locations are left
  out of the calculation entirely
- **Settings page** — a single set of per-metric visibility (Directors /
  Members / Both), weighting, Fleet Participation minimum size, the
  presence minimum, and the managed home and deployment region lists,
  applying to the whole install regardless of corporation

## Prerequisites

- SeAT 5.x (Laravel 10)
- PHP 8.1+
- Corp Wallet Manager (or equivalent) already syncing
  `corporation_wallet_journals` for the corporation(s) you want to track
- Killmail syncing enabled in SeAT (standard SeAT/ESI setup) - Industry Tax
  only needs the corp wallet journal, not industry job syncing
- The `esi-location.read_online.v1` scope granted on each character's SSO
  login, for the Heartbeat list - SeAT core syncs this into
  `character_onlines` on its normal character update cycle; without the
  scope that table stays empty for that character and they'll show as
  "Never" logged in regardless of actual activity
- Outbound HTTPS access from the SeAT containers to `esi.evetech.net` (name
  resolution calls ESI directly; no API key needed, these are public endpoints)

## Installation

The package is published on Packagist as `rci/member-engagement`.

1. **Install the package**

   ```bash
   composer require rci/member-engagement
   ```

   If you're running the official `eveseat/docker` compose setup, add
   `rci/member-engagement` to the `SEAT_PLUGINS` env var instead and
   recreate the containers (`docker compose up -d`, not `restart` — env
   changes only apply on recreate).

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
   visibility, weighting, the Fleet Participation minimum size, the
   presence minimum, and the home and deployment region lists — one set of
   settings for the whole install, applying regardless of corporation. Sensible defaults apply automatically if you skip this.

Scheduled jobs register themselves automatically (via SeAT's own schedule
seeding on plugin boot) — no manual seeding step needed.

## Routes

- **My Activities:** `/member-engagement/dashboard` — the logged-in member's
  own figures (requires `view_own_activities`)
- **Corporation View:** `/member-engagement/director` — the four monthly
  leaderboards plus the Heartbeat list (requires `view_all_activities`).
  Scoped to registered SeAT members only; see Known Limitations
- **Settings:** `/member-engagement/settings` (requires `view_all_activities`)

## Scheduled Commands

Registered automatically once the plugin boots:

| Command | Schedule | Purpose |
|---|---|---|
| `member-engagement:sync` | every 15 min | Pull new activity from SeAT's locally-synced mining/killmail/wallet data |
| `member-engagement:aggregate` | every 30 min | Roll activities up into daily per-character stats |
| `member-engagement:resolve-names --limit=1000` | every 20 min | Resolve character/corp/alliance names via ESI |
| `member-engagement:snapshot-net-worth` | 04:23 daily | Record each account's asset value, wallet balance and asset regions |
| `member-engagement:finalize-month` | 02:15 on the 1st of the month | Roll the month that just ended into a finalized monthly snapshot, net worth included |

`sync` and `aggregate` size their own lookback window: normally they cover
the last couple of days, but each records when it last completed, and if
more than a day has passed (scheduler down, a bug fixed, ESI outage) the
next run widens the window to cover the gap plus a day, up to 90 days. That
way gaps close themselves rather than needing a manual backfill.

Passing `--days` explicitly overrides that, e.g.
`php artisan member-engagement:sync --days=90` for a larger backfill on
first install. Run
`php artisan member-engagement:finalize-month --all` once after installing to
backfill finalized monthly snapshots for every month that already has daily
stats data (the current, in-progress month is always computed live instead,
regardless of any snapshot).

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

- **Name resolution takes time on a fresh install**: at 1000 characters per
  20-minute cycle, a corporation with several thousand distinct characters in
  its activity history will take a few hours to fully resolve. Run
  `php artisan member-engagement:resolve-names --limit=10000` manually for a
  faster one-time backfill.
- **Scoped to the primary corporation**: accounts only appear if at least
  one of their linked characters is currently in the corporation Corp
  Wallet Manager is pointed at (`selected_corporation_id`). One character
  is enough - alts in a holding corp don't disqualify a main. Note the
  filter is per account, not per character: a qualifying account's figures
  still include every linked character, wherever they are. Nothing is
  filtered if Corp Wallet Manager isn't installed or hasn't been pointed
  at a corporation. My Activities is not filtered, so a member can always
  see their own figures.
- **Scoped to registered SeAT members**: EVE's ESI never reveals which
  characters share an account — the only linkage anyone can know is for
  characters whose owner has personally authenticated them into SeAT (main +
  alts). A corp member who's active but has never logged into SeAT simply has
  nothing to consolidate under and won't appear on Corporation View or My
  Activities, regardless of how much activity they generate. This improves
  automatically as more members register their characters in SeAT.
- **Net worth has no history before it starts**: SeAT only stores assets as
  they are right now, so the month-on-month change column stays blank until
  two months of snapshots have accumulated. Nothing can backfill it.
- **Asset values are estimates**: priced from ESI's `average_price`, falling
  back to the SDE's `invTypes.basePrice` where an item has no market price at
  all (supercarriers and the like can't be traded in Jita, so they'd
  otherwise count as worthless). Ships take the higher of the two, since thin
  markets understate capitals; that floor is not applied to other categories,
  where `basePrice` bears no relation to market value. Blueprint copies are
  excluded entirely - they price as originals, which on the dev data inflated
  the total by 4.5x.
  Assets in structures SeAT can't resolve are grouped as "unknown" and left
  out of the home presence calculation rather than counted as away.
- **Heartbeat can't backfill history**: ESI's online endpoint (and SeAT's
  `character_onlines` sync of it) only ever holds each character's MOST
  RECENT known login/logout, not a history. A day is only ever recorded as
  "logged in" if this plugin's own scheduled sync happened to run while
  that login was still the most recent one on record - once a later login
  overwrites it, that day is gone for good. This means Heartbeat becomes
  accurate from when it was first deployed, but can't retroactively
  reconstruct days further back than that.

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
