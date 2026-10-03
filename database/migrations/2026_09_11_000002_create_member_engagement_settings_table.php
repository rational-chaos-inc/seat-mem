<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('member_engagement_settings')) {
            Schema::create('member_engagement_settings', function (Blueprint $table) {
                $table->id();
                $table->bigInteger('corporation_id');
                $table->bigInteger('user_id');
                $table->string('login_visibility')->default('directors'); // directors, members, both
                $table->string('mining_visibility')->default('directors');
                $table->string('tax_bounty_visibility')->default('directors');
                $table->string('pvp_visibility')->default('directors');
                $table->string('fleet_participation_visibility')->default('directors');
                $table->decimal('mining_weight', 5, 2)->default(1.0);
                $table->decimal('tax_bounty_weight', 5, 2)->default(1.0);
                $table->decimal('pvp_weight', 5, 2)->default(1.0);
                $table->decimal('fleet_participation_weight', 5, 2)->default(1.0);
                $table->timestamps();

                $table->unique(['corporation_id', 'user_id'], 'settings_unique');
                $table->index('corporation_id', 'settings_corp_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('member_engagement_settings');
    }
};
