<?php

use App\Services\Reservations\TimeInterval;
use Carbon\CarbonImmutable;

function interval(string $start, string $end): TimeInterval
{
    return new TimeInterval(CarbonImmutable::parse($start), CarbonImmutable::parse($end));
}

dataset('overlap cases', [
    'identical intervals' => ['10:00', '11:00', '10:00', '11:00', true],
    'requested fully inside existing' => ['09:00', '12:00', '10:00', '11:00', true],
    'existing fully inside requested' => ['10:00', '11:00', '09:00', '12:00', true],
    'partial overlap on the left' => ['10:00', '11:00', '09:30', '10:30', true],
    'partial overlap on the right' => ['10:00', '11:00', '10:30', '11:30', true],
    'back to back before' => ['10:00', '11:00', '09:00', '10:00', false],
    'back to back after' => ['10:00', '11:00', '11:00', '12:00', false],
    'completely separate' => ['10:00', '11:00', '14:00', '15:00', false],
]);

it('detects overlap with half-open interval semantics', function (string $aStart, string $aEnd, string $bStart, string $bEnd, bool $expected) {
    $a = interval("2026-09-07T{$aStart}:00+00:00", "2026-09-07T{$aEnd}:00+00:00");
    $b = interval("2026-09-07T{$bStart}:00+00:00", "2026-09-07T{$bEnd}:00+00:00");

    expect($a->overlaps($b))->toBe($expected)
        ->and($b->overlaps($a))->toBe($expected); // overlap is symmetric
})->with('overlap cases');
