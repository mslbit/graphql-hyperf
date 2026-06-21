<?php

declare(strict_types=1);

use function Hyperf\Support\env;

return [
    'debug' => env('GRAPHQL_DEBUG', false),

    'sources' => [
        // App\GraphQL\PageResolver::class,
    ],

    'types' => [
        // App\Domain\Entity\Page::class,
    ],


    'scan_paths' => [
         BASE_PATH . '/app'
    ],

    'security' => [
        'max_query_depth' => 15,
        'max_query_complexity' => 1000,
    ],

    'rate_limit' => [
        'max_attempts' => 100,
        'decay_seconds' => 60,
    ],

    'cache' => [
        'enabled' => false,
        'prefix' => 'graphql:',
    ],
];
