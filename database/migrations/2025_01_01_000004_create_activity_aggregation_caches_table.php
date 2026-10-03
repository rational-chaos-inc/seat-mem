<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('member_engagement_activity_aggregation_caches')) {
            Schema::create('member_engagement_activity_aggregation_caches', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->string('aggregation_period')->comment('day, week, month, quarter, year, custom');
                $table->enum('activity_type', ['mining', 'pvp_kill', 'pvp_loss', 'tax_wallet', 'all']);
                $table->date('period_start');
                $table->date('period_end');
                $table->decimal('total_value', 18, 2)->default(0);
                $table->integer('count')->default(0);
                $table->decimal('average_value', 18, 2)->default(0);
                $table->timestamps();

                $table->unique(['user_id', 'aggregation_period', 'activity_type', 'period_start', 'period_end'], 'uniq_agg');
                $table->index(['user_id', 'aggregation_period'], 'idx_user_period');
                $table->index(['activity_type', 'period_start'], 'idx_type_start');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('member_engagement_activity_aggregation_caches');
    }
};
