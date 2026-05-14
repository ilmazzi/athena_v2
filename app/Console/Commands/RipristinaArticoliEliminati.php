<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Ripristina articoli incorrettamente soft-deleted da script di migrazione (apr 2026).
 *
 * Regola di dominio: gli articoli NON vengono mai eliminati. La "vendita" o lo
 * "scarico" si traduce unicamente in quantita_residua = 0 sulla giacenza.
 *
 * Cosa viene ripristinato:
 *   - Tutti gli articoli con deleted_at valorizzato
 *   - ESCLUSI articoloRisultante di PF annullati (prodotto_finito_id IS NOT NULL)
 *   - ESCLUSI placeholder di migrazione (descrizione = 'INESISTENTE')
 *
 * Dopo il ripristino, per gli articoli senza giacenza viene creata
 * una giacenza a quantita_residua = 0 (storico, non interferisce con il magazzino).
 */
class RipristinaArticoliEliminati extends Command
{
    protected $signature = 'magazzino:ripristina-articoli-eliminati
                            {--dry-run : Mostra cosa verrebbe fatto senza applicare modifiche}';

    protected $description = 'Ripristina articoli incorrettamente soft-deleted dagli script di migrazione di aprile 2026';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('MODALITÀ DRY-RUN — nessuna modifica verrà applicata.');
        } else {
            if (!$this->confirm('Stai per ripristinare migliaia di articoli eliminati. Continuare?')) {
                $this->info('Operazione annullata.');
                return self::SUCCESS;
            }
        }

        // ── analisi ──────────────────────────────────────────────────────────

        $totaleEliminati = DB::table('articoli')
            ->whereNotNull('deleted_at')
            ->count();

        $escludiPf = DB::table('articoli')
            ->whereNotNull('deleted_at')
            ->whereNotNull('prodotto_finito_id')
            ->count();

        $escludiInesistenti = DB::table('articoli')
            ->whereNotNull('deleted_at')
            ->where('descrizione', 'INESISTENTE')
            ->count();

        $daDripristinare = $totaleEliminati - $escludiPf - $escludiInesistenti;

        $conGiacenzaPositiva = DB::table('articoli as a')
            ->join('giacenze as g', 'g.articolo_id', '=', 'a.id')
            ->whereNotNull('a.deleted_at')
            ->whereNull('a.prodotto_finito_id')
            ->where('a.descrizione', '!=', 'INESISTENTE')
            ->where('g.quantita_residua', '>', 0)
            ->count();

        $senzaGiacenza = DB::table('articoli as a')
            ->whereNotNull('a.deleted_at')
            ->whereNull('a.prodotto_finito_id')
            ->where('a.descrizione', '!=', 'INESISTENTE')
            ->whereNotExists(function ($q) {
                $q->from('giacenze')->whereColumn('giacenze.articolo_id', 'a.id');
            })
            ->count();

        $this->info('');
        $this->info('── Analisi articoli eliminati ────────────────────');
        $this->line("  Totale soft-deleted:              {$totaleEliminati}");
        $this->line("  Esclusi (articoloRisultante PF):  {$escludiPf}");
        $this->line("  Esclusi (placeholder INESISTENTE):{$escludiInesistenti}");
        $this->line("  Da ripristinare:                  {$daDripristinare}");
        $this->info('');
        $this->line("  Di cui giacenti (qta_residua > 0): {$conGiacenzaPositiva}  ← attivi ma invisibili al sistema");
        $this->line("  Di cui senza giacenza:             {$senzaGiacenza}  ← verrà creata giacenza a 0");
        $this->info('─────────────────────────────────────────────────');
        $this->info('');

        if ($dryRun) {
            $this->showSample();
            $this->info('Dry-run completato. Nessuna modifica applicata.');
            $this->info('Riesegui senza --dry-run per applicare.');
            return self::SUCCESS;
        }

        // ── esecuzione ───────────────────────────────────────────────────────

        DB::transaction(function () use ($daDripristinare, $senzaGiacenza) {

            // 1. Ripristina articoli (set deleted_at = NULL)
            $ripristinati = DB::table('articoli')
                ->whereNotNull('deleted_at')
                ->whereNull('prodotto_finito_id')
                ->where('descrizione', '!=', 'INESISTENTE')
                ->update(['deleted_at' => null]);

            $this->info("Articoli ripristinati: {$ripristinati}");
            Log::info("RipristinaArticoliEliminati: {$ripristinati} articoli ripristinati.");

            // 2. Crea giacenza mancante per gli articoli senza giacenza
            if ($senzaGiacenza > 0) {
                $articoliSenzaGiacenza = DB::table('articoli as a')
                    ->select('a.id', 'a.categoria_merceologica_id', 'a.sede_id')
                    ->whereNull('a.deleted_at')
                    ->whereNotExists(function ($q) {
                        $q->from('giacenze')->whereColumn('giacenze.articolo_id', 'a.id');
                    })
                    ->get();

                $creati = 0;
                foreach ($articoliSenzaGiacenza as $art) {
                    DB::table('giacenze')->insert([
                        'articolo_id'             => $art->id,
                        'categoria_merceologica_id' => $art->categoria_merceologica_id,
                        'sede_id'                 => $art->sede_id,
                        'quantita'                => 0,
                        'quantita_iniziale'       => 0,
                        'quantita_residua'        => 0,
                        'created_at'              => now(),
                        'updated_at'              => now(),
                    ]);
                    $creati++;
                }

                $this->info("Giacenze create (storico a 0): {$creati}");
                Log::info("RipristinaArticoliEliminati: {$creati} giacenze create per articoli senza giacenza.");
            }
        });

        $this->info('');
        $this->info('Operazione completata con successo.');
        $this->info('Verifica: php artisan magazzino:ripristina-articoli-eliminati --dry-run');

        return self::SUCCESS;
    }

    private function showSample(): void
    {
        $campione = DB::table('articoli as a')
            ->leftJoin('giacenze as g', 'g.articolo_id', '=', 'a.id')
            ->leftJoin('categorie_merceologiche as cm', 'cm.id', '=', 'a.categoria_merceologica_id')
            ->select(
                'a.codice',
                'a.descrizione',
                'a.stato_articolo',
                'a.deleted_at',
                DB::raw('COALESCE(g.quantita_residua, "—") as qta_residua'),
                'cm.nome as categoria'
            )
            ->whereNotNull('a.deleted_at')
            ->whereNull('a.prodotto_finito_id')
            ->where('a.descrizione', '!=', 'INESISTENTE')
            ->orderByDesc('g.quantita_residua')
            ->limit(15)
            ->get();

        $this->info('Campione articoli che verrebbero ripristinati (primi 15, ordinati per giacenza):');
        $this->table(
            ['Codice', 'Descrizione', 'Stato', 'Deleted at', 'Qta residua', 'Categoria'],
            $campione->map(fn($r) => [
                $r->codice,
                mb_strimwidth($r->descrizione, 0, 40, '…'),
                $r->stato_articolo,
                $r->deleted_at,
                $r->qta_residua,
                $r->categoria,
            ])->toArray()
        );
    }
}
