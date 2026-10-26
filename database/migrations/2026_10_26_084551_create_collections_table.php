<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * TMDB-Filmreihen (für „Lücken“ im Rückblick). parts enthält alle Teile mit Erscheinungsdatum,
     * damit auch noch nie geöffnete Teile ohne weiteren TMDB-Abruf angezeigt werden können.
     */
    public function up(): void
    {
        Schema::create('collections', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->string('name');
            $table->jsonb('parts')->default('[]');
            $table->timestamp('cached_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collections');
    }
};
