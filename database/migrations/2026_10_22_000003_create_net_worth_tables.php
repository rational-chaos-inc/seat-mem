<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Net worth is a balance, not a flow - so unlike the activity tables these
 * hold point-in-time snapshots. The daily table is overwritten each run;
 * the monthly table keeps the final snapshot of each month so one month
 * can be compared against the last.
 *
 * Stored per character and consolidated at read time, matching the rest of
 * the plugin, so re-linking an alt doesn't corrupt historical rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('member_engagement_net_worth_daily')) {
            Schema::create('member_engagement_net_worth_daily', function (Blueprint $table) {
                $table->id();
                $table->date('date');
                $table->bigInteger('character_id');
                $table->bigInteger('corporation_id')->nullable();
                $table->decimal('asset_value', 20, 2)->default(0);
                $table->decimal('wallet_balance', 20, 2)->default(0);
                // region_id => asset value in that region. Assets whose
                // location can't be resolved are keyed "unknown".
                $table->json('regions')->nullable();
                $table->timestamps();

                $table->unique(['date', 'character_id'], 'net_worth_daily_unique');
                $table->index('character_id', 'net_worth_daily_char_idx');
            });
        }

        if (!Schema::hasTable('member_engagement_net_worth_monthly')) {
            Schema::create('member_engagement_net_worth_monthly', function (Blueprint $table) {
                $table->id();
                $table->date('month');
                $table->bigInteger('character_id');
                $table->bigInteger('corporation_id')->nullable();
                $table->decimal('asset_value', 20, 2)->default(0);
                $table->decimal('wallet_balance', 20, 2)->default(0);
                $table->json('regions')->nullable();
                $table->timestamps();

                $table->unique(['month', 'character_id'], 'net_worth_monthly_unique');
                $table->index('character_id', 'net_worth_monthly_char_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('member_engagement_net_worth_daily');
        Schema::dropIfExists('member_engagement_net_worth_monthly');
    }
};
