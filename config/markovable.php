<?php

use VinkiusLabs\Markovable\Analyzers\NavigationAnalyzer;
use VinkiusLabs\Markovable\Analyzers\TextAnalyzer;
use VinkiusLabs\Markovable\Storage\CacheStorage;
use VinkiusLabs\Markovable\Storage\DatabaseStorage;
use VinkiusLabs\Markovable\Storage\FileStorage;

return [
    'default_order' => 2,

    'generate_default_words' => 100,

    'cache' => [
        'enabled' => true,
        'ttl' => 3600,
        'driver' => 'redis',
    ],

    'storage' => 'cache',

    'storages' => [
        'cache' => CacheStorage::class,
        'database' => DatabaseStorage::class,
        'file' => FileStorage::class,
    ],

    'queue' => [
        'enabled' => false,
        'connection' => 'redis',
        'queue' => 'markovable',
    ],

    'auto_train' => [
        'enabled' => false,
        'models' => [],
        'field' => null,
    ],

    'analyzers' => [
        'text' => TextAnalyzer::class,
        'navigation' => NavigationAnalyzer::class,
    ],

    'anomaly' => [
        'persist' => true,
        'dispatch_events' => true,
        'default_threshold' => 0.05,
        'seasonality' => [
            'threshold' => 0.3,
        ],
    ],
];
