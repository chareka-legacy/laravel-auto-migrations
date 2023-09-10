<?php

namespace Chareka\LaravelAuto\Migrations\Providers;

use Chareka\LaravelAuto\Migrations\Commands\MakeAModelCommand;
use Chareka\LaravelAuto\Migrations\Commands\MigrateAutoCommand;
use Chareka\LaravelAuto\Migrations\Commands\MigrateAutoDiscoverCommand;
use Illuminate\Support\ServiceProvider;

class LaravelAutomaticMigrationsProvider extends ServiceProvider
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
            [__DIR__ . '/../../config/auto-migrate.php' => config_path('auto-migrations.php')],
            ['auto-migrations', 'auto-migrations:config']
        );

        $this->publishes(
            [__DIR__ . '/../../resources/stubs' => resource_path('stubs/vendor/laravel-auto-migrations')],
            ['auto-migrations', 'auto-migrations:stubs']
        );
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/auto-migrate.php', 'auto-migrations');
    }
}
