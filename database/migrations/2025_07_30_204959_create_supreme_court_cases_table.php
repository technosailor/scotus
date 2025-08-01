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
        Schema::create('supreme_court_cases', function (Blueprint $table) {
            $table->id();
            $table->string('oyez_id')->unique()->nullable();
            $table->string('case_name');
            $table->string('docket_number')->nullable();
            $table->date('decision_date')->index();
            $table->foreignId('term_id')->constrained()->cascadeOnDelete();
            $table->text('href')->nullable();
            $table->text('summary')->nullable();
            $table->json('facts')->nullable();
            $table->json('question')->nullable();
            $table->json('conclusion')->nullable();
            $table->decimal('sentiment_score', 3, 2)->nullable(); // -1.00 to 1.00
            $table->string('majority_opinion_author')->nullable();
            $table->json('concurring_justices')->nullable();
            $table->json('dissenting_justices')->nullable();
            $table->json('raw_data'); // Store original JSON
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supreme_court_cases');
    }
};
