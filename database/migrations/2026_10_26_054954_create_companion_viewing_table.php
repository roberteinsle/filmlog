<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companion_viewing', function (Blueprint $table) {
            $table->foreignId('viewing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('companion_id')->constrained()->cascadeOnDelete();

            $table->primary(['viewing_id', 'companion_id']);
            $table->index('companion_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companion_viewing');
    }
};
