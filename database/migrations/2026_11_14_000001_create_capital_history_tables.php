<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hull counts per capital class over time.
 *
 * SeAT only stores assets as they are right now, so a fleet count is a
 * point-in-time reading that has to be recorded as it happens - nothing
 * can reconstruct it later. The daily table is rewritten through the day
 * as the Capital Tracking cache refreshes; the monthly table keeps the
 * last reading of each month so one month can be set against another.
 *
 * Counts are corporation-wide per class rather than per character: a
 * fleet total is the figure being tracked, and keeping it per character
 * would multiply the rows without changing anything that is displayed.
 * The cost is that re-scoping which accounts are tracked re-bases the
 * series rather than rewriting history, which is the honest behaviour
 * anyway - the old total really was the total at the time.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('member_engagement_capital_daily')) {
            Schema::create('member_engagement_capital_daily', function (Blueprint $table) {
                $table->id();
                $table->date('date');
                // invGroups.groupID of the hull class.
                $table->integer('group_id');
                $table->integer('hulls')->default(0);
                $table->timestamps();

                $table->unique(['date', 'group_id'], 'capital_daily_unique');
            });
        }

        if (!Schema::hasTable('member_engagement_capital_monthly')) {
            Schema::create('member_engagement_capital_monthly', function (Blueprint $table) {
                $table->id();
                // Always the first of the month, matching the other
                // monthly tables in this plugin.
                $table->date('month');
                $table->integer('group_id');
                $table->integer('hulls')->default(0);
                $table->timestamps();

                $table->unique(['month', 'group_id'], 'capital_monthly_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('member_engagement_capital_daily');
        Schema::dropIfExists('member_engagement_capital_monthly');
    }
};
