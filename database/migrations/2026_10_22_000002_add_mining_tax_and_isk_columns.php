<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mining tax (sourced from Mining Manager's daily summaries) and the ISK
 * value destroyed/lost on killmails, which SeAT doesn't store - it's
 * computed from the victim's hull plus destroyed/dropped items priced
 * against market_prices.
 *
 * decimal(20,2) rather than the (15,2) used by the older columns: a single
 * character-day of fleet kills can credit well past what (15,2) holds.
 */
return new class extends Migration
{
    private const COLUMNS = ['mining_tax_amount', 'isk_destroyed', 'isk_lost'];

    public function up(): void
    {
        foreach (['member_engagement_daily_stats', 'member_engagement_monthly_stats'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                foreach (self::COLUMNS as $column) {
                    if (!Schema::hasColumn($table, $column)) {
                        $blueprint->decimal($column, 20, 2)->default(0);
                    }
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['member_engagement_daily_stats', 'member_engagement_monthly_stats'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                foreach (self::COLUMNS as $column) {
                    if (Schema::hasColumn($table, $column)) {
                        $blueprint->dropColumn($column);
                    }
                }
            });
        }
    }
};
