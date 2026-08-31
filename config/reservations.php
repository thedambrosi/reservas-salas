<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Maximum occurrences per recurring reservation
    |--------------------------------------------------------------------------
    |
    | A recurring reservation is materialised into one row per occurrence, so the
    | series must be bounded. This caps how many occurrences a single request may
    | create (e.g. 52 = one year of weekly meetings).
    |
    */
    'max_occurrences' => (int) env('RESERVATIONS_MAX_OCCURRENCES', 52),

    /*
    |--------------------------------------------------------------------------
    | Maximum duration of a single reservation (minutes)
    |--------------------------------------------------------------------------
    */
    'max_duration_minutes' => (int) env('RESERVATIONS_MAX_DURATION_MINUTES', 12 * 60),

    /*
    |--------------------------------------------------------------------------
    | Minimum lead time before a reservation may start (minutes)
    |--------------------------------------------------------------------------
    |
    | Reservations must start at least this many minutes in the future. Set to 0
    | to allow booking a room for right now.
    |
    */
    'min_lead_minutes' => (int) env('RESERVATIONS_MIN_LEAD_MINUTES', 0),

];
