<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('member_engagement_activities')) {
            Schema::create('member_engagement_activities', function (Blueprint $table) {
                $table->id();
                $table->timestamp('activity_timestamp')->comment('When the activity occurred in-game');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->unsignedBigInteger('character_id')->comment('EVE character ID');
                $table->unsignedBigInteger('corporation_id')->nullable();
                $table->unsignedBigInteger('alliance_id')->nullable();
                $table->enum('activity_type', ['mining', 'pvp_kill', 'pvp_loss', 'tax_wallet']);
                $table->string('source_id')->nullable()->comment('ESI killmail ID, transaction ID, etc.');
                $table->json('metadata')->nullable()->comment('Type-specific data: quantities, ISK values, item names');
                $table->timestamps();

                // Indexes for performance
                $table->index(['character_id', 'activity_timestamp'], 'idx_char_ts');
                $table->index(['user_id', 'activity_timestamp'], 'idx_user_ts');
                $table->index(['activity_type', 'activity_timestamp'], 'idx_type_ts');
                $table->index(['corporation_id', 'activity_timestamp'], 'idx_corp_ts');
                $table->unique(['activity_type', 'source_id'], 'uniq_type_src')->whereNotNull('source_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('member_engagement_activities');
    }
};
