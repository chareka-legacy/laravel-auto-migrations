<?php

namespace Chareka\AutoMigrate\Commands;

use Chareka\AutoMigrate\Parsers\CliPrettier;
use Chareka\AutoMigrate\Traits\DiscoverModels;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\TableDiff;
use Illuminate\Console\Command;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Database\Schema\Blueprint;

class MigrateAutoCommand extends Command
{
    use DiscoverModels, CliPrettier;

    protected $signature = 'migrate:auto {--R|fresh} {--S|seed} {--F|force} {--I|info}';

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

        if ($this->option('verbose')) {
            $this->writeInfo("Auto-migrate for " . class_basename($model) . " ($modelTable)");
        }

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

                $this->writeWarning(' * Table updated: ' . $modelTable);

                if ($this->option('verbose')) {
                    $this->printColumnChanges($tableDiff);
                }
            }

            Schema::drop($tempTable);
        } else {
            Schema::rename($tempTable, $modelTable);

            $this->writeWarning(' * Table created: ' . $modelTable);
        }
    }

    /**
     * Print the columns added, removed, renamed and modified by a table diff.
     */
    private function printColumnChanges(TableDiff $diff): void
    {
        foreach ($diff->getAddedColumns() as $column) {
            $this->line('   <fg=green>+ added</>    ' . $column->getName() . ' (' . $this->describeColumn($column) . ')');
        }

        foreach ($diff->getDroppedColumns() as $column) {
            $this->line('   <fg=red>- removed</>  ' . $column->getName());
        }

        foreach ($diff->getRenamedColumns() as $oldName => $column) {
            $this->line('   <fg=cyan>~ renamed</>  ' . $oldName . ' -> ' . $column->getName());
        }

        foreach ($this->getModifiedColumns($diff) as $columnDiff) {
            $old = $columnDiff->getOldColumn();
            $new = $columnDiff->getNewColumn();

            $this->line(
                '   <fg=yellow>~ modified</> ' . $new->getName()
                . ' (' . $this->describeColumnChanges($old, $new) . ')'
            );
        }
    }

    /**
     * DBAL renamed getChangedColumns() to getModifiedColumns() in 3.5.
     */
    private function getModifiedColumns(TableDiff $diff): array
    {
        return method_exists($diff, 'getModifiedColumns')
            ? $diff->getModifiedColumns()
            : $diff->getChangedColumns();
    }

    /**
     * e.g. "string, length 255, nullable"
     */
    private function describeColumn(Column $column): string
    {
        $parts = [strtolower(class_basename($column->getType()::class))];

        if ($column->getLength() !== null) {
            $parts[] = 'length ' . $column->getLength();
        }

        $parts[] = $column->getNotnull() ? 'not null' : 'nullable';

        if ($column->getDefault() !== null) {
            $parts[] = 'default ' . var_export($column->getDefault(), true);
        }

        return implode(', ', $parts);
    }

    /**
     * List only the properties that differ, e.g. "length: 100 -> 255, nullable: no -> yes".
     */
    private function describeColumnChanges(Column $old, Column $new): string
    {
        $changes = [];

        $oldType = strtolower(class_basename($old->getType()::class));
        $newType = strtolower(class_basename($new->getType()::class));

        if ($oldType !== $newType) {
            $changes[] = "type: $oldType -> $newType";
        }

        if ($old->getLength() !== $new->getLength()) {
            $changes[] = 'length: ' . ($old->getLength() ?? 'none') . ' -> ' . ($new->getLength() ?? 'none');
        }

        if ($old->getPrecision() !== $new->getPrecision() || $old->getScale() !== $new->getScale()) {
            $changes[] = "precision/scale: {$old->getPrecision()},{$old->getScale()} -> {$new->getPrecision()},{$new->getScale()}";
        }

        if ($old->getNotnull() !== $new->getNotnull()) {
            $changes[] = 'nullable: ' . ($old->getNotnull() ? 'no' : 'yes') . ' -> ' . ($new->getNotnull() ? 'no' : 'yes');
        }

        if ($old->getDefault() !== $new->getDefault()) {
            $changes[] = 'default: ' . var_export($old->getDefault(), true) . ' -> ' . var_export($new->getDefault(), true);
        }

        if ($old->getUnsigned() !== $new->getUnsigned()) {
            $changes[] = 'unsigned: ' . ($old->getUnsigned() ? 'yes' : 'no') . ' -> ' . ($new->getUnsigned() ? 'yes' : 'no');
        }

        if ($old->getAutoincrement() !== $new->getAutoincrement()) {
            $changes[] = 'autoincrement: ' . ($old->getAutoincrement() ? 'yes' : 'no') . ' -> ' . ($new->getAutoincrement() ? 'yes' : 'no');
        }

        return $changes ? implode(', ', $changes) : 'definition changed';
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
        try {
            // support for laravel versions using doctrine/dbal
            return \DB::getDoctrineConnection();
        } catch (\BadMethodCallException $e) {
            $connectionSettings = $modelConnection->getConfig();

            // Create a connection to the database
            return DriverManager::getConnection([
                // MySQL support
                'dbname' => $connectionSettings['database'],
                'user' => $connectionSettings['username'] ?? null,
                'password' => $connectionSettings['password'] ?? null,
                'host' => $connectionSettings['host'] ?? null,
                // SQLite support
                'path' => $connectionSettings['database'] ?? null,
                // Generic params
                'driver' => 'pdo_' . $connectionSettings['driver'],
            ]);
        }
    }
}