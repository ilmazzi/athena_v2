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
            if (!Schema::hasColumn('articoli', 'magazzino_logico')) {
                $table->unsignedInteger('magazzino_logico')->nullable()->after('categoria_merceologica_id');
                $table->index('magazzino_logico', 'idx_articoli_magazzino_logico');
            }
        });

        Schema::table('giacenze', function (Blueprint $table) {
            if (!Schema::hasColumn('giacenze', 'magazzino_logico')) {
                $table->unsignedInteger('magazzino_logico')->nullable()->after('categoria_merceologica_id');
                $table->index('magazzino_logico', 'idx_giacenze_magazzino_logico');
            }
        });

        Schema::table('ddt', function (Blueprint $table) {
            if (!Schema::hasColumn('ddt', 'magazzino_logico')) {
                $table->unsignedInteger('magazzino_logico')->nullable()->after('categoria_id');
                $table->index('magazzino_logico', 'idx_ddt_magazzino_logico');
            }
        });

        Schema::table('fatture', function (Blueprint $table) {
            if (!Schema::hasColumn('fatture', 'magazzino_logico')) {
                $table->unsignedInteger('magazzino_logico')->nullable()->after('categoria_id');
                $table->index('magazzino_logico', 'idx_fatture_magazzino_logico');
            }
        });

        Schema::table('movimentazioni', function (Blueprint $table) {
            if (!Schema::hasColumn('movimentazioni', 'magazzino_logico_partenza')) {
                $table->unsignedInteger('magazzino_logico_partenza')->nullable()->after('magazzino_partenza_id');
                $table->index('magazzino_logico_partenza', 'idx_mov_mag_logico_partenza');
            }

            if (!Schema::hasColumn('movimentazioni', 'magazzino_logico_destinazione')) {
                $table->unsignedInteger('magazzino_logico_destinazione')->nullable()->after('magazzino_destinazione_id');
                $table->index('magazzino_logico_destinazione', 'idx_mov_mag_logico_dest');
            }
        });

        $this->backfillArticoli();
        $this->backfillGiacenze();
        $this->backfillDocumenti();
        $this->backfillMovimentazioni();
    }

    public function down(): void
    {
        Schema::table('movimentazioni', function (Blueprint $table) {
            if (Schema::hasColumn('movimentazioni', 'magazzino_logico_partenza')) {
                $table->dropIndex('idx_mov_mag_logico_partenza');
                $table->dropColumn('magazzino_logico_partenza');
            }

            if (Schema::hasColumn('movimentazioni', 'magazzino_logico_destinazione')) {
                $table->dropIndex('idx_mov_mag_logico_dest');
                $table->dropColumn('magazzino_logico_destinazione');
            }
        });

        Schema::table('fatture', function (Blueprint $table) {
            if (Schema::hasColumn('fatture', 'magazzino_logico')) {
                $table->dropIndex('idx_fatture_magazzino_logico');
                $table->dropColumn('magazzino_logico');
            }
        });

        Schema::table('ddt', function (Blueprint $table) {
            if (Schema::hasColumn('ddt', 'magazzino_logico')) {
                $table->dropIndex('idx_ddt_magazzino_logico');
                $table->dropColumn('magazzino_logico');
            }
        });

        Schema::table('giacenze', function (Blueprint $table) {
            if (Schema::hasColumn('giacenze', 'magazzino_logico')) {
                $table->dropIndex('idx_giacenze_magazzino_logico');
                $table->dropColumn('magazzino_logico');
            }
        });

        Schema::table('articoli', function (Blueprint $table) {
            if (Schema::hasColumn('articoli', 'magazzino_logico')) {
                $table->dropIndex('idx_articoli_magazzino_logico');
                $table->dropColumn('magazzino_logico');
            }
        });
    }

    private function backfillArticoli(): void
    {
        DB::table('articoli')
            ->select('id', 'categoria_merceologica_id')
            ->orderBy('id')
            ->chunkById(500, function ($articoli) {
                foreach ($articoli as $articolo) {
                    $magazzinoLogico = $this->resolveMagazzinoLogicoFromCategoriaId($articolo->categoria_merceologica_id);
                    if ($magazzinoLogico !== null) {
                        DB::table('articoli')
                            ->where('id', $articolo->id)
                            ->update(['magazzino_logico' => $magazzinoLogico]);
                    }
                }
            });
    }

    private function backfillGiacenze(): void
    {
        DB::table('giacenze')
            ->select('id', 'categoria_merceologica_id', 'articolo_id')
            ->orderBy('id')
            ->chunkById(500, function ($giacenze) {
                foreach ($giacenze as $giacenza) {
                    $magazzinoLogico = $this->resolveMagazzinoLogicoFromCategoriaId($giacenza->categoria_merceologica_id);

                    if ($magazzinoLogico === null && $giacenza->articolo_id) {
                        $articolo = DB::table('articoli')
                            ->select('magazzino_logico')
                            ->where('id', $giacenza->articolo_id)
                            ->first();
                        $magazzinoLogico = $articolo?->magazzino_logico;
                    }

                    if ($magazzinoLogico !== null) {
                        DB::table('giacenze')
                            ->where('id', $giacenza->id)
                            ->update(['magazzino_logico' => $magazzinoLogico]);
                    }
                }
            });
    }

    private function backfillDocumenti(): void
    {
        foreach (['ddt', 'fatture'] as $tabella) {
            DB::table($tabella)
                ->select('id', 'categoria_id')
                ->orderBy('id')
                ->chunkById(500, function ($documenti) use ($tabella) {
                    foreach ($documenti as $documento) {
                        $magazzinoLogico = $this->resolveMagazzinoLogicoFromCategoriaId($documento->categoria_id);
                        if ($magazzinoLogico !== null) {
                            DB::table($tabella)
                                ->where('id', $documento->id)
                                ->update(['magazzino_logico' => $magazzinoLogico]);
                        }
                    }
                });
        }
    }

    private function backfillMovimentazioni(): void
    {
        DB::table('movimentazioni')
            ->select('id', 'magazzino_partenza_id', 'magazzino_destinazione_id')
            ->orderBy('id')
            ->chunkById(500, function ($movimentazioni) {
                foreach ($movimentazioni as $movimentazione) {
                    DB::table('movimentazioni')
                        ->where('id', $movimentazione->id)
                        ->update([
                            'magazzino_logico_partenza' => $this->resolveMagazzinoLogicoFromCategoriaId($movimentazione->magazzino_partenza_id),
                            'magazzino_logico_destinazione' => $this->resolveMagazzinoLogicoFromCategoriaId($movimentazione->magazzino_destinazione_id),
                        ]);
                }
            });
    }

    private function resolveMagazzinoLogicoFromCategoriaId(?int $categoriaId): ?int
    {
        if (!$categoriaId) {
            return null;
        }

        $categoria = DB::table('categorie_merceologiche')
            ->select('codice', 'nome')
            ->where('id', $categoriaId)
            ->first();

        if (!$categoria) {
            return null;
        }

        return $this->extractMagazzinoLogico((string) ($categoria->codice ?? ''), (string) ($categoria->nome ?? ''));
    }

    private function extractMagazzinoLogico(string $codice, string $nome): ?int
    {
        $codice = trim($codice);
        $nome = trim($nome);

        if ($codice !== '' && ctype_digit($codice)) {
            return (int) $codice;
        }

        if ($codice !== '' && preg_match('/(?:MAG|MAGAZZINO)\s*([0-9]+)/i', $codice, $matches)) {
            return (int) $matches[1];
        }

        if ($nome !== '' && preg_match('/MAGAZZINO\s*([0-9]+)/i', $nome, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }
};
