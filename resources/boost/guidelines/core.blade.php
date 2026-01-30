## Laravel Automatic Migrations

This package provides automatic database migrations by defining schema directly in Eloquent models instead of separate migration files.

### Features

- **Model-based migrations**: Define your table schema directly inside model classes using a static `migration` method
- **Automatic comparison**: Uses Doctrine DBAL to compare model definitions with existing database schema
- **Seamless integration**: Works alongside traditional Laravel migrations, running traditional migrations first
- **Model generation**: Includes a `make:amodel` command to create models with migration methods

### Core Commands

- **make:amodel**: Create a model with migration method, factory, and optional soft deletes
- **migrate:auto**: Run automatic migrations based on model definitions
- **migrate:auto-discover**: Discover and list all models that support automatic migrations

### Usage Patterns

#### Basic Model with Migration

@verbatim
<code-snippet name="Model with migration method" lang="php">
class User extends Model
{
    public static function migration(Blueprint $table)
    {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->timestamps();
    }
}
</code-snippet>
@endverbatim

#### Creating Models with Command

@verbatim
<code-snippet name="Create auto-migration model" lang="bash">
php artisan make:amodel Post
</code-snippet>
@endverbatim

#### Running Migrations

@verbatim
<code-snippet name="Run automatic migrations" lang="bash">
php artisan migrate:auto
php artisan migrate:auto --fresh --seed
</code-snippet>
@endverbatim

#### Migration Order Control

@verbatim
<code-snippet name="Control migration order" lang="php">
class Country extends Model
{
    public $migrationOrder = 1; // Run first
    
    public static function migration(Blueprint $table)
    {
        $table->id();
        $table->string('code')->unique();
        $table->string('name');
        $table->timestamps();
    }
}

class City extends Model
{
    public $migrationOrder = 2; // Run after Country
    
    public static function migration(Blueprint $table)
    {
        $table->id();
        $table->string('name');
        $table->foreignId('country_id')->constrained();
        $table->timestamps();
    }
}
</code-snippet>
@endverbatim

### Key Points

- Models must extend `Illuminate\Database\Eloquent\Model`
- The `migration` method must be static and accept a `Blueprint` parameter
- Use `$migrationOrder` property to control table creation order for foreign key constraints
- Package supports soft deletes, factories, and model traits
- Automatically handles table creation and schema updates
- Can be used in production with the `--force` flag

### Options

- `--no-factory`: Skip factory creation when using `make:amodel`
- `--no-soft-delete`: Skip soft delete trait and column
- `--no-static-migration`: Skip migration method generation
- `--fresh`: Drop all tables before migrating
- `--seed`: Run database seeders after migration
- `--force`: Allow migrations in production environment