<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Streaming-Anbieter (TMDB-IDs, Abo/kostenlos/mit Werbung in DE) für den Abo-Filter in „Heute Abend“.
     * null = noch nicht von TMDB geholt; SyncMovie und der nächtliche Refresh füllen die Spalte.
     */
    public function up(): void
    {
        Schema::table('movies', function (Blueprint $table) {
            $table->jsonb('provider_ids')->nullable();
        });

        DB::statement('CREATE INDEX movies_provider_ids_gin ON movies USING gin (provider_ids)');
    }

    public function down(): void
    {
        Schema::table('movies', function (Blueprint $table) {
            $table->dropColumn('provider_ids');
        });
    }
};
