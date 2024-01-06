<?php

namespace Chareka\AutoMigrate\Commands;

use Chareka\AutoMigrate\Traits\DiscoverModels;
use Illuminate\Console\Command;
use ReflectionException;

class MigrateAutoDiscoverCommand extends Command
{
    use DiscoverModels;

    protected $signature = 'migrate:auto-discover';

    /**
     * @throws ReflectionException
     */
    public function handle(): void
    {
        $total = 0;

        $this->info('Fetching models...');

        $models = collect($this->discoverBaseModels());
        $total += $models->count();

        foreach ($models->sortBy('order') as $model) {
            $this->info(" - {$model['name']}");
        }

        $this->info('Fetching modular models...');

        $models = collect($this->discoverModularModels());
        $total += $models->count();

        foreach ($models->sortBy('order') as $model) {
            $this->info(" - {$model['name']}");
        }

        $this->info("Automatic discovery found {$total} models.");
    }
}
