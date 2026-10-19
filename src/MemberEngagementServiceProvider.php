<?php

namespace RCI\MemberEngagement;

use Seat\Services\AbstractSeatPlugin;
use RCI\MemberEngagement\Services\AccountConsolidationService;
use RCI\MemberEngagement\Services\AggregationService;
use RCI\MemberEngagement\Services\DataCollectionService;
use RCI\MemberEngagement\Services\EntityResolutionService;
use RCI\MemberEngagement\Services\MonthlyAggregationService;
use RCI\MemberEngagement\Services\RankingService;

class MemberEngagementServiceProvider extends AbstractSeatPlugin
{
    public function getName(): string
    {
        return 'SeAT Member Engagement Module';
    }

    public function getPackageRepositoryUrl(): string
    {
        return 'https://github.com/rational-chaos-inc/seat-mem';
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
        $this->app->singleton(DataCollectionService::class);
        $this->app->singleton(AggregationService::class);
        $this->app->singleton(EntityResolutionService::class);
        $this->app->singleton(AccountConsolidationService::class);
        $this->app->singleton(MonthlyAggregationService::class);
        $this->app->singleton(RankingService::class);
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
            \RCI\MemberEngagement\Commands\AggregateStatsCommand::class,
            \RCI\MemberEngagement\Commands\GenerateTestDataCommand::class,
            \RCI\MemberEngagement\Commands\ResolveNamesCommand::class,
            \RCI\MemberEngagement\Commands\FinalizeMonthCommand::class,
        ]);
    }

    private function registerSchedules(): void
    {
        // Register database seeders with schedule definitions
        // SeAT handles scheduling through its own schedule management system
        $this->registerDatabaseSeeders(\RCI\MemberEngagement\Database\Seeders\ScheduleSeeder::class);
    }

}
