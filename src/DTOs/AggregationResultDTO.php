<?php

namespace RCI\MemberEngagement\DTOs;

class AggregationResultDTO
{
    public function __construct(
        public readonly string $timeWindow,
        public readonly ?string $activityType,
        public readonly float $total,
        public readonly int $count,
        public readonly float $average,
        public readonly int $characterCount,
        public readonly float $averagePerCharacter,
        public readonly array $byType = [],
        public readonly array $byCharacter = [],
        public readonly bool $cached = false,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            timeWindow: $data['time_window'] ?? 'custom',
            activityType: $data['activity_type'] ?? null,
            total: (float) ($data['total'] ?? 0),
            count: (int) ($data['count'] ?? 0),
            average: (float) ($data['average'] ?? 0),
            characterCount: (int) ($data['character_count'] ?? 0),
            averagePerCharacter: (float) ($data['average_per_character'] ?? 0),
            byType: $data['by_type'] ?? [],
            byCharacter: $data['by_character'] ?? [],
            cached: $data['cached'] ?? false,
        );
    }

    public function toArray(): array
    {
        return [
            'time_window' => $this->timeWindow,
            'activity_type' => $this->activityType,
            'total' => $this->total,
            'count' => $this->count,
            'average' => $this->average,
            'character_count' => $this->characterCount,
            'average_per_character' => $this->averagePerCharacter,
            'by_type' => $this->byType,
            'by_character' => $this->byCharacter,
            'cached' => $this->cached,
        ];
    }

    public function getPercentageForType(string $type): float
    {
        return $this->byType[$type]['percentage'] ?? 0;
    }

    public function getTopCharacter(): ?array
    {
        if (empty($this->byCharacter)) {
            return null;
        }

        $top = array_reduce(
            $this->byCharacter,
            fn($carry, $char) => $carry === null || $char['total'] > $carry['total'] ? $char : $carry,
            null
        );

        return $top;
    }
}
