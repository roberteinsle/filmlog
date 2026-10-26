<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Aktive Streaming-Abos (TMDB-Anbieter-ID), z. B. Netflix = 8. Name und Logo für die Anzeige.
     */
    public function up(): void
    {
        Schema::create('streaming_subscriptions', function (Blueprint $table) {
            $table->unsignedInteger('provider_id')->primary();
            $table->string('name');
            $table->string('logo_path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('streaming_subscriptions');
    }
};
