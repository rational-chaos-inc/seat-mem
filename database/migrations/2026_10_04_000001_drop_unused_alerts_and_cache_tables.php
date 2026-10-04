<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Drops the activity_alerts and activity_aggregation_caches tables, part of
 * an alerts/caching feature that was scaffolded but never built out (no UI,
 * no working consumer) and has been removed from the codebase.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('member_engagement_activity_alerts');
        Schema::dropIfExists('member_engagement_activity_aggregation_caches');
    }

    public function down(): void
    {
        // Intentionally not recreated - the feature these tables backed no
        // longer exists in the codebase.
    }
};
