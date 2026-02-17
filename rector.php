<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([
        __DIR__ . '/src',
        __DIR__ . '/examples',
        __DIR__ . '/sample',
        __DIR__ . '/tests',
    ]);

    // Define a clear cache directory
    $rectorConfig->cacheDirectory(__DIR__ . '/var/cache/rector');

    // Here we can define what rule sets we want to apply
    $rectorConfig->sets([
        LevelSetList::UP_TO_PHP_81,
        SetList::CODE_QUALITY,
        SetList::DEAD_CODE,
        SetList::PRIVATIZATION,
        SetList::TYPE_DECLARATION,
        SetList::EARLY_RETURN,
    ]);

    // Skip certain files or directories if needed
    $rectorConfig->skip([
        __DIR__ . '/vendor',
        __DIR__ . '/var',
        __DIR__ . '/tools',
    ]);

    // Import names (classes, functions) automatically
    $rectorConfig->importNames();
    $rectorConfig->importShortClasses();

    // Register file extensions
    $rectorConfig->fileExtensions(['php']);

    // Parallel processing - adjust number based on your CPU cores
    $rectorConfig->parallel();
};