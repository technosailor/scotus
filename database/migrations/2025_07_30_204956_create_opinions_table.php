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
        Schema::create('opinions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('supreme_court_cases')->cascadeOnDelete();
            $table->foreignId('justice_id')->constrained()->cascadeOnDelete();
            $table->enum('opinion_type', ['majority', 'concurrence', 'dissent', 'plurality', 'none']);
            $table->enum('vote', ['majority', 'minority']);
            $table->text('opinion_text')->nullable();
            $table->decimal('sentiment_score', 3, 2)->nullable();
            $table->integer('ideology_score')->nullable();
            $table->integer('seniority')->nullable();
            $table->json('joining_justices')->nullable(); // For cases where justices join others
            $table->text('oyez_href')->nullable();
            $table->timestamps();
            
            $table->index(['case_id', 'opinion_type']);
            $table->index(['justice_id', 'opinion_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('opinions');
    }
};
