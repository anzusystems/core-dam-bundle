<?php

use Rector\Config\RectorConfig;
use Rector\Php83\Rector\ClassConst\AddTypeToConstRector;
use Rector\ValueObject\PhpVersion;

return RectorConfig::configure()
    ->withRules([
        AddTypeToConstRector::class
    ])
    ->withPhpVersion(PhpVersion::PHP_83)
    ->withPaths([
        __DIR__ . '/src',
    ]);