<?php

namespace Chareka\AutoMigrate\Commands;

use Chareka\AutoMigrate\Parsers\CliPrettier;
use Chareka\AutoMigrate\Traits\DiscoverModels;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Database\Schema\Blueprint;

class MigrateAutoCommand extends Command
{
    use DiscoverModels, CliPrettier;

    protected $signature = 'migrate:auto {--f|--fresh} {--s|--seed} {--force}';

    /**
     * @throws \ReflectionException
     * @throws Exception
     */
    public function handle(): void
    {
        if (app()->environment('production') && !$this->option('force')) {
            $this->writeWarning('Use the [--force] to migrate in production.');

            return;
        }

        $this->handleTraditionalMigrations();
        $this->handleAutomaticMigrations();
        $this->seed();

        $this->writeInfo('Automatic migration completed successfully.');
    }
    private function handleTraditionalMigrations(): void
    {
        $command = 'migrate';

        if ($this->option('fresh')) {
            $command .= ':fresh';
        }

        if ($this->option('force')) {
            $command .= ' --force';
        }

        Artisan::call($command, [], $this->getOutput());
    }

    /**
     * @throws \ReflectionException
     * @throws Exception
     */
    private function handleAutomaticMigrations(): void
    {
        foreach ($this->discoverModels() as $model) {
            $this->migrate($model['object']);
        }
    }

    /**
     * @throws Exception
     */
    private function migrate(Model $model): void
    {
        $modelTable = $model->getTable();
        $tempTable = 'temp_' . $modelTable;

        $this->writeInfo("Running for $model ($modelTable)");

        Schema::dropIfExists($tempTable);
        Schema::create($tempTable, static function (Blueprint $table) use ($model) {
            $model->migration($table);
        });

        if (Schema::hasTable($modelTable)) {
            $connection = $this->getDoctrineConnection($model->getConnection());
            $schemaManager = $connection->createSchemaManager();
            $tableDiff = $schemaManager->createComparator()->compareTables(
                $schemaManager->introspectTable($modelTable),
                $schemaManager->introspectTable($tempTable)
            );

            if (!$tableDiff->isEmpty()) {
                $schemaManager->alterTable($tableDiff);

                $this->writeInfo(' - Table updated: ' . $modelTable);
            }

            Schema::drop($tempTable);
        } else {
            Schema::rename($tempTable, $modelTable);

            $this->writeInfo(' - Table created: ' . $modelTable);
        }
    }

    private function seed(): void
    {
        if (!$this->option('seed')) {
            return;
        }

        $command = 'db:seed';

        if ($this->option('force')) {
            $command .= ' --force';
        }

        Artisan::call($command, [], $this->getOutput());
    }

    /**
     * @param Connection $modelConnection
     * @return \Doctrine\DBAL\Connection
     * @throws Exception
     */
    public function getDoctrineConnection(Connection $modelConnection): \Doctrine\DBAL\Connection
    {
        $connectionSettings = $modelConnection->getConfig();

        // Create a connection to the database
        return DriverManager::getConnection([
            'dbname' => $connectionSettings['database'],
            'user' => $connectionSettings['username'],
            'password' => $connectionSettings['password'],
            'host' => $connectionSettings['host'],
            'driver' => 'pdo_' . $connectionSettings['driver'],
        ]);
    }
}
