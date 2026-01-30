---
name: auto-migrate-development
description: Work with Laravel Automatic Migrations, including model creation, schema management, and migration execution.
---

# Laravel Automatic Migrations Development

## When to use this skill
Use this skill when working with Laravel Automatic Migrations to create models with built-in schema definitions, run automatic migrations, or manage database schemas through model-based migrations.

## Core Concepts

Laravel Automatic Migrations allows you to define database schema directly in your Eloquent models using a static `migration` method, eliminating the need for separate migration files for most use cases.

## Available Commands

### make:amodel
Create a new Eloquent model with automatic migration support.

**Usage:**
```bash
php artisan make:amodel {model_name} {options}
```

**Options:**
- `--force`: Overwrite existing model
- `--no-factory`: Skip factory creation
- `--no-soft-delete`: Skip soft delete trait and column
- `--no-static-migration`: Skip migration method generation

**Example:**
```bash
php artisan make:amodel BlogPost
```

### migrate:auto
Execute automatic migrations based on model definitions.

**Usage:**
```bash
php artisan migrate:auto {options}
```

**Options:**
- `--fresh`: Wipe database before migrating
- `--seed`: Run database seeders after migration
- `--force`: Run migrations in production environment
- `--info`: Display additional information

**Example:**
```bash
php artisan migrate:auto --fresh --seed
```

### migrate:auto-discover
Discover and list all models that support automatic migrations.

**Usage:**
```bash
php artisan migrate:auto-discover
```

## Model Structure

### Basic Model with Migration

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;

class Product extends Model
{
    public static function migration(Blueprint $table)
    {
        $table->id();
        $table->string('name');
        $table->text('description')->nullable();
        $table->decimal('price', 8, 2);
        $table->boolean('active')->default(true);
        $table->timestamps();
    }
}
```

### Model with Migration Order Control

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Category extends Model
{
    use HasFactory, SoftDeletes;
    
    public $migrationOrder = 1; // Run first
    
    public static function migration(Blueprint $table)
    {
        $table->id();
        $table->string('name');
        $table->string('slug')->unique();
        $table->text('description')->nullable();
        $table->timestamps();
        $table->softDeletes();
    }
}
```

### Model with Factory Definition

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Schema\Blueprint;
use Faker\Generator as Faker;

class Post extends Model
{
    use HasFactory;
    
    public static function migration(Blueprint $table)
    {
        $table->id();
        $table->string('title');
        $table->string('slug')->unique();
        $table->text('content');
        $table->foreignId('user_id')->constrained();
        $table->foreignId('category_id')->constrained();
        $table->timestamps();
    }
    
    public function definition(Faker $faker): array
    {
        return [
            'title' => $faker->sentence,
            'slug' => $faker->slug,
            'content' => $faker->paragraphs(3, true),
            'user_id' => User::factory(),
            'category_id' => Category::factory(),
            'created_at' => $faker->dateTimeThisMonth(),
        ];
    }
}
```

## Migration Order Control

Use the `$migrationOrder` property to control the order of table creation, especially important for foreign key constraints:

```php
class User extends Model
{
    public $migrationOrder = 1; // First
    
    public static function migration(Blueprint $table)
    {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->timestamps();
    }
}

class Post extends Model
{
    public $migrationOrder = 2; // Second (after User)
    
    public static function migration(Blueprint $table)
    {
        $table->id();
        $table->string('title');
        $table->foreignId('user_id')->constrained(); // References users table
        $table->timestamps();
    }
}
```

## Best Practices

1. **Use descriptive table names**: The package automatically converts model names to table names (e.g., `BlogPost` becomes `blog_posts`)
2. **Control migration order**: Set `$migrationOrder` for models with foreign key relationships
3. **Include timestamps**: Always include `timestamps()` in your migration methods
4. **Use nullable() wisely**: Mark optional columns as nullable to avoid errors during migrations
5. **Test migrations**: Use `migrate:auto-discover` to verify that your models are being detected
6. **Backup in production**: Always backup production databases before running migrations

## Working with Existing Migrations

The package works seamlessly with traditional Laravel migrations:
- Traditional migrations run first
- Automatic migrations run after traditional ones
- You can mix both approaches in the same project

## Common Patterns

### Soft Deletes
```php
public static function migration(Blueprint $table)
{
    $table->id();
    $table->string('name');
    $table->timestamps();
    $table->softDeletes(); // Add this for soft delete support
}
```

### Foreign Keys
```php
public static function migration(Blueprint $table)
{
    $table->id();
    $table->string('title');
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->timestamps();
}
```

### Indexes and Constraints
```php
public static function migration(Blueprint $table)
{
    $table->id();
    $table->string('email')->unique();
    $table->string('name');
    $table->index('name'); // Additional index
    $table->timestamps();
}
```

## Troubleshooting

### Common Issues

1. **Foreign key constraint errors**: Ensure proper migration order using `$migrationOrder`
2. **Model not discovered**: Verify your models extend the correct base Model class and are in the correct namespace
3. **Schema changes not applying**: Check that your migration method signature matches the expected format

### Debugging

Use `migrate:auto-discover` to list all discoverable models and verify your models are being detected.

Use verbose output with migrations for detailed information:
```bash
php artisan migrate:auto --verbose
```