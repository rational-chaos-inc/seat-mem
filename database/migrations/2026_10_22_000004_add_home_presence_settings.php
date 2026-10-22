<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Home presence: which regions count as "home", and how much of an
 * account's asset value has to sit in them before it stops being flagged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_engagement_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('member_engagement_settings', 'minimum_home_presence')) {
                $table->unsignedInteger('minimum_home_presence')->default(80);
            }
            if (!Schema::hasColumn('member_engagement_settings', 'home_regions')) {
                $table->json('home_regions')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('member_engagement_settings', function (Blueprint $table) {
            $table->dropColumn(['minimum_home_presence', 'home_regions']);
        });
    }
};
