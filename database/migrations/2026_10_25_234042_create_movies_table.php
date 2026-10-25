<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lokaler Cache der TMDB-Daten, die Filter und Statistik brauchen.
     * Inhalt, Cast und Streaming-Anbieter kommen weiterhin live von TMDB.
     */
    public function up(): void
    {
        Schema::create('movies', function (Blueprint $table) {
            $table->unsignedBigInteger('tmdb_id')->primary();
            $table->string('title');
            $table->string('original_title')->nullable();
            $table->unsignedSmallInteger('year')->nullable()->index();
            $table->unsignedSmallInteger('runtime')->nullable()->index();
            $table->unsignedTinyInteger('fsk')->nullable();
            $table->string('poster_path')->nullable();
            $table->decimal('vote_average', 3, 1)->nullable();
            $table->unsignedInteger('vote_count')->default(0);
            $table->jsonb('genre_ids')->default('[]');
            $table->jsonb('directors')->default('[]');
            $table->unsignedBigInteger('collection_id')->nullable()->index();
            $table->string('imdb_id', 16)->nullable()->index();
            $table->boolean('on_plex')->default(false);
            $table->string('plex_rating_key')->nullable();
            $table->timestamp('cached_at')->nullable();
            $table->timestamps();

            $table->index(['vote_average', 'vote_count']);
        });

        DB::statement('CREATE INDEX movies_genre_ids_gin ON movies USING gin (genre_ids)');
    }

    public function down(): void
    {
        Schema::dropIfExists('movies');
    }
};
