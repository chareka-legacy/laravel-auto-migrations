<?php

namespace Chareka\AutoMigrate\Providers;

use Chareka\AutoMigrate\Commands\MakeAModelCommand;
use Chareka\AutoMigrate\Commands\MigrateAutoCommand;
use Chareka\AutoMigrate\Commands\MigrateAutoDiscoverCommand;
use Illuminate\Support\ServiceProvider;

class AutoMigrateProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                MakeAModelCommand::class,
                MigrateAutoCommand::class,
                MigrateAutoDiscoverCommand::class,
            ]);
        }

        $this->publishes(
            [__DIR__ . '/../../config/auto-migrate.php' => config_path('auto-migrate.php')],
            ['auto-migrate', 'auto-migrate:config']
        );

        $this->publishes(
            [__DIR__ . '/../../resources/stubs' => resource_path('stubs/vendor/auto-migrate')],
            ['auto-migrate', 'auto-migrate:stubs']
        );
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/auto-migrate.php', 'auto-migrate');
    }
}
