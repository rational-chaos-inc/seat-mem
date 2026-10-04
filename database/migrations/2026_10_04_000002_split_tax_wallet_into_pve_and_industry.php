<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Splits the combined "Tax/Bounty" metric into two:
 * - pve_bounty_tax: bounty prizes and daily goal payouts (character-attributed)
 * - industry_tax: industry facility tax between corporations, attributed to
 *   the character via the industry job referenced in the wallet description
 */
return new class extends Migration
{
    public function up(): void
    {
        // activities.activity_type enum: widen to include the new values,
        // migrate existing 'tax_wallet' rows to 'pve_bounty_tax'.
        DB::statement("ALTER TABLE member_engagement_activities MODIFY activity_type ENUM('mining', 'pvp_kill', 'pvp_loss', 'tax_wallet', 'pve_bounty_tax', 'industry_tax') NOT NULL");
        DB::table('member_engagement_activities')
            ->where('activity_type', 'tax_wallet')
            ->update(['activity_type' => 'pve_bounty_tax']);
        DB::statement("ALTER TABLE member_engagement_activities MODIFY activity_type ENUM('mining', 'pvp_kill', 'pvp_loss', 'pve_bounty_tax', 'industry_tax') NOT NULL");

        if (Schema::hasColumn('member_engagement_daily_stats', 'tax_bounty_amount')) {
            Schema::table('member_engagement_daily_stats', function ($table) {
                $table->renameColumn('tax_bounty_amount', 'pve_bounty_amount');
            });
        }
        if (!Schema::hasColumn('member_engagement_daily_stats', 'industry_tax_amount')) {
            Schema::table('member_engagement_daily_stats', function ($table) {
                $table->decimal('industry_tax_amount', 15, 2)->default(0)->after('pve_bounty_amount');
            });
        }

        if (Schema::hasColumn('member_engagement_settings', 'tax_bounty_visibility')) {
            Schema::table('member_engagement_settings', function ($table) {
                $table->renameColumn('tax_bounty_visibility', 'pve_bounty_visibility');
            });
        }
        if (Schema::hasColumn('member_engagement_settings', 'tax_bounty_weight')) {
            Schema::table('member_engagement_settings', function ($table) {
                $table->renameColumn('tax_bounty_weight', 'pve_bounty_weight');
            });
        }
        if (!Schema::hasColumn('member_engagement_settings', 'industry_tax_visibility')) {
            Schema::table('member_engagement_settings', function ($table) {
                $table->string('industry_tax_visibility')->default('directors')->after('pve_bounty_visibility');
            });
        }
        if (!Schema::hasColumn('member_engagement_settings', 'industry_tax_weight')) {
            Schema::table('member_engagement_settings', function ($table) {
                $table->decimal('industry_tax_weight', 5, 2)->default(1.0)->after('pve_bounty_weight');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('member_engagement_settings', 'industry_tax_weight')) {
            Schema::table('member_engagement_settings', fn ($table) => $table->dropColumn('industry_tax_weight'));
        }
        if (Schema::hasColumn('member_engagement_settings', 'industry_tax_visibility')) {
            Schema::table('member_engagement_settings', fn ($table) => $table->dropColumn('industry_tax_visibility'));
        }
        if (Schema::hasColumn('member_engagement_settings', 'pve_bounty_weight')) {
            Schema::table('member_engagement_settings', function ($table) {
                $table->renameColumn('pve_bounty_weight', 'tax_bounty_weight');
            });
        }
        if (Schema::hasColumn('member_engagement_settings', 'pve_bounty_visibility')) {
            Schema::table('member_engagement_settings', function ($table) {
                $table->renameColumn('pve_bounty_visibility', 'tax_bounty_visibility');
            });
        }

        if (Schema::hasColumn('member_engagement_daily_stats', 'industry_tax_amount')) {
            Schema::table('member_engagement_daily_stats', fn ($table) => $table->dropColumn('industry_tax_amount'));
        }
        if (Schema::hasColumn('member_engagement_daily_stats', 'pve_bounty_amount')) {
            Schema::table('member_engagement_daily_stats', function ($table) {
                $table->renameColumn('pve_bounty_amount', 'tax_bounty_amount');
            });
        }

        DB::statement("ALTER TABLE member_engagement_activities MODIFY activity_type ENUM('mining', 'pvp_kill', 'pvp_loss', 'tax_wallet', 'pve_bounty_tax', 'industry_tax') NOT NULL");
        DB::table('member_engagement_activities')
            ->where('activity_type', 'pve_bounty_tax')
            ->update(['activity_type' => 'tax_wallet']);
        DB::statement("ALTER TABLE member_engagement_activities MODIFY activity_type ENUM('mining', 'pvp_kill', 'pvp_loss', 'tax_wallet') NOT NULL");
    }
};
