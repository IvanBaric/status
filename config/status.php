<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Model overrides
    |--------------------------------------------------------------------------
    |
    | Extend the package models in your application if you need extra traits,
    | relations or custom behavior, then point the config entries below to
    | those subclasses. Package internals always resolve models through here.
    |
    */
    'models' => [
        'status' => IvanBaric\Status\Models\Status::class,
        'status_history' => IvanBaric\Status\Models\StatusHistory::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Status history
    |--------------------------------------------------------------------------
    |
    | Disable this if you only need the current status and do not want the
    | package to record transition history rows on setStatus() / clearStatus().
    |
    */
    'history' => [
        'enabled' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache TTL
    |--------------------------------------------------------------------------
    |
    | Active status lists, key lists and keyed lookups are cached per status
    | type for this many seconds.
    |
    */
    'cache_ttl' => 3600,
];
