<?php

namespace Chareka\AutoMigrate\Commands;

use Chareka\AutoMigrate\Parsers\CliPrettier;
use Chareka\AutoMigrate\Parsers\ComponentParser;
use Faker\Generator;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Literal;
use Nette\PhpGenerator\Method;
use Nette\PhpGenerator\PhpNamespace;
use Nette\PhpGenerator\PsrPrinter;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;


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

    public function handle(): void
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

        if (!$this->option('force') && $this->filesystem->exists($this->modelParser->classPath())) {
            $this->writeError('<comment>Model exists:</comment> ' . $this->modelParser->relativeClassPath());
            $this->writeWarning('Use the <info>--force</info> to overwrite it.');

            return;
        }

        $this->deleteUserMigration();

        $this->makeModel();
        if (!$this->option('no-factory')) {
            $this->makeFactory();
        }
        if (!$this->option('no-static-migration')) {
            $this->makeMigration();
        }

        $this->writeInfo('<info>Model created:</info> ' . $this->modelParser->relativeClassPath());
        if (!$this->option('no-factory')) {
            $this->writeInfo('<info>Factory created:</info> ' . $this->factoryPath('relativeClassPath'));
        }
        if (!$this->option('no-static-migration')) {
            $this->writeInfo('<info>Migration created:</info> ' . $this->migrationPath('relativeClassPath'));
        }
    }

    /**
     * Deletes the default user migration if the class is 'User'.
     */
    private function deleteUserMigration(): void
    {
        if ($this->modelParser->className() !== 'User') {
            return;
        }

        $path = 'database/migrations/0001_01_01_000000_create_users_table.php';
        $file = base_path($path);

        if ($this->filesystem->exists($file)) {
            $this->filesystem->delete($file);
            $this->writeError('<info>Migration deleted:</info> ' . $path);
        }
    }

    /**
     * Generates and writes the combined model file.
     */
    private function makeModel(): void
    {
        $namespace = new PhpNamespace($this->modelParser->classNamespace());
        $namespace->addUse(Model::class);

        $class = $namespace
            ->addClass($this->modelParser->className())
            ->setExtends(Model::class);

        // Add HasFactory trait
        if (!$this->option('no-factory')) {
            $namespace->addUse('Illuminate\Database\Eloquent\Factories\HasFactory');
            $class->addTrait('Illuminate\Database\Eloquent\Factories\HasFactory');
        }

        // Add SoftDeletes trait
        if (!$this->option('no-soft-delete')) {
            $namespace->addUse('Illuminate\Database\Eloquent\SoftDeletes');
            $class->addTrait('Illuminate\Database\Eloquent\SoftDeletes');
        }

        // Add the $guarded property
        $class->addProperty('guarded', [])
            ->setProtected();

        // Add the migration method
        if (!$this->option('no-static-migration')) {
            $namespace->addUse(Blueprint::class);
            $namespace->addUse('Illuminate\Database\Schema\Blueprint');

            $class->addMethod('migration')
                ->setPublic()
                ->setStatic()
                ->setReturnType('void')
                ->addBody("
                    \$table->id();
                    \$table->string('name');
                    \$table->timestamps();" .
                    (!$this->option('no-soft-delete') ? "\n                    \$table->softDeletes();" : '') . "
                ")
                ->addComment("@param Blueprint \$table")
                ->addParameter('table')->setType(Blueprint::class);
        }

        // Add the factory definition method
        if (!$this->option('no-factory')) {
            $namespace->addUse(Generator::class);

            $class
                ->addMethod('definition')
                ->setPublic()
                ->addBody("
                    return [
                        'name' => \$faker->name(),
                        'created_at' => \$faker->dateTimeThisMonth(),
                    ];
                ")
                ->setReturnType('array')
                ->addComment("@param Generator \$faker")
                ->addParameter('faker')->setType(Generator::class);
        }

        $this->filesystem->ensureDirectoryExists(dirname($this->modelParser->classPath()));
        $this->filesystem->put(
            $this->modelParser->classPath(),
            "<?php\n\n" . (new PsrPrinter)->printNamespace($namespace)
        );
    }

    /**
     * Generates and writes the factory file.
     */
    private function makeFactory(): void
    {
        $namespace = new PhpNamespace($this->factoryParser->classNamespace());
        $namespace->addUse(Factory::class);
        $className = $this->modelParser->className()->toString();
        $namespace->addUse($this->modelParser->classNamespace() . '\\' . $className);

        $class = $namespace
            ->addClass($this->factoryParser->className())
            ->setExtends(Factory::class);

        $class
            ->addProperty('model', new Literal("$className::class"))
            ->setProtected();

        $method = new Method('definition');
        $method->setPublic();
        $method->setReturnType('array');
        $method->addBody('return app($this->model)->definition($this->faker);');

        $class->addMember($method);

        $this->filesystem->ensureDirectoryExists(dirname($this->factoryPath('classPath')));
        $this->filesystem->put(
            $this->factoryPath('classPath'),
            "<?php\n\n" . (new PsrPrinter)->printNamespace($namespace)
        );
    }

    /**
     * Generates and writes the migration file.
     */
    private function makeMigration(): void
    {
        $className = $this->modelParser->className();
        $qualifiedClassName = '\\' . $this->modelParser->classNamespace() . '\\' . $className;
        $tableName = Str::of($className)->headline()->slug('_')->plural()->lower();

        $namespace = new PhpNamespace($this->migrationParser->classNamespace());
        $namespace->addUse(Migration::class);
        $namespace->addUse(Schema::class);

        $class = (new ClassType(null, $namespace))
            ->setExtends('Migration');

        // Up method
        $upMethod = new Method('up');
        $upMethod->setPublic();
        $upMethod->addBody("Schema::create('{$tableName}', $qualifiedClassName::migration(...));");
        $class->addMember($upMethod);

        // Down method
        $downMethod = new Method('down');
        $downMethod->setPublic();
        $downMethod->addBody("Schema::dropIfExists('{$tableName}');");
        $class->addMember($downMethod);

        $this->filesystem->ensureDirectoryExists(dirname($this->migrationPath('classPath')));
        $this->filesystem->put(
            $this->migrationPath('classPath'),
            "<?php\n\n" .
            (new PsrPrinter)->printNamespace($namespace) .
            'return new class ' . (new PsrPrinter)->printClass($class) . ';'
        );
    }

    /**
     * Gets the correct factory path.
     */
    private function factoryPath($method): string
    {
        return Str::replaceFirst(
            'app/Database/Factories',
            'database/factories',
            $this->factoryParser->$method()
        );
    }

    /**
     * Gets the correct migration path.
     */
    private function migrationPath($method): string
    {
        $class = str($this->migrationParser->className());
        $table = $class->headline()->slug('_')->plural()->lower();

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
