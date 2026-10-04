<?php

namespace RCI\MemberEngagement\Commands;

use Illuminate\Console\Command;
use RCI\MemberEngagement\Services\EntityResolutionService;

class ResolveNamesCommand extends Command
{
    protected $signature = 'member-engagement:resolve-names {--limit=5000 : Max characters to resolve per run}';

    protected $description = 'Resolve character names/corporations/alliances via ESI for characters referenced in activities';

    public function handle(EntityResolutionService $service): int
    {
        $limit = (int) $this->option('limit');

        $characterIds = $service->findUnresolvedCharacterIds($limit);

        if (empty($characterIds)) {
            $this->info('Nothing to resolve - all referenced characters are already cached.');
            return self::SUCCESS;
        }

        $this->info('Resolving ' . count($characterIds) . ' characters via ESI...');

        $resolved = $service->resolveCharacters($characterIds);

        $this->info("Resolved {$resolved} characters.");

        return self::SUCCESS;
    }
}
