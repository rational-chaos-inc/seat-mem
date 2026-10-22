<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PLEX lives in the account-wide vault, so it has no station, structure or
 * region - it was landing in the "unknown location" bucket and dragging
 * home presence down with it. It belongs alongside the wallet instead.
 *
 * Kept as its own column rather than folded into wallet_balance so the two
 * stay separable; the dashboard presents them together.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['member_engagement_net_worth_daily', 'member_engagement_net_worth_monthly'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                if (!Schema::hasColumn($table, 'plex_value')) {
                    $blueprint->decimal('plex_value', 20, 2)->default(0);
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['member_engagement_net_worth_daily', 'member_engagement_net_worth_monthly'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                if (Schema::hasColumn($table, 'plex_value')) {
                    $blueprint->dropColumn('plex_value');
                }
            });
        }
    }
};
