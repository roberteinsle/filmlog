<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lists', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_watchlist')->default(false);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        // Genau eine Watchlist; sie existiert immer.
        DB::statement('CREATE UNIQUE INDEX lists_single_watchlist ON lists (is_watchlist) WHERE is_watchlist');

        DB::table('lists')->insert([
            'name' => 'Watchlist',
            'is_watchlist' => true,
            'sort' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('lists');
    }
};
