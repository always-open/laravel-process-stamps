<?php

return [

    'table' => 'process_stamps',

    'columns' => [
        'primary_key' => 'id',
        'created'     => 'process_created_id',
        'updated'     => 'process_updated_id',
    ],

    /*
     * Keeps each process's stamp id in a cache store, so only its first stamp reads the table.
     * A null store is the application's default cache store.
     */
    'cache' => [
        'enabled' => env('PROCESS_STAMP_CACHE_ENABLED', true),
        'store'   => env('PROCESS_STAMP_CACHE_DRIVER'),
    ],

    'resolve_recursive' => env('PROCESS_STAMP_RESOLVE_RECURSIVE', true),
];
