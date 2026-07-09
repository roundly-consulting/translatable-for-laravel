<?php

declare(strict_types=1);

namespace RoundlyConsulting\Translatable\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use RoundlyConsulting\Translatable\TranslatableServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [TranslatableServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        if (pgsqlConfigured()) {
            $app['config']->set('database.connections.pgsql', [
                'driver' => 'pgsql',
                'host' => env('TRANSLATABLE_PGSQL_HOST', '127.0.0.1'),
                'port' => env('TRANSLATABLE_PGSQL_PORT', '5432'),
                'database' => env('TRANSLATABLE_PGSQL_DATABASE', 'translatable_test'),
                'username' => env('TRANSLATABLE_PGSQL_USERNAME', 'postgres'),
                'password' => env('TRANSLATABLE_PGSQL_PASSWORD', ''),
                'charset' => 'utf8',
                'prefix' => '',
                'search_path' => 'public',
                'sslmode' => 'prefer',
            ]);
        }
    }

    protected function createTopicsTable(): void
    {
        Schema::dropIfExists('topics');

        Schema::create('topics', function (Blueprint $table): void {
            $table->id();
            $table->jsonb('name');
            $table->jsonb('description')->nullable();
            $table->jsonb('slug')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    protected function createArticlesTable(): void
    {
        Schema::dropIfExists('articles');

        Schema::create('articles', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
}
