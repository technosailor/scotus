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
        Schema::table('supreme_court_cases', function (Blueprint $table) {
            $table->string('unique_hash', 64)->nullable()->after('id');
        });
        
        // Populate unique_hash for existing records in chunks
        \App\Models\SupremeCourtCase::chunk(100, function ($cases) {
            foreach ($cases as $case) {
                $uniqueHash = hash('sha256', $case->case_name . $case->decision_date->format('Y-m-d'));
                $case->update(['unique_hash' => $uniqueHash]);
            }
        });
        
        // Now make it unique
        Schema::table('supreme_court_cases', function (Blueprint $table) {
            $table->unique('unique_hash');
            $table->index('unique_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('supreme_court_cases', function (Blueprint $table) {
            $table->dropIndex(['unique_hash']);
            $table->dropColumn('unique_hash');
        });
    }
};
