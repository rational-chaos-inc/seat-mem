<?php

namespace RCI\MemberEngagement;

use Seat\Services\AbstractSeatPlugin;
use RCI\MemberEngagement\Services\ActivityCollectionService;
use RCI\MemberEngagement\Services\AggregationService;
use RCI\MemberEngagement\Services\ESIActivityService;
use RCI\MemberEngagement\Services\TaxWalletActivityService;
use RCI\MemberEngagement\Services\MiningActivityService;

class MemberEngagementServiceProvider extends AbstractSeatPlugin
{
    public function getName(): string
    {
        return 'SeAT Member Engagement Module';
    }

    public function getPackageRepositoryUrl(): string
    {
        return 'https://github.com/rational-chaos-inc/seat-mrp';
    }

    public function getPackagistPackageName(): string
    {
        return 'rci/member-engagement';
    }

    public function getPackagistVendorName(): string
    {
        return 'rci';
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/member-engagement.php', 'member-engagement');
        $this->registerPermissions(__DIR__ . '/Config/Permissions/member-engagement.permissions.php', 'member-engagement');
        $this->mergeConfigFrom(__DIR__ . '/Config/Menu/package.sidebar.php', 'package.sidebar');

        $this->registerServices();
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'member-engagement');
        $this->publishConfig();
        $this->publishMigrations();
        $this->registerRoutes();
        $this->registerViews();
        $this->registerCommands();
        $this->registerSchedules();
    }

    private function registerServices(): void
    {
        $this->app->singleton(ESIActivityService::class);
        $this->app->singleton(TaxWalletActivityService::class);
        $this->app->singleton(MiningActivityService::class);
        $this->app->singleton(ActivityCollectionService::class);
        $this->app->singleton(AggregationService::class);
        $this->app->singleton(\RCI\MemberEngagement\Services\AlertService::class);
        $this->app->singleton(\RCI\MemberEngagement\Services\DataCollectionService::class);
    }

    private function publishConfig(): void
    {
        $this->publishes([
            __DIR__ . '/../config/member-engagement.php' => config_path('member-engagement.php'),
        ], 'config');
    }

    private function publishMigrations(): void
    {
        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'migrations');
    }

    private function registerRoutes(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
    }

    private function registerViews(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'member-engagement');

        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/member-engagement'),
        ], 'views');
    }

    private function registerCommands(): void
    {
        $this->commands([
            \RCI\MemberEngagement\Commands\SyncActivitiesCommand::class,
            \RCI\MemberEngagement\Commands\GenerateTestDataCommand::class,
            \RCI\MemberEngagement\Commands\AggregateStatsCommand::class,
            \RCI\MemberEngagement\Commands\CollectActivitiesCommand::class,
            \RCI\MemberEngagement\Commands\CacheAggregationsCommand::class,
            \RCI\MemberEngagement\Commands\CheckAlertsCommand::class,
        ]);
    }

    private function registerSchedules(): void
    {
        // Register database seeders with schedule definitions
        // SeAT handles scheduling through its own schedule management system
        $this->registerDatabaseSeeders(\RCI\MemberEngagement\Database\Seeders\ScheduleSeeder::class);
    }

}
