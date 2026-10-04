<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('member_engagement_settings', 'fleet_participation_min_size')) {
            Schema::table('member_engagement_settings', function ($table) {
                $table->unsignedInteger('fleet_participation_min_size')->default(5)->after('fleet_participation_weight');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('member_engagement_settings', 'fleet_participation_min_size')) {
            Schema::table('member_engagement_settings', fn ($table) => $table->dropColumn('fleet_participation_min_size'));
        }
    }
};
