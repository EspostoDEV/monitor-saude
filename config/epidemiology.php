<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Epidemiological Risk Thresholds
    |--------------------------------------------------------------------------
    |
    | These values define the incidence thresholds (per 100k inhabitants)
    | for each alert level.
    |
    */
    'thresholds' => [
        'yellow' => 100,
        'orange' => 300,
        'red' => 600,
    ],

    /*
    |--------------------------------------------------------------------------
    | Sanity Check Limits
    |--------------------------------------------------------------------------
    |
    | Limits to avoid statistical noise in cities with very low absolute cases.
    |
    */
    'sanity_check' => [
        'min_cases_for_stable' => 5,  // Below this, level is always 1 (Stable)
        'min_cases_for_critical' => 10, // Below this, level 4 is downgraded to 3
    ],
];
