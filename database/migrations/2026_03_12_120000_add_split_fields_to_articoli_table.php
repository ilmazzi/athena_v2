<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articoli', function (Blueprint $table) {
            $table->unsignedBigInteger('articolo_padre_id')
                ->nullable()
                ->after('id');
            $table->unsignedInteger('split_index')
                ->nullable()
                ->after('articolo_padre_id');
            $table->string('codice_base', 100)
                ->nullable()
                ->after('codice');

            $table->foreign('articolo_padre_id')
                ->references('id')
                ->on('articoli')
                ->nullOnDelete();

            $table->index(['codice_base', 'split_index'], 'idx_articoli_codice_base_split');
        });

        DB::table('articoli')
            ->whereNull('codice_base')
            ->update(['codice_base' => DB::raw('codice')]);
    }

    public function down(): void
    {
        Schema::table('articoli', function (Blueprint $table) {
            $table->dropForeign(['articolo_padre_id']);
            $table->dropIndex('idx_articoli_codice_base_split');
            $table->dropColumn(['articolo_padre_id', 'split_index', 'codice_base']);
        });
    }
};
