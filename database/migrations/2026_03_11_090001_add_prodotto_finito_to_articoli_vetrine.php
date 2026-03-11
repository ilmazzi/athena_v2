<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('articoli_vetrine', function (Blueprint $table) {
            $table->foreignId('prodotto_finito_id')
                ->nullable()
                ->constrained('prodotti_finiti')
                ->nullOnDelete()
                ->after('articolo_id');

            $table->index('prodotto_finito_id', 'idx_art_vet_pf');
            $table->unique(['prodotto_finito_id', 'vetrina_id'], 'idx_art_vet_pf_unique');
        });
    }

    public function down()
    {
        Schema::table('articoli_vetrine', function (Blueprint $table) {
            $table->dropUnique('idx_art_vet_pf_unique');
            $table->dropIndex('idx_art_vet_pf');
            $table->dropForeign(['prodotto_finito_id']);
            $table->dropColumn('prodotto_finito_id');
        });
    }
};
