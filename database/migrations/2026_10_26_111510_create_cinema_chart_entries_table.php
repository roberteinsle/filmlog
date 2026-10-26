<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Eine Zeile der Kinocharts. tmdb_id ist null, solange sie nicht zugeordnet ist;
     * resolved_at gesetzt + tmdb_id null = bei TMDB nicht gefunden.
     */
    public function up(): void
    {
        Schema::create('cinema_chart_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->unsignedSmallInteger('rank');
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->unsignedInteger('visitors')->nullable();
            $table->string('imdb_id', 16)->nullable();
            $table->foreignId('tmdb_id')->nullable()->constrained('movies', 'tmdb_id')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->foreign('year')->references('year')->on('cinema_chart_years')->cascadeOnDelete();
            $table->unique(['year', 'rank']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cinema_chart_entries');
    }
};
