<?php

use App\Enums\RecurrenceFrequency;
use App\Services\Reservations\RecurrenceExpander;
use App\Services\Reservations\RecurrenceRule;
use App\Services\Reservations\TimeInterval;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->expander = new RecurrenceExpander;
    $this->first = new TimeInterval(
        CarbonImmutable::parse('2026-09-07T09:00:00+00:00'),
        CarbonImmutable::parse('2026-09-07T10:00:00+00:00'),
    );
});

it('expands a weekly rule into evenly spaced occurrences', function () {
    $occurrences = $this->expander->expand($this->first, new RecurrenceRule(RecurrenceFrequency::Weekly, 1, 4));

    expect($occurrences)->toHaveCount(4)
        ->and($occurrences[0]->start->toDateString())->toBe('2026-09-07')
        ->and($occurrences[1]->start->toDateString())->toBe('2026-09-14')
        ->and($occurrences[2]->start->toDateString())->toBe('2026-09-21')
        ->and($occurrences[3]->start->toDateString())->toBe('2026-09-28');

    foreach ($occurrences as $occurrence) {
        expect($occurrence->durationInMinutes())->toBe(60)
            ->and($occurrence->start->format('H:i'))->toBe('09:00');
    }
});

it('honours a daily frequency', function () {
    $occurrences = $this->expander->expand($this->first, new RecurrenceRule(RecurrenceFrequency::Daily, 1, 3));

    expect($occurrences)->toHaveCount(3)
        ->and($occurrences[1]->start->toDateString())->toBe('2026-09-08')
        ->and($occurrences[2]->start->toDateString())->toBe('2026-09-09');
});

it('honours an interval greater than one', function () {
    $occurrences = $this->expander->expand($this->first, new RecurrenceRule(RecurrenceFrequency::Weekly, 2, 3));

    expect($occurrences[1]->start->toDateString())->toBe('2026-09-21')
        ->and($occurrences[2]->start->toDateString())->toBe('2026-10-05');
});
