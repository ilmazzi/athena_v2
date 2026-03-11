<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('movimentazioni_dettagli', function (Blueprint $table) {
            $table->foreignId('prodotto_finito_id')
                ->nullable()
                ->constrained('prodotti_finiti')
                ->nullOnDelete()
                ->after('articolo_id');

            $table->index('prodotto_finito_id', 'idx_mov_det_pf');
        });
    }

    public function down()
    {
        Schema::table('movimentazioni_dettagli', function (Blueprint $table) {
            $table->dropIndex('idx_mov_det_pf');
            $table->dropForeign(['prodotto_finito_id']);
            $table->dropColumn('prodotto_finito_id');
        });
    }
};
