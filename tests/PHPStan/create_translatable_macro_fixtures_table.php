<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ColumnDefinition;
use Illuminate\Support\Facades\Schema;

/**
 * Static-analysis fixture (never executed): a host migration calling the schema macro, analysed
 * by `composer analyse` so the macro stays visible to Larastan without a booted provider.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('translatable_macro_fixtures', function (Blueprint $table): void {
            $table->id();
            $table->translatable('name');
            $table->translatable(name: 'summary')->nullable();
            $this->column($table->translatable('body'));
        });
    }

    private function column(ColumnDefinition $column): void
    {
        $column->comment('locale map');
    }
};
