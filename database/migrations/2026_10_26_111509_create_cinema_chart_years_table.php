<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ein Kinocharts-Jahr von insidekino.de: wann geladen, Status und Fehlermeldung.
     */
    public function up(): void
    {
        Schema::create('cinema_chart_years', function (Blueprint $table) {
            $table->unsignedSmallInteger('year')->primary();
            $table->string('status', 16)->default('pending');
            $table->text('error')->nullable();
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cinema_chart_years');
    }
};
