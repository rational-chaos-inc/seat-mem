<?php

namespace RCI\MemberEngagement\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RCI\MemberEngagement\Models\CapitalDaily;
use RCI\MemberEngagement\Models\CapitalMonthly;

/**
 * How the capital fleet has changed over time.
 *
 * Assets have no history in SeAT - the asset tables only ever describe
 * right now - so each reading has to be written down as it is taken. The
 * Capital Tracking refresh records the day's counts, and the month-end
 * roll-up keeps the last reading of each month.
 */
class CapitalHistoryService
{
    /**
     * The three bands the stacked chart is split into, bottom first.
     *
     * Freighters and the Rorqual do not fight, dreadnoughts through force
     * auxiliaries do, and titans and supercarriers are the ones a
     * director is really counting - so they get their own band rather
     * than being lost inside a combat total.
     */
    public const BANDS = [
        'support' => [
            'label' => 'Non-combat',
            'groups' => [883, 902, 513],
        ],
        'combat' => [
            'label' => 'Combat',
            'groups' => [485, 4594, 547, 5120, 1538],
        ],
        'capital' => [
            'label' => 'Titans & Supercarriers',
            'groups' => [30, 659],
        ],
    ];

    public function __construct(
        private CapitalTrackingService $capitals,
    ) {
    }

    /**
     * Record today's count for each class, replacing any earlier reading
     * from the same day. Called on every Capital Tracking refresh, so the
     * current month's series tracks the fleet as it changes.
     *
     * @param  array  $accounts  the by-account payload the tracker builds
     * @return int  classes recorded
     */
    public function recordToday(array $accounts): int
    {
        $today = Carbon::now()->toDateString();
        $counts = $this->totalsByGroup($accounts);

        foreach ($counts as $groupId => $hulls) {
            CapitalDaily::updateOrCreate(
                ['date' => $today, 'group_id' => $groupId],
                ['hulls' => $hulls]
            );
        }

        return count($counts);
    }

    /**
     * Keep the last reading of the given month as its final figure.
     *
     * @return int  classes finalized
     */
    public function finalizeMonth(Carbon $month): int
    {
        $start = $month->copy()->startOfMonth()->toDateString();
        $end = $month->copy()->endOfMonth()->toDateString();

        $lastDate = CapitalDaily::whereBetween('date', [$start, $end])->max('date');

        if (!$lastDate) {
            return 0;
        }

        $rows = CapitalDaily::whereDate('date', $lastDate)->get();

        foreach ($rows as $row) {
            CapitalMonthly::updateOrCreate(
                ['month' => $start, 'group_id' => $row->group_id],
                ['hulls' => $row->hulls]
            );
        }

        return $rows->count();
    }

    /**
     * Per class: what is held now, what was held at the end of last
     * month, and the move between them.
     *
     * The previous month is read from the finalized table, falling back
     * to the last daily reading inside that month - so the comparison
     * works before the first month-end roll-up has run.
     *
     * @param  array  $accounts  the by-account payload the tracker builds
     * @return array
     */
    public function classSummary(array $accounts): array
    {
        $now = $this->totalsByGroup($accounts);
        $previous = $this->previousMonthTotals();
        $images = $this->representativeTypes(array_keys($this->capitals->groups()), $accounts);
        $bandOf = $this->bandLookup();

        $rows = [];

        foreach ($this->capitals->groups() as $groupId => $groupName) {
            $held = $now[$groupId] ?? 0;
            $was = $previous[$groupId] ?? null;

            $rows[] = [
                'group_id' => $groupId,
                'group_name' => $groupName,
                'band' => $bandOf[$groupId] ?? 'combat',
                'hulls' => $held,
                'previous' => $was,
                // Null rather than zero where there is nothing to compare
                // against, so "no history yet" never reads as "no change".
                'change' => $was === null ? null : $held - $was,
                'type_id' => $images[$groupId] ?? null,
            ];
        }

        return $rows;
    }

    /**
     * The stacked series: one point per finalized month, then one per day
     * of the month in progress.
     *
     * @return array{labels: array, series: array, points: int, current_month_from: int|null}
     */
    public function stackedSeries(int $months = 12): array
    {
        $currentMonth = Carbon::now()->startOfMonth();

        $monthly = CapitalMonthly::where('month', '<', $currentMonth->toDateString())
            ->where('month', '>=', $currentMonth->copy()->subMonths($months)->toDateString())
            ->get()
            ->groupBy(fn($row) => $row->month->format('Y-m'));

        $daily = CapitalDaily::whereBetween('date', [
                $currentMonth->toDateString(),
                $currentMonth->copy()->endOfMonth()->toDateString(),
            ])
            ->get()
            ->groupBy(fn($row) => $row->date->toDateString());

        $labels = [];
        $series = array_fill_keys(array_keys(self::BANDS), []);

        foreach ($monthly->keys()->sort()->values() as $key) {
            $labels[] = Carbon::parse($key . '-01')->format('M Y');
            $this->appendBands($series, $monthly[$key]);
        }

        $currentMonthFrom = count($labels);

        foreach ($daily->keys()->sort()->values() as $key) {
            $labels[] = Carbon::parse($key)->format('j M');
            $this->appendBands($series, $daily[$key]);
        }

        return [
            'labels' => $labels,
            'series' => $series,
            'points' => count($labels),
            // Where the axis switches from months to days, so the view can
            // say so rather than leaving the reader to guess.
            'current_month_from' => count($daily) > 0 ? $currentMonthFrom : null,
        ];
    }

    /**
     * Sum one reading into each band.
     *
     * @param  array  $series  by reference, band => values
     */
    private function appendBands(array &$series, Collection $rows): void
    {
        $bandOf = $this->bandLookup();
        $totals = array_fill_keys(array_keys(self::BANDS), 0);

        foreach ($rows as $row) {
            $band = $bandOf[$row->group_id] ?? null;

            if ($band !== null) {
                $totals[$band] += $row->hulls;
            }
        }

        foreach ($totals as $band => $total) {
            $series[$band][] = $total;
        }
    }

    /** group_id => band key. */
    private function bandLookup(): array
    {
        $lookup = [];

        foreach (self::BANDS as $band => $definition) {
            foreach ($definition['groups'] as $groupId) {
                $lookup[$groupId] = $band;
            }
        }

        return $lookup;
    }

    /**
     * Hulls held now, per class.
     *
     * @return array<int, int>
     */
    private function totalsByGroup(array $accounts): array
    {
        $totals = array_fill_keys(array_keys($this->capitals->groups()), 0);

        foreach ($accounts as $account) {
            foreach ($account['counts'] as $groupId => $count) {
                $totals[(int) $groupId] = ($totals[(int) $groupId] ?? 0) + (int) $count;
            }
        }

        return $totals;
    }

    /**
     * What was held at the end of last month, per class. Prefers the
     * finalized figure and falls back to the last daily reading taken
     * inside that month.
     *
     * @return array<int, int>
     */
    private function previousMonthTotals(): array
    {
        $month = Carbon::now()->subMonthNoOverflow()->startOfMonth();

        $finalized = CapitalMonthly::whereDate('month', $month->toDateString())
            ->pluck('hulls', 'group_id');

        if ($finalized->isNotEmpty()) {
            return $finalized->map(fn($hulls) => (int) $hulls)->all();
        }

        $lastDate = CapitalDaily::whereBetween('date', [
            $month->toDateString(),
            $month->copy()->endOfMonth()->toDateString(),
        ])->max('date');

        if (!$lastDate) {
            return [];
        }

        return CapitalDaily::whereDate('date', $lastDate)
            ->pluck('hulls', 'group_id')
            ->map(fn($hulls) => (int) $hulls)
            ->all();
    }

    /**
     * A hull to picture each class with: the one the corporation holds
     * most of, so the image is of something they actually fly, falling
     * back to the first published hull in the class.
     *
     * @param  int[]  $groupIds
     * @return array<int, int>  group_id => type_id
     */
    private function representativeTypes(array $groupIds, array $accounts): array
    {
        $held = [];

        foreach ($accounts as $account) {
            foreach ($account['ships'] as $ship) {
                $group = (int) $ship['group_id'];
                $held[$group][$ship['type_id']] = ($held[$group][$ship['type_id']] ?? 0) + $ship['quantity'];
            }
        }

        $chosen = [];

        foreach (array_keys($this->capitals->groups()) as $groupId) {
            if (!empty($held[$groupId])) {
                arsort($held[$groupId]);
                $chosen[$groupId] = (int) array_key_first($held[$groupId]);
            }
        }

        $missing = array_diff($groupIds, array_keys($chosen));

        if (!empty($missing)) {
            $fallbacks = DB::table('invTypes')
                ->whereIn('groupID', $missing)
                ->where('published', 1)
                ->orderBy('typeID')
                ->get(['groupID', 'typeID'])
                ->groupBy('groupID')
                ->map(fn($rows) => (int) $rows->first()->typeID);

            foreach ($fallbacks as $groupId => $typeId) {
                $chosen[(int) $groupId] = $typeId;
            }
        }

        // A class whose hulls are too new for this install's SDE has no
        // invTypes row to fall back to, only the built-in table.
        foreach (array_diff($groupIds, array_keys($chosen)) as $groupId) {
            $chosen[$groupId] = $this->capitals->fallbackHullFor((int) $groupId);
        }

        return array_filter($chosen);
    }
}
