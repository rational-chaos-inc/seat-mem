<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records when sync/aggregate last completed, so each run can size its own
 * lookback window to cover whatever it missed instead of relying on a fixed
 * one that silently leaves gaps behind.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_engagement_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('member_engagement_settings', 'last_sync_at')) {
                $table->timestamp('last_sync_at')->nullable();
            }
            if (!Schema::hasColumn('member_engagement_settings', 'last_aggregate_at')) {
                $table->timestamp('last_aggregate_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('member_engagement_settings', function (Blueprint $table) {
            $table->dropColumn(['last_sync_at', 'last_aggregate_at']);
        });
    }
};
