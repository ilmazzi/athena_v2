<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Societa;
use App\Models\CategoriaMerceologica;
use App\Models\ContoDeposito;
use App\Models\Articolo;
use App\Models\ProdottoFinito;

class SetupMagazziniContoDeposito extends Command
{
    protected $signature = 'conti-deposito:setup-magazzini
                            {--dry-run : Simula senza salvare}
                            {--fix-articoli : Aggiorna articoli/PF già in deposito}';

    protected $description = 'Crea i magazzini CD-<società> e opzionalmente aggiorna gli articoli/PF già in deposito';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $fixArticoli = (bool) $this->option('fix-articoli');

        $this->info('🧰 Setup magazzini Conto Deposito');
        if ($dryRun) {
            $this->warn('🔍 DRY RUN - Nessuna modifica verrà salvata');
        }

        $stats = [
            'creati' => 0,
            'esistenti' => 0,
            'sedi_mancanti' => 0,
            'articoli_aggiornati' => 0,
            'pf_aggiornati' => 0,
        ];

        $societa = Societa::attive()->get();
        foreach ($societa as $soc) {
            $sedePrincipale = $soc->sedi()->where('attivo', true)->orderBy('id')->first();
            if (!$sedePrincipale) {
                $stats['sedi_mancanti']++;
                $this->warn("  ⚠️  Nessuna sede attiva per società {$soc->ragione_sociale} (ID {$soc->id})");
                continue;
            }

            $codice = "CD-{$soc->codice}";
            $nome = "Conto Deposito {$soc->ragione_sociale}";

            $magazzino = CategoriaMerceologica::where('sede_id', $sedePrincipale->id)
                ->where('codice', $codice)
                ->first();

            if ($magazzino) {
                $stats['esistenti']++;
                continue;
            }

            if (!$dryRun) {
                CategoriaMerceologica::create([
                    'sede_id' => $sedePrincipale->id,
                    'nome' => $nome,
                    'codice' => $codice,
                    'attivo' => true,
                    'note' => 'Magazzino Conto Deposito (auto)',
                ]);
            }
            $stats['creati']++;
            $this->line("  ✓ Creato {$codice} in sede {$sedePrincipale->nome}");
        }

        if ($fixArticoli) {
            $this->newLine();
            $this->info('🔧 Aggiornamento articoli/PF già in deposito...');

            $depositi = ContoDeposito::with(['sedeMittente.societa', 'sedeDestinataria.societa'])
                ->where('stato', '!=', 'chiuso')
                ->get();

            foreach ($depositi as $deposito) {
                if (!$deposito->isInterSocieta()) {
                    continue;
                }

                $socDest = $deposito->getSocietaDestinataria();
                $magazzinoCD = $socDest?->getMagazzinoContoDeposito();
                if (!$magazzinoCD) {
                    $this->warn("  ⚠️  Magazzino CD mancante per società destinataria deposito {$deposito->codice}");
                    continue;
                }

                if (!$dryRun) {
                    $stats['articoli_aggiornati'] += Articolo::where('conto_deposito_corrente_id', $deposito->id)
                        ->where('quantita_in_deposito', '>', 0)
                        ->update(['categoria_merceologica_id' => $magazzinoCD->id]);

                    $stats['pf_aggiornati'] += ProdottoFinito::where('conto_deposito_corrente_id', $deposito->id)
                        ->where('in_conto_deposito', true)
                        ->update(['magazzino_id' => $magazzinoCD->id]);
                }
            }
        }

        $this->newLine();
        $this->info('✅ Completato');
        $this->line("  - Magazzini creati: {$stats['creati']}");
        $this->line("  - Magazzini già presenti: {$stats['esistenti']}");
        $this->line("  - Sedi mancanti: {$stats['sedi_mancanti']}");
        if ($fixArticoli) {
            $this->line("  - Articoli aggiornati: {$stats['articoli_aggiornati']}");
            $this->line("  - PF aggiornati: {$stats['pf_aggiornati']}");
        }

        return 0;
    }
}
