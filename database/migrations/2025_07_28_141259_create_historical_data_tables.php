<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Main historical data table
        Schema::create('historical_records', function (Blueprint $table) {
            $table->id();
            $table->integer('year');
            $table->string('category', 100);
            $table->string('subcategory', 100)->nullable();
            $table->string('region', 100);
            $table->string('country', 100)->nullable();
            $table->json('data'); // Flexible JSON storage
            $table->decimal('primary_value', 20, 6)->nullable(); // For quick queries
            $table->string('unit', 50)->nullable();
            $table->text('notes')->nullable();
            $table->string('source', 255)->nullable();
            $table->timestamps();

            $table->index(['year', 'category']);
            $table->index(['year', 'region']);
            $table->index('primary_value');
        });

        // LLM analyses cache
        Schema::create('llm_analyses', function (Blueprint $table) {
            $table->id();
            $table->string('query_hash', 64)->unique();
            $table->text('query');
            $table->json('parameters');
            $table->longText('response');
            $table->string('model', 50);
            $table->integer('tokens_used')->nullable();
            $table->decimal('cost', 10, 6)->nullable();
            $table->timestamps();

            $table->index('created_at');
        });

        // Visualizations metadata
        Schema::create('visualizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 50); // line, bar, scatter, map, etc.
            $table->json('config'); // D3.js configuration
            $table->json('data_query'); // Query parameters
            $table->text('description')->nullable();
            $table->boolean('is_public')->default(false);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        // Data import logs
        Schema::create('import_logs', function (Blueprint $table) {
            $table->id();
            $table->string('filename');
            $table->integer('records_imported');
            $table->integer('records_failed')->default(0);
            $table->json('errors')->nullable();
            $table->string('status', 20);
            $table->foreignId('imported_by')->constrained('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('import_logs');
        Schema::dropIfExists('visualizations');
        Schema::dropIfExists('llm_analyses');
        Schema::dropIfExists('historical_records');
    }
};
