<?php

namespace RCI\MemberEngagement\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ActivitiesCollected
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $count,
        public int $corporationId,
    ) {
    }
}
