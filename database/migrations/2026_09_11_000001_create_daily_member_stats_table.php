<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('member_engagement_daily_stats')) {
            Schema::create('member_engagement_daily_stats', function (Blueprint $table) {
                $table->id();
                $table->date('date');
                $table->bigInteger('corporation_id');
                $table->bigInteger('character_id');
                $table->boolean('logged_in')->default(false);
                $table->bigInteger('mining_quantity')->default(0);
                $table->decimal('mining_value', 15, 2)->default(0);
                $table->decimal('tax_bounty_amount', 15, 2)->default(0);
                $table->integer('pvp_kills')->default(0);
                $table->integer('pvp_losses')->default(0);
                $table->integer('fleet_participation')->default(0);
                $table->timestamps();

                $table->unique(['date', 'corporation_id', 'character_id'], 'daily_stats_unique');
                $table->index('corporation_id', 'daily_stats_corp_idx');
                $table->index('character_id', 'daily_stats_char_idx');
                $table->index('date', 'daily_stats_date_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('member_engagement_daily_stats');
    }
};
