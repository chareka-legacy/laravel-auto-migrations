<?php

namespace Chareka\AutoMigrate\Commands;

use Chareka\AutoMigrate\Parsers\CliPrettier;
use Chareka\AutoMigrate\Traits\DiscoverModels;
use Illuminate\Console\Command;
use ReflectionException;

class MigrateAutoDiscoverCommand extends Command
{
    use DiscoverModels, CliPrettier;

    protected $signature = 'migrate:auto-discover';

    /**
     * @throws ReflectionException
     */
    public function handle(): void
    {
        $total = 0;

        $this->writeInfo('Fetching models...');

        $models = collect($this->discoverBaseModels());
        $total += $models->count();

        foreach ($models->sortBy('order') as $model) {
            $this->info(" - {$model['name']}");
        }

        $this->writeInfo('Fetching modular models...');

        $models = collect($this->discoverModularModels());
        $total += $models->count();

        foreach ($models->sortBy('order') as $model) {
            $this->info(" - {$model['name']}");
        }

        $this->writeInfo("Automatic discovery found {$total} models.");
    }
}
