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
    protected $signature = 'member-engagement:diagnose-pvp
        {--month= : YYYY-MM, defaults to the current month}
        {--killmail= : Trace one specific killmail_id (e.g. from zkillboard) through the whole pipeline instead of summarizing a month}';

    protected $description = 'Diagnose why PvP Kills / Fleet Participation may be showing empty on Corporation View';

    public function handle(AccountConsolidationService $consolidation): int
    {
        if ($killmailId = $this->option('killmail')) {
            return $this->traceKillmail((int) $killmailId, $consolidation);
        }

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

    /**
     * Traces one real killmail_id (e.g. copied from zkillboard) through
     * every stage this plugin depends on, to show exactly where it stops
     * showing up - independent of any month-wide aggregate counts.
     */
    private function traceKillmail(int $killmailId, AccountConsolidationService $consolidation): int
    {
        $this->info("Tracing killmail {$killmailId}");
        $this->newLine();

        // [1] Does SeAT's own sync even have this killmail?
        $detail = DB::table('killmail_details')->where('killmail_id', $killmailId)->first();

        $this->line('[1] SeAT core killmail sync');
        if (!$detail) {
            $this->warn("    killmail_details has NO row for {$killmailId}.");
            $this->warn('    => SeAT itself never synced this kill. This plugin only reads from' .
                ' killmail_details/killmail_attackers/killmail_victims - it never calls ESI for' .
                ' killmails directly. Check SeAT\'s own killmail sync (ESI killmail scope granted' .
                ' on at least one character/corp role, the sync job actually running, and that it' .
                ' has reached back far enough to cover this killmail\'s date).');
            $this->newLine();
            $this->info('Trace stops here - nothing downstream can work without this row.');
            return self::SUCCESS;
        }
        $this->line("    killmail_time: {$detail->killmail_time}");

        // [2] Attackers and victim on this specific killmail
        $attackers = DB::table('killmail_attackers')
            ->where('killmail_id', $killmailId)
            ->get(['character_id', 'corporation_id', 'final_blow']);
        $victim = DB::table('killmail_victims')->where('killmail_id', $killmailId)->first();

        $this->newLine();
        $this->line('[2] Attackers / victim on this killmail');
        $this->line("    Attacker rows: {$attackers->count()} (attacker_count used for Fleet Participation)");
        foreach ($attackers as $attacker) {
            $finalBlow = $attacker->final_blow ? ' (final blow)' : '';
            $this->line("      - character_id {$attacker->character_id}, corp {$attacker->corporation_id}{$finalBlow}");
        }
        if ($victim) {
            $this->line("    Victim: character_id {$victim->character_id}, corp {$victim->corporation_id}");
        }

        // [3] Did this plugin turn each attacker into an Activity row?
        $this->newLine();
        $this->line('[3] This plugin\'s Activity records (member-engagement:sync)');
        $attackerCharacterIds = $attackers->pluck('character_id')->filter()->values();
        foreach ($attackerCharacterIds as $characterId) {
            $sourceId = "kill_{$killmailId}_{$characterId}";
            $exists = Activity::where('source_id', $sourceId)->exists();
            $this->line('    character_id ' . $characterId . ': ' . ($exists ? 'Activity row exists' : 'MISSING - not synced'));
        }
        if ($victim) {
            $lossSourceId = "loss_{$killmailId}_{$victim->character_id}";
            $lossExists = Activity::where('source_id', $lossSourceId)->exists();
            $this->line('    victim ' . $victim->character_id . ' (loss): ' . ($lossExists ? 'Activity row exists' : 'MISSING - not synced'));
        }

        // [4] Registered-SeAT-account scoping
        $this->newLine();
        $this->line('[4] Registered-SeAT-account scoping (Corporation View only shows these)');
        $groupMap = $attackerCharacterIds->isEmpty() ? [] : $consolidation->resolveGroups($attackerCharacterIds->all());
        foreach ($attackerCharacterIds as $characterId) {
            $status = isset($groupMap[$characterId])
                ? "registered (consolidates under main character_id {$groupMap[$characterId]})"
                : 'NOT linked to any SeAT account - excluded from Corporation View by design';
            $this->line("    character_id {$characterId}: {$status}");
        }

        return self::SUCCESS;
    }
}
