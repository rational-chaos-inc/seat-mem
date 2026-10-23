<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Regions the corporation is currently deployed to. Assets there count
 * towards the presence minimum just as home regions do - a member who has
 * moved their ships to the deployment is where they are supposed to be.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_engagement_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('member_engagement_settings', 'deployment_regions')) {
                $table->json('deployment_regions')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('member_engagement_settings', function (Blueprint $table) {
            if (Schema::hasColumn('member_engagement_settings', 'deployment_regions')) {
                $table->dropColumn('deployment_regions');
            }
        });
    }
};
