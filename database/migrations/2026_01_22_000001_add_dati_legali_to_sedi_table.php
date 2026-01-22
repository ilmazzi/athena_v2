<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sedi', function (Blueprint $table) {
            $table->string('sede_legale', 255)->nullable()->after('email');
            $table->string('sede_legale_indirizzo', 255)->nullable()->after('sede_legale');
            $table->string('sede_legale_citta', 100)->nullable()->after('sede_legale_indirizzo');
            $table->string('sede_legale_provincia', 2)->nullable()->after('sede_legale_citta');
            $table->string('sede_legale_cap', 10)->nullable()->after('sede_legale_provincia');
            $table->string('partita_iva', 30)->nullable()->after('sede_legale_cap');
            $table->string('codice_fiscale', 30)->nullable()->after('partita_iva');
        });
    }

    public function down(): void
    {
        Schema::table('sedi', function (Blueprint $table) {
            $table->dropColumn([
                'sede_legale',
                'sede_legale_indirizzo',
                'sede_legale_citta',
                'sede_legale_provincia',
                'sede_legale_cap',
                'partita_iva',
                'codice_fiscale',
            ]);
        });
    }
};
