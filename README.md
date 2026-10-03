# SeAT Member Engagement Module

A SeAT plugin for tracking corporation member activity across mining, PvP combat, and tax wallet contributions.

## Features

- Track member activities: mining, PvP kills/losses, tax wallet bounties
- Aggregate data per user (main character + linked alts)
- View totals and averages across linked characters
- Configurable time windows (daily, weekly, monthly, quarterly, yearly)
- Member dashboard for personal activity tracking
- Director dashboard with corporation-wide summaries and league tables
- Member drill-down and search/filter capabilities
- Activity alerts and target setting
- Hybrid Manager-Core integration (optional)

## Installation

### Prerequisites
- SeAT 5.x (Laravel 10)
- PHP 8.1+
- Database with migration support
- Redis or queue driver (for scheduled jobs)
- EVE Online ESI API access (for kills/losses)
- Mining-Manager plugin (for mining data) - optional but recommended

### Step-by-Step Installation

1. **Add to your SeAT installation**

   For development/local testing:
   ```bash
   # Add to composer.json repositories section
   "repositories": [
       {
           "type": "vcs",
           "url": "https://github.com/rational-chaos-inc/seat-mrp.git"
       }
   ]
   ```

   Then install:
   ```bash
   composer require rci/member-engagement
   ```

2. **Publish configuration and views**
   ```bash
   php artisan vendor:publish --tag=member-engagement-config
   php artisan vendor:publish --tag=member-engagement-views
   ```

3. **Run database migrations**
   ```bash
   php artisan migrate
   ```

4. **Seed scheduled tasks** (via database seeder)
   ```bash
   php artisan db:seed --class="\RCI\MemberEngagement\Database\Seeders\ScheduleSeeder"
   ```

5. **Configure permissions** (if using role-based access)
   - Log into SeAT admin panel
   - Go to: Admin → Permissions & Roles
   - Assign `view_own_activities` to members
   - Assign `view_all_activities` to directors

6. **Configure scheduler** (if not already running)

   Ensure Laravel's task scheduler runs every minute:
   ```bash
   * * * * * cd /path/to/seat && php artisan schedule:run >> /dev/null 2>&1
   ```

   Or use the SeAT Scheduler UI if available:
   - Admin → Scheduler

7. **Verify installation**
   ```bash
   # Check if service provider is registered
   php artisan list | grep member-engagement

   # Verify permissions exist
   php artisan tinker
   >>> \Spatie\Permission\Models\Permission::where('name', 'like', '%member-engagement%')->get()

   # Verify schedule is registered (if Manager-Core available)
   php artisan manager-core:diagnose --detailed
   ```

### Optional: Manager-Core Integration

To enable real-time activity updates and cross-plugin communication:

```bash
composer require seatplus/manager-core
php artisan migrate
```

The plugin will auto-detect Manager-Core and enable:
- Real-time kill/loss detection (~2 minutes vs 20-30 minutes)
- Mining data via events
- Cross-plugin alerts

## Configuration

Edit `config/member-engagement.php` to configure:

```php
return [
    'enabled' => env('MEMBER_ENGAGEMENT_ENABLED', true),
    'polling_interval' => env('MEMBER_ENGAGEMENT_POLLING_INTERVAL', 5),  // minutes
    'aggregation_cache_ttl' => env('MEMBER_ENGAGEMENT_CACHE_TTL', 0),     // seconds (0 = disabled)
    'esi.retry_attempts' => 3,
    'esi.retry_delay_seconds' => 2,
    'time_windows' => ['day', 'week', 'month', 'quarter', 'year'],
    'activity_types' => ['mining', 'pvp_kill', 'pvp_loss', 'tax_wallet'],
    'manager_core_integration' => true,
    'alerts.enabled' => true,
    'alerts.check_interval' => 5,  // minutes
];
```

## Post-Installation

### Start collecting activities
Activities begin collecting automatically every 5 minutes via scheduled job. First run should complete within 5 minutes depending on corporation size.

### Monitor collection
```bash
# View recent collection logs
php artisan tinker
>>> \Illuminate\Support\Facades\Log::tail('laravel.log', 50)

# Or via file
tail -f storage/logs/laravel.log | grep 'member-engagement'
```

### Access the plugin
- **Member Dashboard:** `/member-engagement/dashboard`
- **Director Dashboard:** `/member-engagement/director` (requires `view_all_activities` permission)
- **League Tables:** `/member-engagement/league-tables`
- **API:** `/api/member-engagement/...`

## Uninstallation

To remove the plugin:

```bash
# Disable the plugin from SeAT admin or composer.json
composer remove rci/member-engagement

# Remove database tables (careful!)
php artisan migrate:rollback --path=vendor/rci/member-engagement/database/migrations
```

## Troubleshooting

**Activities not collecting:**
- Check scheduler is running: `php artisan schedule:list`
- Check logs: `storage/logs/laravel.log`
- Verify characters have ESI tokens: SeAT Admin → Characters
- Check ESI API status: https://status.eve-esi.com/

**No permissions appearing:**
- Run migration: `php artisan migrate`
- Seed permissions: `php artisan db:seed --class="\RCI\MemberEngagement\Database\Seeders\ScheduleSeeder"`
- Clear config cache: `php artisan config:cache`

**Manager-Core integration not working:**
- Verify MC installed: `composer show seatplus/manager-core`
- Check MC is bootstrapped: `php artisan list | grep manager-core`
- Run diagnostic: `php artisan manager-core:diagnose --detailed`

## Architecture

### Phase 1: Foundation (✓ Complete)
- [x] Plugin bootstrap and service provider
- [x] Database schema (activities, alerts, aggregation cache)
- [x] Eloquent models with aggregation scopes
- [x] Core service layer structure (ESI, tax wallet, mining, orchestration)
- [x] Configuration and route placeholders
- [x] Character linking via SeAT's built-in relationships

### Phase 2: Data Collection (In Progress)
- [ ] ESI API integration for kills/losses
- [ ] Tax wallet data collection
- [ ] Mining data integration (via Mining-Manager)
- [ ] Activity collection orchestration job

### Phase 3: Aggregation & Performance (✓ Complete)
- [x] On-the-fly aggregation logic with comprehensive metrics
- [x] Optional caching layer (configurable TTL)
- [x] Time window calculations (day/week/month/quarter/year)
- [x] Character grouping logic with per-character breakdowns
- [x] League table generation and ranking
- [x] Activity trend analysis over time
- [x] Cache management commands

### Phase 4: Access Control & Views (✓ Complete)
- [x] Permission model implementation (view_own_activities, view_all_activities)
- [x] Member dashboard with time windows and activity breakdown
- [x] Director dashboard with corp-wide summaries
- [x] League tables and member rankings
- [x] Character detail drilldown (member view)
- [x] Member detail drilldown (director view)
- [x] Permission middleware and route protection

### Phase 5: API & Advanced Features (✓ Complete)
- [x] REST API endpoints (activities, aggregations, league tables)
- [x] Alert system (threshold, unusual activity, member alerts)
- [x] Notification dispatch (in-app, email, webhook stubs)
- [x] Alert management (CRUD via API)
- [x] Test notification endpoint
- [x] Scheduled alert checking

### Phase 6: Manager-Core Integration (✓ Complete)
- [x] EventBus subscription for character/mining/ESI events
- [x] ESI FastPoll integration for real-time kills/losses
- [x] Event listeners for Mining-Manager integration
- [x] Event publishing (activities, alerts)
- [x] Cross-plugin communication via PluginBridge
- [x] Graceful fallback when Manager-Core unavailable

## Development Notes

- Uses Laravel PSR-4 autoloading
- Database migrations auto-discovered by SeAT
- Permissions managed via Spatie/Laravel-Permission
- Graceful fallback when Manager-Core is not available

## License

MIT
