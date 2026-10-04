<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Caches for resolving character/corporation/alliance names via ESI.
 * SeAT's own character_infos/corporation_infos/alliances tables only cover
 * entities SeAT has actively synced (characters with a token, corporations
 * with director access, etc.) - most characters referenced in wallet
 * journals and killmails never appear there at all. These tables cache our
 * own ESI lookups (public, unauthenticated endpoints) instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('member_engagement_entity_names')) {
            Schema::create('member_engagement_entity_names', function (Blueprint $table) {
                $table->bigInteger('entity_id')->primary();
                $table->string('name');
                $table->string('category'); // character, corporation, alliance
                $table->timestamp('resolved_at');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('member_engagement_character_affiliations')) {
            Schema::create('member_engagement_character_affiliations', function (Blueprint $table) {
                $table->bigInteger('character_id')->primary();
                $table->bigInteger('corporation_id')->nullable();
                $table->bigInteger('alliance_id')->nullable();
                $table->bigInteger('faction_id')->nullable();
                $table->timestamp('resolved_at');
                $table->timestamps();

                $table->index('corporation_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('member_engagement_character_affiliations');
        Schema::dropIfExists('member_engagement_entity_names');
    }
};
