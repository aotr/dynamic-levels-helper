<?php

declare(strict_types=1);

namespace Aotr\DynamicLevelHelper\Providers;

use Aotr\DynamicLevelHelper\Console\Commands\DynamicLevelsMakeCommand;
use Illuminate\Support\ServiceProvider;

final class PackageServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands(
                commands: [
                    DynamicLevelsMakeCommand::class,
                ],
            );
        }
    }
}
