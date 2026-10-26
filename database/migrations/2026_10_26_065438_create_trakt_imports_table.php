<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ein hochgeladener Trakt-Export und sein Fortschritt. resolved merkt sich, welche Filme schon
     * einer TMDB-ID zugeordnet sind; so kann der Job in Etappen laufen und nach Abbrüchen weitermachen.
     */
    public function up(): void
    {
        Schema::create('trakt_imports', function (Blueprint $table) {
            $table->id();
            $table->string('original_name');
            $table->string('path');
            $table->string('status', 16)->default('pending');
            $table->unsignedInteger('total_movies')->default(0);
            $table->jsonb('resolved')->default('{}');
            $table->jsonb('stats')->default('{}');
            $table->jsonb('unresolved')->default('[]');
            $table->jsonb('ignored_files')->default('[]');
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trakt_imports');
    }
};
