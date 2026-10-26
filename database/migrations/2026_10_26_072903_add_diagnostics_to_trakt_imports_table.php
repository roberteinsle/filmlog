<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Für den Bericht: welche Dateien gelesen wurden und was das Export-Werkzeug selbst als Fehler meldet.
     */
    public function up(): void
    {
        Schema::table('trakt_imports', function (Blueprint $table) {
            $table->jsonb('read_files')->default('[]');
            $table->jsonb('export_errors')->default('[]');
        });
    }

    public function down(): void
    {
        Schema::table('trakt_imports', function (Blueprint $table) {
            $table->dropColumn(['read_files', 'export_errors']);
        });
    }
};
