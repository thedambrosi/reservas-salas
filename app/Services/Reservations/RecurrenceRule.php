<?php

namespace App\Services\Reservations;

use App\Enums\RecurrenceFrequency;

final readonly class RecurrenceRule
{
    public function __construct(
        public RecurrenceFrequency $frequency,
        public int $interval,
        public int $occurrences,
    ) {}

    /**
     * @param  array{frequency: string, interval?: int|string|null, count: int|string}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            frequency: RecurrenceFrequency::from($data['frequency']),
            interval: (int) ($data['interval'] ?? 1),
            occurrences: (int) $data['count'],
        );
    }
}
