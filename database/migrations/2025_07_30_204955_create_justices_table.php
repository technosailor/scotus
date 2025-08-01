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
        Schema::create('justices', function (Blueprint $table) {
            $table->id();
            $table->string('oyez_id')->unique()->index();
            $table->string('identifier')->unique()->index();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('name');
            $table->text('thumbnail_url')->nullable();
            $table->integer('length_of_service')->nullable();
            $table->text('href')->nullable();
            $table->integer('view_count')->default(0);
            $table->json('roles');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('justices');
    }
};
