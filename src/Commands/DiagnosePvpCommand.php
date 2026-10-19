<?php

namespace RCI\MemberEngagement\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RCI\MemberEngagement\Models\Activity;
use RCI\MemberEngagement\Models\DailyMemberStat;
use RCI\MemberEngagement\Models\MemberEngagementSetting;
use RCI\MemberEngagement\Services\AccountConsolidationService;

/**
 * Walks the PvP Kills / Fleet Participation pipeline stage by stage for the
 * given month, to pinpoint where data disappears when Corporation View shows
 * nothing despite real kills having happened - without needing direct DB
 * access to whichever server this is run on.
 */
class DiagnosePvpCommand extends Command
{
    protected $signature = 'member-engagement:diagnose-pvp {--month= : YYYY-MM, defaults to the current month}';

    protected $description = 'Diagnose why PvP Kills / Fleet Participation may be showing empty on Corporation View';

    public function handle(AccountConsolidationService $consolidation): int
    {
        $month = $this->option('month')
            ? Carbon::parse($this->option('month') . '-01')->startOfMonth()
            : Carbon::now()->startOfMonth();
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        $this->info("Diagnosing PvP Kills / Fleet Participation for {$month->format('M Y')} ({$start->toDateString()} to {$end->toDateString()})");
        $this->newLine();

        // [1] SeAT core's own killmail sync
        $killmailCount = DB::table('killmail_details')
            ->whereBetween('killmail_time', [$start, $end])
            ->count();
        $attackerRowCount = DB::table('killmail_attackers')
            ->join('killmail_details', 'killmail_details.killmail_id', '=', 'killmail_attackers.killmail_id')
            ->whereBetween('killmail_details.killmail_time', [$start, $end])
            ->count();
        $attackersWithCharacter = DB::table('killmail_attackers')
            ->join('killmail_details', 'killmail_details.killmail_id', '=', 'killmail_attackers.killmail_id')
            ->whereBetween('killmail_details.killmail_time', [$start, $end])
            ->whereNotNull('killmail_attackers.character_id')
            ->count();

        $this->line('[1] SeAT core killmail sync');
        $this->line("    killmail_details rows this month: {$killmailCount}");
        $this->line("    killmail_attackers rows (joined): {$attackerRowCount}");
        $this->line("    ...of which have a character_id: {$attackersWithCharacter}");
        if ($killmailCount === 0) {
            $this->warn('    => No killmails synced for this month at all. Check SeAT\'s own killmail sync' .
                ' (ESI killmail scope granted, corp roles, scheduler running) - this plugin only reads' .
                ' from killmail_details/killmail_attackers/killmail_victims, it doesn\'t fetch from ESI itself.');
        }
        $this->newLine();

        // [2] This plugin's own Activity records, derived from the above
        $pvpKillActivities = Activity::where('activity_type', 'pvp_kill')
            ->whereBetween('activity_timestamp', [$start, $end])
            ->get();
        $pvpLossActivities = Activity::where('activity_type', 'pvp_loss')
            ->whereBetween('activity_timestamp', [$start, $end])
            ->count();

        $this->line('[2] This plugin\'s Activity records (member-engagement:sync)');
        $this->line("    pvp_kill Activity rows this month: {$pvpKillActivities->count()}");
        $this->line("    pvp_loss Activity rows this month: {$pvpLossActivities}");
        if ($killmailCount > 0 && $pvpKillActivities->isEmpty()) {
            $this->warn('    => Killmails exist but no pvp_kill Activity rows were created. Run' .
                ' `php artisan member-engagement:sync --days=31` and check storage/logs/laravel-*.log' .
                ' for "Error collecting kill data" - likely an exception in collectKillData(), or the' .
                ' sync command simply hasn\'t run with a wide enough --days window to cover this month yet.');
        }
        $this->newLine();

        // [3] Are the attackers/victims actually registered SeAT accounts?
        // Corporation View and My Activities only ever show registered
        // members and their known alts - a real kill by a character who's
        // never authenticated into SeAT is correctly excluded, not a bug.
        $killCharacterIds = $pvpKillActivities->pluck('character_id')->unique()->filter()->values()->all();
        $groupMap = empty($killCharacterIds) ? [] : $consolidation->resolveGroups($killCharacterIds);
        $registeredCount = count($groupMap);
        $unregisteredCount = count($killCharacterIds) - $registeredCount;

        $this->line('[3] Registered-SeAT-account scoping (Corporation View only shows these)');
        $this->line('    Distinct characters with a pvp_kill this month: ' . count($killCharacterIds));
        $this->line("    ...of which resolve to a registered SeAT account: {$registeredCount}");
        $this->line("    ...of which are NOT linked to any SeAT account: {$unregisteredCount}");
        if (!empty($killCharacterIds) && $registeredCount === 0) {
            $this->warn('    => Kills exist, but none of the characters who got them are linked to a' .
                ' registered SeAT account (main or alt). This is the documented "scoped to registered' .
                ' members" behaviour (see README Known Limitations), not a bug - Corporation View will' .
                ' show them once their account is registered in SeAT.');
        }
        $this->newLine();

        // [4] Daily rollup
        $dailyKillRows = DailyMemberStat::whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->where('pvp_kills', '>', 0)
            ->get(['character_id', 'pvp_kills', 'fleet_participation']);
        $dailyFleetRows = $dailyKillRows->where('fleet_participation', '>', 0);

        $this->line('[4] Daily rollup (member-engagement:aggregate)');
        $this->line("    DailyMemberStat rows this month with pvp_kills > 0: {$dailyKillRows->count()} (sum: {$dailyKillRows->sum('pvp_kills')})");
        $this->line("    ...of which also have fleet_participation > 0: {$dailyFleetRows->count()} (sum: {$dailyFleetRows->sum('fleet_participation')})");
        if ($pvpKillActivities->isNotEmpty() && $dailyKillRows->isEmpty()) {
            $this->warn('    => pvp_kill Activity rows exist but none made it into the daily rollup.' .
                ' Run `php artisan member-engagement:aggregate --days=31` to rebuild this month.');
        }
        $this->newLine();

        // [5] Fleet Participation's configured minimum size
        $minSize = MemberEngagementSetting::current()->fleet_participation_min_size;
        $attackerCountDistribution = $pvpKillActivities
            ->map(fn($a) => $a->metadata['attacker_count'] ?? 1)
            ->countBy()
            ->sortKeys();

        $this->line('[5] Fleet Participation minimum size (Settings page)');
        $this->line("    Configured minimum: {$minSize}");
        $this->line('    Attacker-count distribution across this month\'s pvp_kill rows: ' .
            ($attackerCountDistribution->isEmpty() ? '(none)' : $attackerCountDistribution->map(
                fn($count, $size) => "{$size} attacker(s): {$count} kill(s)"
            )->implode(', ')));
        if ($pvpKillActivities->isNotEmpty() && $dailyFleetRows->isEmpty() && $attackerCountDistribution->keys()->max() < $minSize) {
            $this->warn("    => No kill this month had {$minSize}+ attackers, so Fleet Participation is" .
                ' correctly 0 even though PvP Kills has data - consider lowering the minimum on the' .
                ' Settings page if this seems too strict for your fleet sizes.');
        }
        $this->newLine();

        $this->info('Diagnosis complete.');

        return self::SUCCESS;
    }
}
