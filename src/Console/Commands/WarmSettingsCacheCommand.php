<?php

namespace Aotr\DynamicLevelHelper\Console\Commands;

use Aotr\DynamicLevelHelper\Services\SettingsService;
use Illuminate\Console\Command;

class WarmSettingsCacheCommand extends Command
{
    protected $signature = 'settings:warm';
    protected $description = 'Warm the settings cache for optimal performance';

    public function handle()
    {
        try {
            app(SettingsService::class)->warm();
            $this->info('Settings cache has been warmed.');
            return 0;
        } catch (\Exception $e) {
            $this->error('Failed to warm settings cache: ' . $e->getMessage());
            return 1;
        }
    }
}
