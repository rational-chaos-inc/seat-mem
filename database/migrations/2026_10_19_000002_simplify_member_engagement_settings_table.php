<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Settings used to be keyed per corporation (and, inadvertently, per
 * director), but engagement is now tracked per SeAT account regardless of
 * corporation - so there's only ever one group of settings for the whole
 * install. Collapses any existing per-corporation/per-director rows into a
 * single row and drops the columns that keyed them.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('member_engagement_settings', 'corporation_id')) {
            $keep = DB::table('member_engagement_settings')->orderBy('id')->first();
            if ($keep) {
                DB::table('member_engagement_settings')->where('id', '!=', $keep->id)->delete();
            }

            Schema::table('member_engagement_settings', function (Blueprint $table) {
                $table->dropUnique('settings_unique');
                $table->dropIndex('settings_corp_idx');
                $table->dropColumn(['corporation_id', 'user_id']);
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('member_engagement_settings', 'corporation_id')) {
            Schema::table('member_engagement_settings', function (Blueprint $table) {
                $table->bigInteger('corporation_id')->default(0)->after('id');
                $table->bigInteger('user_id')->default(0)->after('corporation_id');
            });

            Schema::table('member_engagement_settings', function (Blueprint $table) {
                $table->unique(['corporation_id', 'user_id'], 'settings_unique');
                $table->index('corporation_id', 'settings_corp_idx');
            });
        }
    }
};
