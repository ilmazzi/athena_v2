<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimentazioni', function (Blueprint $table) {
            $table->foreignId('sede_partenza_id')
                ->nullable()
                ->after('magazzino_partenza_id')
                ->constrained('sedi')
                ->nullOnDelete();

            $table->foreignId('sede_destinazione_id')
                ->nullable()
                ->after('magazzino_destinazione_id')
                ->constrained('sedi')
                ->nullOnDelete();
        });

        DB::statement("
            UPDATE movimentazioni m
            LEFT JOIN categorie_merceologiche cp ON cp.id = m.magazzino_partenza_id
            LEFT JOIN categorie_merceologiche cd ON cd.id = m.magazzino_destinazione_id
            LEFT JOIN sedi sp ON sp.id = m.magazzino_partenza_id
            LEFT JOIN sedi sd ON sd.id = m.magazzino_destinazione_id
            SET
                m.sede_partenza_id = CASE
                    WHEN m.magazzino_partenza_id <> m.magazzino_destinazione_id
                        THEN COALESCE(cp.sede_id, sp.id, m.sede_partenza_id)
                    ELSE m.sede_partenza_id
                END,
                m.sede_destinazione_id = CASE
                    WHEN m.magazzino_partenza_id <> m.magazzino_destinazione_id
                        THEN COALESCE(cd.sede_id, sd.id, m.sede_destinazione_id)
                    ELSE m.sede_destinazione_id
                END
            WHERE m.sede_partenza_id IS NULL
               OR m.sede_destinazione_id IS NULL
        ");
    }

    public function down(): void
    {
        Schema::table('movimentazioni', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sede_partenza_id');
            $table->dropConstrainedForeignId('sede_destinazione_id');
        });
    }
};
