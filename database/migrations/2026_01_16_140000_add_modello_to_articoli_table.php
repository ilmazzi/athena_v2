<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articoli', function (Blueprint $table) {
            if (!Schema::hasColumn('articoli', 'modello')) {
                $table->string('modello', 120)
                    ->nullable()
                    ->after('numero_seriale')
                    ->comment('Modello articolo per listini fornitori');
            }
        });
    }

    public function down(): void
    {
        Schema::table('articoli', function (Blueprint $table) {
            if (Schema::hasColumn('articoli', 'modello')) {
                $table->dropColumn('modello');
            }
        });
    }
};
