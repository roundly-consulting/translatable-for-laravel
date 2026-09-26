<?php

declare(strict_types=1);

namespace RoundlyConsulting\Translatable\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use RoundlyConsulting\Sluggable\SluggableServiceProvider;
use RoundlyConsulting\Testing\PackageTestCase;
use RoundlyConsulting\Translatable\TranslatableServiceProvider;

abstract class TestCase extends PackageTestCase
{
    /**
     * Every provider translatable hard-requires, in registration order. A host
     * auto-discovers these; the suite must list them or the test environment is a fiction.
     *
     * @return list<class-string<ServiceProvider>>
     */
    protected function packageProviders(): array
    {
        return [SluggableServiceProvider::class, TranslatableServiceProvider::class];
    }

    /**
     * Translatable ships NO migrations — it adds `jsonb` columns to the HOST's tables via
     * the Blueprint macros, so there is nothing to load here. The fixture tables below are
     * built per-test by the cases that need them, on whatever connection is under test.
     *
     * @return list<class-string<ServiceProvider>|string>
     */
    protected function migrationSources(): array
    {
        return [];
    }

    /**
     * The `topics` fixture: three translatable `jsonb` columns. Created on the DEFAULT
     * connection, which `DriverMatrix` points at whatever `TESTING_DB_DRIVER` names — so
     * this same helper builds the table on sqlite, postgres or mysql without a branch.
     */
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
}
