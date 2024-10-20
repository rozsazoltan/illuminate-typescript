<?php

namespace Rose\IlluminateTypeScript\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use Rose\IlluminateTypeScript\Foundation\IlluminateTypeScriptServiceProvider;

abstract class DatabaseTestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [IlluminateTypeScriptServiceProvider::class];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');

        $this->migrateDatabase();
    }

    public function migrateDatabase()
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->json('data')->nullable();
            $table->unsignedInteger('position');
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id');
            $table->foreignId('sub_category_id')->constrained('categories');
            $table->string('name');
            $table->decimal('price');
            $table->json('data')->nullable();
            $table->timestamps();
        });

        Schema::create('features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id');
            $table->string('body');
            $table->timestamps();
        });
    }
}
