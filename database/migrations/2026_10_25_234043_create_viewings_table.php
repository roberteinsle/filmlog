<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('viewings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tmdb_id')->constrained('movies', 'tmdb_id')->cascadeOnDelete();
            $table->timestampTz('watched_at');
            $table->unsignedTinyInteger('rating')->nullable();
            $table->string('note', 280)->nullable();
            $table->string('source', 16)->default('manual');
            $table->string('external_id')->nullable();
            $table->timestamps();

            $table->index(['tmdb_id', 'watched_at']);
            $table->unique(['source', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('viewings');
    }
};
