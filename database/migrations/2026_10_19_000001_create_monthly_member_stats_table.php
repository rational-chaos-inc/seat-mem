<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Finalized monthly rollups of member_engagement_daily_stats, one row per
 * corporation+character+month. Written by member-engagement:finalize-month
 * (scheduled for the 1st of each month, finalizing the previous month) so
 * the Rankings dashboard has a stable, fast-to-query historical record that
 * doesn't shift if daily stats are later corrected/re-aggregated. The
 * current, still-in-progress month is never read from this table - the
 * Rankings dashboard computes it live from daily_stats instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('member_engagement_monthly_stats')) {
            Schema::create('member_engagement_monthly_stats', function (Blueprint $table) {
                $table->id();
                $table->date('month'); // always the 1st of the month
                $table->bigInteger('corporation_id');
                $table->bigInteger('character_id');
                $table->bigInteger('mining_quantity')->default(0);
                $table->decimal('pve_bounty_amount', 15, 2)->default(0);
                $table->decimal('industry_tax_amount', 15, 2)->default(0);
                $table->integer('pvp_kills')->default(0);
                $table->integer('pvp_losses')->default(0);
                $table->integer('fleet_participation')->default(0);
                $table->timestamps();

                $table->unique(['month', 'corporation_id', 'character_id'], 'monthly_stats_unique');
                $table->index('corporation_id', 'monthly_stats_corp_idx');
                $table->index('character_id', 'monthly_stats_char_idx');
                $table->index('month', 'monthly_stats_month_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('member_engagement_monthly_stats');
    }
};
