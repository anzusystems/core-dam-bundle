<?php

use Rector\Config\RectorConfig;
use Rector\Php83\Rector\ClassConst\AddTypeToConstRector;

return RectorConfig::configure()
    ->withRules([
        AddTypeToConstRector::class
    ])
    ->withPaths([
        __DIR__ . '/src',
    ]);