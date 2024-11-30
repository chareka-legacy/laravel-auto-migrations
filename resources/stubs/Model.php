<?php

namespace DummyModelNamespace;

use Faker\Generator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;

class DummyModelClass extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    public function migration(Blueprint $table): void
    {
        $table->id();
        $table->string('name');
        $table->timestamps();
        $table->softDeletes();
    }

    public function definition(Generator $faker): array
    {
        return [
            'name' => $faker->name(),
            'created_at' => $faker->dateTimeThisMonth(),
        ];
    }
}
