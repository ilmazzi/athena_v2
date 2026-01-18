<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articoli', function (Blueprint $table) {
            if (!Schema::hasColumn('articoli', 'prezzo_fornitore')) {
                $table->decimal('prezzo_fornitore', 10, 2)
                    ->nullable()
                    ->after('prezzo_acquisto')
                    ->comment('Prezzo vendita concordato con fornitore (non costo)');
            }
        });
    }

    public function down(): void
    {
        Schema::table('articoli', function (Blueprint $table) {
            if (Schema::hasColumn('articoli', 'prezzo_fornitore')) {
                $table->dropColumn('prezzo_fornitore');
            }
        });
    }
};
