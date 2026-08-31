<?php

namespace App\Services\Reservations;

/**
 * Expands a recurrence rule into the concrete list of occurrences it represents.
 *
 * We materialise occurrences (one row per meeting) rather than storing only the
 * rule, so that the overlap check is a plain interval comparison against real rows.
 */
class RecurrenceExpander
{
    /**
     * @return list<TimeInterval>
     */
    public function expand(TimeInterval $first, RecurrenceRule $rule): array
    {
        $occurrences = [$first];

        $start = $first->start;
        $end = $first->end;

        for ($i = 1; $i < $rule->occurrences; $i++) {
            $start = $rule->frequency->advance($start, $rule->interval);
            $end = $rule->frequency->advance($end, $rule->interval);

            $occurrences[] = new TimeInterval($start, $end);
        }

        return $occurrences;
    }
}
