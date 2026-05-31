<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use RectorLaravel\Set\LaravelSetList;
use Rector\Set\ValueObject\LevelSetList;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([
        __DIR__.'/app',
    ]);

    $rectorConfig->sets([
        LevelSetList::UP_TO_PHP_84,
        LaravelSetList::LARAVEL_130,
    ]);
};
