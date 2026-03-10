<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('articoli_vetrine', function (Blueprint $table) {
            $table->unsignedBigInteger('articolo_id')->nullable()->change();

            $table->string('tipo_articolo', 20)->default('interno')->after('articolo_id');
            $table->text('descrizione_esterno')->nullable()->after('tipo_articolo');
            $table->foreignId('categoria_merceologica_id')
                ->nullable()
                ->constrained('categorie_merceologiche')
                ->nullOnDelete()
                ->after('descrizione_esterno');
            $table->foreignId('sede_id')
                ->nullable()
                ->constrained('sedi')
                ->nullOnDelete()
                ->after('categoria_merceologica_id');
            $table->string('foto_principale_esterno', 255)->nullable()->after('sede_id');

            $table->string('materiale_esterno', 100)->nullable()->after('foto_principale_esterno');
            $table->string('titolo_esterno', 50)->nullable()->after('materiale_esterno');
            $table->string('caratura_esterno', 50)->nullable()->after('titolo_esterno');
            $table->string('colore_esterno', 50)->nullable()->after('caratura_esterno');
            $table->decimal('peso_lordo_esterno', 10, 2)->nullable()->after('colore_esterno');
            $table->decimal('peso_netto_esterno', 10, 2)->nullable()->after('peso_lordo_esterno');
            $table->decimal('prezzo_acquisto_esterno', 10, 2)->nullable()->after('peso_netto_esterno');
            $table->decimal('prezzo_fornitore_esterno', 10, 2)->nullable()->after('prezzo_acquisto_esterno');
            $table->text('note_esterno')->nullable()->after('prezzo_fornitore_esterno');
        });
    }

    public function down()
    {
        Schema::table('articoli_vetrine', function (Blueprint $table) {
            $table->dropForeign(['categoria_merceologica_id']);
            $table->dropForeign(['sede_id']);

            $table->dropColumn([
                'tipo_articolo',
                'descrizione_esterno',
                'categoria_merceologica_id',
                'sede_id',
                'foto_principale_esterno',
                'materiale_esterno',
                'titolo_esterno',
                'caratura_esterno',
                'colore_esterno',
                'peso_lordo_esterno',
                'peso_netto_esterno',
                'prezzo_acquisto_esterno',
                'prezzo_fornitore_esterno',
                'note_esterno',
            ]);

            $table->unsignedBigInteger('articolo_id')->nullable(false)->change();
        });
    }
};
