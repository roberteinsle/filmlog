<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Plex-Konten (inkl. verwalteter Plex-Home-Profile), von denen Webhooks kamen.
     * counts_viewings legt fest, ob ihre Scrobbles als Sichtung ins Tagebuch gehen.
     */
    public function up(): void
    {
        Schema::create('plex_accounts', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->string('title');
            $table->boolean('counts_viewings')->default(false);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plex_accounts');
    }
};
