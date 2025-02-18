<?php

namespace Chareka\AutoMigrate\Commands;

use Chareka\AutoMigrate\Parsers\CliPrettier;
use Chareka\AutoMigrate\Parsers\ComponentParser;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;


class MakeAModelCommand extends Command
{
    use CliPrettier;

    protected $signature = 'make:amodel {class} {--force} 
                                        {--f|no-factory}
                                        {--s|no-soft-delete} 
                                        {--m|no-static-migration}';
    private $filesystem;

    /** @var ComponentParser */
    private $modelParser;

    /** @var ComponentParser */
    private $factoryParser;

    /** @var ComponentParser */
    private $migrationParser;

    public function handle()
    {
        $this->filesystem = new Filesystem;

        $this->modelParser = new ComponentParser(
            'App\\Models',
            $this->argument('class')
        );

        $this->factoryParser = new ComponentParser(
            'Database\\Factories',
            $this->argument('class') . 'Factory'
        );

        $this->migrationParser = new ComponentParser(
            'Database\\Migrations',
            $this->argument('class')
        );

        if ($this->filesystem->exists($this->modelParser->classPath()) && !$this->option('force')) {
            $this->writeError('<comment>Model exists:</comment> ' . $this->modelParser->relativeClassPath());
            $this->writeWarning('Use the <info>--force</info> to overwrite it.');

            return;
        }

        $this->deleteUserMigration();
        $this->makeStubs();

        $this->writeInfo('<info>Model created:</info> ' . $this->modelParser->relativeClassPath());
        $this->writeInfo('<info>Factory created:</info> ' . $this->factoryPath('relativeClassPath'));

        if (!$this->option('no-static-migration')){
            $this->writeInfo('<info>Migration created:</info> ' . $this->migrationPath('relativeClassPath'));
        }
    }

    private function deleteUserMigration()
    {
        if ($this->modelParser->className() != 'User') {
            return;
        }

        $path = 'database/migrations/0001_01_01_000000_create_users_table.php';
        $file = base_path($path);

        if ($this->filesystem->exists($file)) {
            $this->filesystem->delete($file);

            $this->writeError('<info>Migration deleted:</info> ' . $path);
        }
    }

    private function makeStubs()
    {
        $prefix = $this->modelParser->className() == 'User' ? 'User' : null;

        $stubs = [
            $this->modelParser->classPath() => "{$prefix}Model.php",
        ];

        if(!$this->option('no-factory')){
            $stubs[$this->factoryPath('classPath')] = "{$prefix}Factory.php";
        }

        if (!$this->option('no-static-migration')) {
            $stubs[$this->migrationPath('classPath')] = 'migration.php';
        }

        $replaces = [
            'DummyFactoryClass' => $this->factoryParser->className(),
            'DummyFactoryNamespace' => $this->factoryParser->classNamespace(),
            'DummyModelClass' => $this->modelParser->className(),
            'DummyModelNamespace' => $this->modelParser->classNamespace(),
            ', SoftDeletes;' => !$this->option('no-soft-delete') ? ', SoftDeletes;' : ';',
            '$table->softDeletes();' => !$this->option('no-soft-delete') ? '$table->softDeletes();' : ';',
            'dummy_table' => str($this->modelParser->className())->plural()->lower(),
        ];

        foreach ($stubs as $path => $stub) {
            $contents = Str::replace(
                array_keys($replaces),
                $replaces,
                $this->filesystem->get(config('auto-migrate.stub_path') . '/' . $stub)
            );

            $this->filesystem->ensureDirectoryExists(dirname($path));
            $this->filesystem->put($path, $contents);
        }
    }

    private function factoryPath($method): string
    {
        return Str::replaceFirst(
            'app/Database/Factories',
            'database/factories',
            $this->factoryParser->$method()
        );
    }

    private function migrationPath($method): string
    {
        $class = str($this->migrationParser->className());
        $table = $class->plural()->slug('_')->lower();

        return str($this->migrationParser->$method())
            ->replaceFirst(
                'app/Database/Migrations',
                'database/migrations'
            )
            ->replaceLast(
                "$class.php",
                now()->format('Y_m_d_His') . "_create_{$table}_table.php"
            );
    }
}
