<?php

namespace App\Services;

use App\Models\Articolo;
use App\Models\ProdottoFinito;
use App\Models\ComponenteProdotto;
use App\Models\Giacenza;
use App\Models\Movimentazione;
use App\Models\CategoriaMerceologica;
use App\Services\CodiceService;
use App\Services\GiacenzaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Service: Gestione Prodotti Finiti
 * 
 * Logica business per assemblaggio prodotti finiti da componenti
 */
class ProdottoFinitoService
{
    protected CodiceService $codiceService;
    protected GiacenzaService $giacenzaService;
    
    public function __construct(
        CodiceService $codiceService,
        GiacenzaService $giacenzaService
    ) {
        $this->codiceService = $codiceService;
        $this->giacenzaService = $giacenzaService;
    }
    
    /**
     * Assembla un nuovo prodotto finito
     * 
     * @param array $dati Dati prodotto finito
     * @param array $componenti Array di ['articolo_id' => id, 'quantita' => qty]
     * @param int $sedeId Sede dove viene creato
     * @param int $categoriaId Categoria prodotto finale (di solito 9 - gioielleria)
     * @return ProdottoFinito
     * @throws \Exception
     */
    public function assemblaProdotto(
        array $dati,
        array $componenti,
        int $sedeId,
        int $categoriaId = 9
    ): ProdottoFinito {
        DB::beginTransaction();
        
        try {
            // 1. Verifica disponibilità componenti
            foreach ($componenti as $comp) {
                $this->verificaDisponibilitaComponente($comp['articolo_id'], $comp['quantita'], $sedeId);
            }
            
            // 2. Calcola dati gioielleria dai componenti
            $datiGioielleria = $this->calcolaDatiGioielleria($componenti);
            
            // 3. Genera codice prodotto finito basato sulla sede
            $codicePF = $this->generaCodice($sedeId);
            
            // 4. Crea prodotto finito (sempre completato quando creato)
            $prodottoFinito = ProdottoFinito::create([
                'codice' => $codicePF,
                'descrizione' => $dati['descrizione'],
                'tipologia' => $dati['tipologia'] ?? 'prodotto_finito',
                'magazzino_id' => $categoriaId,
                'stato' => 'completato',
                'oro_totale' => $datiGioielleria['oro'],
                'brillanti_totali' => $datiGioielleria['brillanti'],
                'pietre_totali' => $datiGioielleria['pietre'],
                'costo_lavorazione' => $dati['costo_lavorazione'] ?? 0,
                'note' => $dati['note'] ?? null,
                'data_inizio_lavorazione' => now(),
                'data_completamento' => now(),
                'assemblato_da' => Auth::id(),
                'creato_da' => Auth::id(),
            ]);
            
            // 5. Aggiungi componenti
            $costoMateriali = 0;
            foreach ($componenti as $comp) {
                $articolo = Articolo::findOrFail($comp['articolo_id']);
                $quantita = $comp['quantita'];
                $costoUnitario = $articolo->prezzo_acquisto ?? 0;
                
                ComponenteProdotto::create([
                    'prodotto_finito_id' => $prodottoFinito->id,
                    'articolo_id' => $articolo->id,
                    'quantita' => $quantita,
                    'costo_unitario' => $costoUnitario,
                    'costo_totale' => $costoUnitario * $quantita,
                    'stato' => 'prelevato',
                    'prelevato_il' => now(),
                    'prelevato_da' => Auth::id(),
                ]);
                
                $costoMateriali += ($costoUnitario * $quantita);
                
                // Scarica componente da giacenza
                $this->scaricarComponente($articolo->id, $quantita, $sedeId, $prodottoFinito->id);
                
                // Aggiorna stato articolo (SENZA data_scarico!)
                $articolo->update(['stato_articolo' => 'in_prodotto_finito']);
            }
            
            // 6. Aggiorna costi totali
            $costoTotale = $costoMateriali + ($dati['costo_lavorazione'] ?? 0);
            $prodottoFinito->update([
                'costo_materiali' => $costoMateriali,
                'costo_totale' => $costoTotale,
            ]);
            
            DB::commit();
            
            return $prodottoFinito;
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
    
    /**
     * Completa l'assemblaggio e crea l'articolo finale in magazzino
     */
    public function completaAssemblaggio(int $prodottoFinitoId): Articolo
    {
        DB::beginTransaction();
        
        try {
            $prodottoFinito = ProdottoFinito::with('componentiArticoli.articolo')->findOrFail($prodottoFinitoId);
            
            if ($prodottoFinito->stato === 'completato') {
                throw new \Exception('Prodotto finito già completato');
            }
            
            // Genera codice articolo finale
            $categoria = CategoriaMerceologica::findOrFail($prodottoFinito->magazzino_id);
            $codiceArticolo = $this->codiceService->prossimoCodiceDisponibile($prodottoFinito->magazzino_id);
            
            // Crea articolo finale in magazzino
            $articoloFinale = Articolo::create([
                'codice' => $codiceArticolo->toString(),
                'descrizione' => $prodottoFinito->descrizione,
                'categoria_merceologica_id' => $prodottoFinito->magazzino_id,
                'sede_id' => $prodottoFinito->componentiArticoli->first()->articolo->sede_id ?? 1,
                'prodotto_finito_id' => $prodottoFinito->id,
                'tipo_carico' => 'produzione_interna',
                'numero_documento_carico' => '0', // Produzione interna
                'data_carico' => now(),
                'prezzo_acquisto' => $prodottoFinito->costo_totale,
                'stato' => 'disponibile',
                'assemblato_il' => now(),
                'assemblato_da' => Auth::id(),
                'caratteristiche' => [
                    'oro' => $prodottoFinito->oro_totale,
                    'brill' => $prodottoFinito->brillanti_totali,
                    'pietre' => $prodottoFinito->pietre_totali,
                ],
            ]);
            
            // Crea giacenza per articolo finale
            Giacenza::create([
                'articolo_id' => $articoloFinale->id,
                'sede_id' => $articoloFinale->sede_id,
                'quantita' => 1,
                'quantita_residua' => 1,
                'quantita_iniziale' => 1,
            ]);
            
            // Aggiorna stato componenti
            $prodottoFinito->componentiArticoli()->update([
                'stato' => 'utilizzato'
            ]);
            
            // Aggiorna prodotto finito
            $prodottoFinito->update([
                'stato' => 'completato',
                'data_completamento' => now(),
                'assemblato_da' => Auth::id(),
                'articolo_risultante_id' => $articoloFinale->id,
            ]);
            
            DB::commit();
            
            return $articoloFinale;
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
    
    /**
     * Aggiorna un prodotto finito esistente
     */
    public function aggiornaProdotto(
        int $prodottoFinitoId,
        array $dati,
        array $componenti,
        int $sedeId,
        int $categoriaId = 9
    ): ProdottoFinito {
        DB::beginTransaction();
        
        try {
            $prodottoFinito = ProdottoFinito::with('componentiArticoli')->findOrFail($prodottoFinitoId);
            
            Log::info('🔄 Inizio aggiornamento prodotto finito', [
                'prodotto_id' => $prodottoFinitoId,
                'componenti_attuali' => $prodottoFinito->componentiArticoli->count(),
                'nuovi_componenti' => count($componenti),
            ]);
            
            // IMPORTANTE: Prima ripristina i componenti esistenti, POI verifica disponibilità
            // Questo permette di riutilizzare gli stessi componenti in modifica
            
            // 1. Ripristina giacenze e stati dei componenti esistenti
            Log::info('♻️ Ripristino giacenze componenti esistenti');
            foreach ($prodottoFinito->componentiArticoli as $componente) {
                Log::info('  ↩️ Ripristino', [
                    'articolo_id' => $componente->articolo_id,
                    'quantita' => $componente->quantita,
                ]);
                $this->ripristinaComponente(
                    $componente->articolo_id,
                    $componente->quantita,
                    $sedeId
                );
                
                // Ripristina stato articolo a 'disponibile'
                $componente->articolo->update(['stato_articolo' => 'disponibile']);
            }
            
            // 2. Ora verifica disponibilità nuovi componenti (dopo aver ripristinato)
            Log::info('✅ Verifica disponibilità nuovi componenti');
            foreach ($componenti as $comp) {
                $this->verificaDisponibilitaComponente($comp['articolo_id'], $comp['quantita'], $sedeId);
            }
            
            // 3. Calcola dati gioielleria dai nuovi componenti
            $datiGioielleria = $this->calcolaDatiGioielleria($componenti);
            
            // 4. Aggiorna dati prodotto
            $prodottoFinito->update([
                'descrizione' => $dati['descrizione'],
                'tipologia' => $dati['tipologia'] ?? 'prodotto_finito',
                'magazzino_id' => $categoriaId,
                'oro_totale' => $datiGioielleria['oro'],
                'brillanti_totali' => $datiGioielleria['brillanti'],
                'pietre_totali' => $datiGioielleria['pietre'],
                'costo_lavorazione' => $dati['costo_lavorazione'] ?? 0,
                'note' => $dati['note'] ?? null,
            ]);
            
            // 5. Elimina componenti esistenti (già ripristinati sopra)
            $prodottoFinito->componentiArticoli()->delete();
            
            // Aggiungi nuovi componenti
            $costoMateriali = 0;
            foreach ($componenti as $comp) {
                $articolo = Articolo::findOrFail($comp['articolo_id']);
                $quantita = $comp['quantita'];
                $costoUnitario = $articolo->prezzo_acquisto ?? 0;
                
                ComponenteProdotto::create([
                    'prodotto_finito_id' => $prodottoFinito->id,
                    'articolo_id' => $articolo->id,
                    'quantita' => $quantita,
                    'costo_unitario' => $costoUnitario,
                    'costo_totale' => $costoUnitario * $quantita,
                    'stato' => 'prelevato',
                    'prelevato_il' => now(),
                    'prelevato_da' => Auth::id(),
                ]);
                
                $costoMateriali += ($costoUnitario * $quantita);
                
                // Scarica nuovo componente da giacenza
                $this->scaricarComponente($articolo->id, $quantita, $sedeId, $prodottoFinito->id);
                
                // Aggiorna stato articolo (SENZA data_scarico!)
                $articolo->update(['stato_articolo' => 'in_prodotto_finito']);
            }
            
            // Aggiorna costi totali
            $costoTotale = $costoMateriali + ($dati['costo_lavorazione'] ?? 0);
            $prodottoFinito->update([
                'costo_materiali' => $costoMateriali,
                'costo_totale' => $costoTotale,
            ]);
            
            DB::commit();
            
            return $prodottoFinito;
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
    
    /**
     * Annulla assemblaggio e ripristina componenti
     */
    public function annullaAssemblaggio(int $prodottoFinitoId): void
    {
        DB::beginTransaction();
        
        try {
            $prodottoFinito = ProdottoFinito::with('componentiArticoli.articolo', 'articoloRisultante')->findOrFail($prodottoFinitoId);
            
            // Ripristina giacenze componenti
            foreach ($prodottoFinito->componentiArticoli as $componente) {
                $this->ripristinaComponente(
                    $componente->articolo_id,
                    $componente->quantita,
                    $componente->articolo->sede_id ?? 1
                );

                if ($componente->articolo) {
                    $componente->articolo->update(['stato_articolo' => 'disponibile']);
                }
            }
            
            // Elimina articolo risultante se esiste
            if ($prodottoFinito->articoloRisultante) {
                $prodottoFinito->articoloRisultante->giacenza()->delete();
                $prodottoFinito->articoloRisultante->delete();
            }
            
            // Soft delete prodotto finito
            $prodottoFinito->update(['stato' => 'annullato']);
            $prodottoFinito->delete();
            
            DB::commit();
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
    
    // ==========================================
    // METODI PRIVATI
    // ==========================================
    
    /**
     * Verifica disponibilità componente
     */
    private function verificaDisponibilitaComponente(int $articoloId, int $quantita, int $sedeId): void
    {
        $giacenza = Giacenza::where('articolo_id', $articoloId)
            ->where('sede_id', $sedeId)
            ->first();
        
        if (!$giacenza) {
            $articolo = Articolo::find($articoloId);
            throw new \Exception("Articolo {$articolo->codice} non presente nella sede selezionata");
        }
        
        if ($giacenza->quantita_residua < $quantita) {
            $articolo = Articolo::find($articoloId);
            throw new \Exception("Giacenza insufficiente per articolo {$articolo->codice}. Disponibili: {$giacenza->quantita_residua}, Richiesti: {$quantita}");
        }
    }
    
    /**
     * Scarica componente da giacenza
     */
    private function scaricarComponente(int $articoloId, int $quantita, int $sedeId, int $prodottoFinitoId): void
    {
        $giacenza = Giacenza::where('articolo_id', $articoloId)
            ->where('sede_id', $sedeId)
            ->firstOrFail();
        
        // Decrementa giacenza
        $giacenza->decrement('quantita_residua', $quantita);
        
        // Genera numero documento univoco
        $numeroDocumento = $this->generaNumeroDocumentoUnivoco($prodottoFinitoId);
        
        // Crea movimentazione (testata)
        $movimentazione = Movimentazione::create([
            'numero_documento' => $numeroDocumento,
            'magazzino_partenza_id' => $sedeId, // Sede da cui prelevo
            'magazzino_destinazione_id' => $sedeId, // Stessa sede (assemblaggio)
            'data_movimentazione' => now()->toDateString(),
            'stato' => 'confermata',
            'causale' => 'assemblaggio_prodotto_finito',
            'creata_da' => Auth::id(),
            'confermata_da' => Auth::id(),
            'confermata_at' => now(),
            'completata_at' => now(),
            'note' => "Assemblaggio Prodotto Finito ID: {$prodottoFinitoId}",
        ]);
        
        // Crea dettaglio movimentazione
        $movimentazione->dettagli()->create([
            'articolo_id' => $articoloId,
            'quantita' => $quantita,
            'note' => "Componente per assemblaggio PF-{$prodottoFinitoId}",
        ]);
    }
    
    /**
     * Ripristina componente in giacenza
     */
    private function ripristinaComponente(int $articoloId, int $quantita, int $sedeId): void
    {
        $giacenza = Giacenza::where('articolo_id', $articoloId)
            ->where('sede_id', $sedeId)
            ->firstOrFail();
        
        // Incrementa giacenza
        $giacenza->increment('quantita_residua', $quantita);
        
        // Genera numero documento univoco per ripristino
        $numeroDocumento = $this->generaNumeroDocumentoRipristino();
        
        // Crea movimentazione di ripristino
        $movimentazione = Movimentazione::create([
            'numero_documento' => $numeroDocumento,
            'magazzino_partenza_id' => $sedeId,
            'magazzino_destinazione_id' => $sedeId,
            'data_movimentazione' => now()->toDateString(),
            'stato' => 'confermata',
            'causale' => 'annullamento_assemblaggio',
            'creata_da' => Auth::id(),
            'confermata_da' => Auth::id(),
            'confermata_at' => now(),
            'completata_at' => now(),
            'note' => "Ripristino componente per annullamento assemblaggio",
        ]);
        
        // Crea dettaglio movimentazione
        $movimentazione->dettagli()->create([
            'articolo_id' => $articoloId,
            'quantita' => $quantita,
            'note' => "Ripristino componente",
        ]);
    }
    
    /**
     * Calcola dati gioielleria totali dai componenti
     */
    private function calcolaDatiGioielleria(array $componenti): array
    {
        $oro = [];
        $brillanti = [];
        $pietre = [];
        
        foreach ($componenti as $comp) {
            $articolo = Articolo::find($comp['articolo_id']);
            if (!$articolo) continue;
            
            $caratteristiche = is_string($articolo->caratteristiche)
                ? json_decode($articolo->caratteristiche, true)
                : $articolo->caratteristiche;
            
            if (!empty($caratteristiche['oro'])) {
                $oro[] = $caratteristiche['oro'];
            }
            if (!empty($caratteristiche['brill'])) {
                $brillanti[] = $caratteristiche['brill'];
            }
            if (!empty($caratteristiche['pietre'])) {
                $pietre[] = $caratteristiche['pietre'];
            }
        }
        
        return [
            'oro' => !empty($oro) ? implode(' + ', array_unique($oro)) : null,
            'brillanti' => !empty($brillanti) ? implode(' + ', array_unique($brillanti)) : null,
            'pietre' => !empty($pietre) ? implode(' + ', array_unique($pietre)) : null,
        ];
    }
    
    /**
     * Genera codice univoco prodotto finito basato sulla sede
     */
    private function generaCodice(int $sedeId): string
    {
        return DB::transaction(function () use ($sedeId) {
            // Determina categoria in base alla sede
            // CAVOUR (ID 1) → Categoria 9 (gioielleria)
            // ROMA (ID 5) → Categoria 22 (semilavorati)  
            // Altre sedi → Categoria 9 (default)
            $categoriaId = $sedeId == 5 ? 22 : 9;
            
            // Trova ultimo prodotto finito per questa sede
            $ultimoProdotto = ProdottoFinito::where('magazzino_id', $categoriaId)
                ->lockForUpdate()
                ->orderBy('id', 'desc')
                ->first();
            
            if (!$ultimoProdotto) {
                // Primo prodotto finito per questa sede
                return sprintf('%d-%d', $categoriaId, 1);
            }
            
            // Parse l'ultimo codice per ottenere il progressivo
            // Formato: 9-271 → 271
            if (preg_match('/\d+-(\d+)/', $ultimoProdotto->codice, $matches)) {
                $ultimoProgressivo = (int)$matches[1];
                $prossimoProgressivo = $ultimoProgressivo + 1;
            } else {
                // Fallback: conta tutti i prodotti finiti + 1
                $count = ProdottoFinito::where('magazzino_id', $categoriaId)->count();
                $prossimoProgressivo = $count + 1;
            }
            
            // Genera codice con formato: 9-xxx per CAVOUR e altre sedi, 22-xxx per ROMA
            $nuovoCodice = sprintf('%d-%d', $categoriaId, $prossimoProgressivo);
            
            // Verifica che non esista già (safety check)
            $tentativi = 0;
            while (ProdottoFinito::where('codice', $nuovoCodice)->exists() && $tentativi < 100) {
                $prossimoProgressivo++;
                $nuovoCodice = sprintf('%d-%d', $categoriaId, $prossimoProgressivo);
                $tentativi++;
            }
            
            if ($tentativi >= 100) {
                throw new \Exception('Impossibile generare un codice univoco dopo 100 tentativi');
            }
            
            return $nuovoCodice;
        });
    }
    
    /**
     * Genera numero documento univoco per movimentazione
     */
    private function generaNumeroDocumentoUnivoco(int $prodottoFinitoId): string
    {
        $baseNumero = 'PF-' . $prodottoFinitoId . '-' . now()->format('YmdHis');
        $numeroDocumento = $baseNumero;
        $contatore = 1;
        
        // Verifica unicità e aggiungi suffisso se necessario
        while (Movimentazione::where('numero_documento', $numeroDocumento)->exists()) {
            $numeroDocumento = $baseNumero . '-' . $contatore;
            $contatore++;
        }
        
        return $numeroDocumento;
    }
    
    /**
     * Genera numero documento univoco per ripristino
     */
    private function generaNumeroDocumentoRipristino(): string
    {
        $baseNumero = 'RIP-' . now()->format('YmdHis');
        $numeroDocumento = $baseNumero;
        $contatore = 1;
        
        // Verifica unicità e aggiungi suffisso se necessario
        while (Movimentazione::where('numero_documento', $numeroDocumento)->exists()) {
            $numeroDocumento = $baseNumero . '-' . $contatore;
            $contatore++;
        }
        
        return $numeroDocumento;
    }
}

