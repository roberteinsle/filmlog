<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('list_items', function (Blueprint $table) {
            $table->foreignId('list_id')->constrained('lists')->cascadeOnDelete();
            $table->foreignId('tmdb_id')->constrained('movies', 'tmdb_id')->cascadeOnDelete();
            $table->unsignedInteger('rank')->default(0);
            $table->timestamp('added_at')->useCurrent();

            $table->primary(['list_id', 'tmdb_id']);
            $table->index(['list_id', 'rank']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('list_items');
    }
};
