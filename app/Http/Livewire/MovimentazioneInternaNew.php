<?php

namespace App\Http\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Sede;
use App\Models\Articolo;
use App\Models\ProdottoFinito;
use App\Models\CategoriaMerceologica;
use App\Services\MovimentazioneService;
use App\Domain\Magazzino\DTOs\MovimentazioneDTO;
use Illuminate\Support\Facades\DB;

/**
 * Livewire Component per Movimentazioni Interne tra Sedi
 * 
 * Funzionalità:
 * - Selezione sedi origine/destinazione
 * - Filtri articoli/PF per categoria e ricerca
 * - Selezione multipla articoli/PF con quantità
 * - Esecuzione movimentazione con DDT
 * - Gestione regole business (giacenze, depositi, vetrine)
 */
class MovimentazioneInternaNew extends Component
{
    use WithPagination;
    
    // ==========================================
    // PROPERTIES
    // ==========================================
    
    public $sedeOrigineId = null;
    public $sedeDestinazioneId = null;
    public $dataMovimentazione = '';
    public $noteMovimentazione = '';
    
    // Filtri
    public $tipoItem = 'articoli'; // 'articoli' | 'prodotti_finiti'
    public $categoriaId = null;
    public $search = '';
    
    // Selezioni
    public $articoliSelezionati = [];
    public $prodottiFinitiSelezionati = [];
    
    // Modal
    public $showMovimentazioneModal = false;
    
    // ==========================================
    // VALIDATION RULES
    // ==========================================
    
    protected $rules = [
        'sedeOrigineId' => 'required|exists:sedi,id|different:sedeDestinazioneId',
        'sedeDestinazioneId' => 'required|exists:sedi,id|different:sedeOrigineId',
        'dataMovimentazione' => 'required|date',
        'noteMovimentazione' => 'nullable|string|max:500',
    ];
    
    protected $messages = [
        'sedeOrigineId.different' => 'La sede origine deve essere diversa dalla destinazione',
        'sedeDestinazioneId.different' => 'La sede destinazione deve essere diversa dall\'origine',
    ];
    
    public function mount()
    {
        \Log::info("🚀 MovimentazioneInternaNew MOUNT - Component caricato");
        $this->dataMovimentazione = now()->format('Y-m-d');
        
        // Preseleziona prima sede attiva come origine
        $primaSede = Sede::attive()->first();
        if ($primaSede) {
            $this->sedeOrigineId = $primaSede->id;
        }

        // Preseleziona una sede diversa come destinazione (se esiste)
        if ($this->sedeOrigineId) {
            $destinazione = Sede::attive()
                ->where('id', '!=', $this->sedeOrigineId)
                ->first();
            $this->sedeDestinazioneId = $destinazione?->id;
        }
    }

    public function updatedSedeOrigineId($value)
    {
        if ($this->sedeDestinazioneId == $value) {
            $destinazione = Sede::attive()
                ->where('id', '!=', $value)
                ->first();
            $this->sedeDestinazioneId = $destinazione?->id;
        }
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingCategoriaId()
    {
        $this->resetPage();
    }

    public function updatingTipoItem()
    {
        $this->resetPage();
    }

    public function updatingSedeOrigineId()
    {
        $this->resetPage();
    }
    
    // ==========================================
    // COMPUTED PROPERTIES
    // ==========================================
    
    public function getSediProperty()
    {
        return Sede::attive()->orderBy('nome')->get();
    }
    
    public function getCategorieProperty()
    {
        if (!$this->sedeOrigineId) {
            return collect();
        }
        
        return CategoriaMerceologica::where('sede_id', $this->sedeOrigineId)
            ->orderBy('nome')
            ->get();
    }
    
    public function getArticoliDisponibiliProperty()
    {
        if (!$this->sedeOrigineId || $this->tipoItem !== 'articoli') {
            return collect();
        }

        $query = Articolo::with(['categoriaMerceologica', 'giacenza'])
            ->where('sede_id', $this->sedeOrigineId)
            ->where('stato', 'disponibile')
            // SOLO articoli con giacenza disponibile
            ->whereHas('giacenza', function($q) {
                $q->where('quantita_residua', '>', 0);
            })
            // ESCLUDI articoli in conto deposito
            ->whereNull('conto_deposito_corrente_id');
            
        if ($this->categoriaId) {
            $query->where('categoria_merceologica_id', $this->categoriaId);
        }
        
        if ($this->search) {
            $query->where(function($q) {
                $q->where('codice', 'like', "%{$this->search}%")
                  ->orWhere('descrizione', 'like', "%{$this->search}%");
            });
        }
        
        return $query->orderBy('codice')->paginate(20);
    }
    
    public function getProdottiFinitiDisponibiliProperty()
    {
        if (!$this->sedeOrigineId || $this->tipoItem !== 'prodotti_finiti') {
            return collect();
        }
        
        $query = ProdottoFinito::with(['componentiArticoli.articolo'])
            ->whereHas('componentiArticoli.articolo', function($q) {
                $q->where('sede_id', $this->sedeOrigineId);
            })
            ->where('stato', 'completato');
            
        if ($this->search) {
            $query->where(function($q) {
                $q->where('codice', 'like', "%{$this->search}%")
                  ->orWhere('descrizione', 'like', "%{$this->search}%");
            });
        }
        
        return $query->orderBy('codice')->paginate(20);
    }
    
    // ==========================================
    // ACTIONS
    // ==========================================
    
    public function toggleArticolo($articoloId)
    {
        if (isset($this->articoliSelezionati[$articoloId])) {
            unset($this->articoliSelezionati[$articoloId]);
        } else {
            $articolo = Articolo::with('giacenza')->findOrFail($articoloId);
            
            // Verifica se in conto deposito
            if ($articolo->isInContoDeposito()) {
                session()->flash('error', "L'articolo {$articolo->codice} è attualmente in conto deposito e non può essere movimentato.");
                return;
            }
            
            // Calcola quantità disponibile per movimentazione
            $quantitaDisponibile = $articolo->getQuantitaDisponibilePerMovimentazione();
            
            if ($quantitaDisponibile <= 0) {
                session()->flash('error', "L'articolo {$articolo->codice} non ha giacenza disponibile per movimentazione.");
                return;
            }
            
            $this->articoliSelezionati[$articoloId] = [
                'articolo_id' => $articoloId,
                'quantita' => 1,
                'max_quantita' => $quantitaDisponibile,
                'codice' => $articolo->codice,
                'descrizione' => $articolo->descrizione,
                'categoria' => $articolo->categoriaMerceologica->nome ?? 'N/A',
                'in_vetrina' => $articolo->isInVetrina(),
                'warning_vetrina' => $articolo->isInVetrina() ? "Articolo in vetrina - sarà rimosso automaticamente" : null,
            ];
            
            // Alert se in vetrina
            if ($articolo->isInVetrina()) {
                session()->flash('warning', "⚠️ L'articolo {$articolo->codice} è attualmente in vetrina. Se movimentato, sarà automaticamente rimosso dalla vetrina.");
            }
        }
    }
    
    public function toggleProdottoFinito($pfId)
    {
        if (isset($this->prodottiFinitiSelezionati[$pfId])) {
            unset($this->prodottiFinitiSelezionati[$pfId]);
        } else {
            $pf = ProdottoFinito::with('componentiArticoli.articolo')->findOrFail($pfId);
            $componenti = $pf->componentiArticoli->map(function ($componente) {
                return [
                    'articolo_id' => $componente->articolo_id,
                    'codice' => $componente->articolo->codice ?? 'N/A',
                    'descrizione' => $componente->articolo->descrizione ?? 'N/A',
                    'quantita' => $componente->quantita,
                ];
            })->values()->all();
            
            $this->prodottiFinitiSelezionati[$pfId] = [
                'prodotto_finito_id' => $pfId,
                'quantita' => 1,
                'codice' => $pf->codice,
                'descrizione' => $pf->descrizione,
                'componenti' => $componenti,
            ];
        }
    }

    public function rimuoviArticoloSelezionato($articoloId)
    {
        unset($this->articoliSelezionati[$articoloId]);
    }

    public function rimuoviProdottoFinitoSelezionato($pfId)
    {
        unset($this->prodottiFinitiSelezionati[$pfId]);
    }
    
    public function apriMovimentazioneModal()
    {
        if (empty($this->articoliSelezionati) && empty($this->prodottiFinitiSelezionati)) {
            session()->flash('error', 'Seleziona almeno un articolo o prodotto finito da spostare');
            return;
        }
        
        if (!$this->sedeDestinazioneId) {
            session()->flash('error', 'Seleziona la sede di destinazione');
            return;
        }
        
        $this->showMovimentazioneModal = true;
    }
    
    public function chiudiMovimentazioneModal()
    {
        $this->showMovimentazioneModal = false;
        $this->reset(['noteMovimentazione']);
    }
    
    public function eseguiMovimentazione()
    {
        \Log::info("🚀 INIZIO eseguiMovimentazione");
        \Log::info("📊 Dati: sedeOrigine={$this->sedeOrigineId}, sedeDestinazione={$this->sedeDestinazioneId}");
        \Log::info("📦 Articoli selezionati: " . count($this->articoliSelezionati));
        \Log::info("🏆 PF selezionati: " . count($this->prodottiFinitiSelezionati));
        
        $this->validate();
        
        if (empty($this->articoliSelezionati) && empty($this->prodottiFinitiSelezionati)) {
            session()->flash('error', 'Seleziona almeno un articolo o prodotto finito da spostare');
            return;
        }
        
        \Log::info("✅ Validazione passata, inizio transazione");
        try {
            DB::transaction(function () {
                $movimentazioneService = app(MovimentazioneService::class);
                $totaleMovimentazioni = 0;
                $movimentazioneMaster = null;

                $articoloCampione = null;
                if (!empty($this->articoliSelezionati)) {
                    $articoloCampione = Articolo::findOrFail(reset($this->articoliSelezionati)['articolo_id']);
                } elseif (!empty($this->prodottiFinitiSelezionati)) {
                    $pfCampione = ProdottoFinito::with('componentiArticoli.articolo')
                        ->findOrFail(reset($this->prodottiFinitiSelezionati)['prodotto_finito_id']);
                    $articoloCampione = $pfCampione->componentiArticoli->first()?->articolo;
                }

                if (!$articoloCampione) {
                    throw new \Exception('Nessun articolo valido per la movimentazione.');
                }

                $magazzinoOrigineId = $articoloCampione->categoria_merceologica_id;
                $magazzinoDestinazioneId = $this->trovaCategoriaDaSede($this->sedeDestinazioneId, $articoloCampione);

                $movimentazioneMaster = $movimentazioneService->creaMovimentazioneMaster(
                    $magazzinoOrigineId,
                    $magazzinoDestinazioneId,
                    $this->dataMovimentazione,
                    $this->noteMovimentazione
                );

                // Movimenta articoli selezionati
                foreach ($this->articoliSelezionati as $articoloData) {
                    $articolo = Articolo::findOrFail($articoloData['articolo_id']);
                    
                    // Verifica finale prima della movimentazione
                    if ($articolo->isInContoDeposito()) {
                        throw new \Exception("L'articolo {$articolo->codice} è in conto deposito e non può essere movimentato.");
                    }
                    
                    $dto = new MovimentazioneDTO(
                        articoloId: $articolo->id,
                        quantita: $articoloData['quantita'],
                        magazzinoOrigineId: $articolo->categoria_merceologica_id,
                        magazzinoDestinazioneId: $this->trovaCategoriaDaSede($this->sedeDestinazioneId, $articolo),
                        dataMovimentazione: $this->dataMovimentazione,
                        note: $this->noteMovimentazione
                    );
                    
                    $movimentazioneService->eseguiMovimentazioneDettaglio($movimentazioneMaster, $dto);
                    $totaleMovimentazioni++;
                    
                    // Rimuovi dalla vetrina se necessario
                    if ($articolo->isInVetrina()) {
                        $articolo->update([
                            'in_vetrina' => false,
                            'ultimo_testo_vetrina' => null
                        ]);
                        \Log::info("Articolo {$articolo->codice} rimosso dalla vetrina per movimentazione");
                    }
                    
                    // Sposta l'articolo nella nuova sede
                    $articolo->update(['sede_id' => $this->sedeDestinazioneId]);
                }
                
                // Movimenta prodotti finiti (sposta tutti i componenti)
                foreach ($this->prodottiFinitiSelezionati as $pfData) {
                    $pf = ProdottoFinito::with('componentiArticoli.articolo')->findOrFail($pfData['prodotto_finito_id']);
                    
                    foreach ($pf->componentiArticoli as $componente) {
                        $articolo = $componente->articolo;
                        
                        $dto = new MovimentazioneDTO(
                            articoloId: $articolo->id,
                            quantita: $componente->quantita,
                            magazzinoOrigineId: $articolo->categoria_merceologica_id,
                            magazzinoDestinazioneId: $this->trovaCategoriaDaSede($this->sedeDestinazioneId, $articolo),
                            dataMovimentazione: $this->dataMovimentazione,
                            note: "Spostamento componente PF {$pf->codice} - {$this->noteMovimentazione}"
                        );
                        
                        $movimentazioneService->eseguiMovimentazioneDettaglio($movimentazioneMaster, $dto);
                        $totaleMovimentazioni++;
                        
                        // Sposta l'articolo componente nella nuova sede
                        $articolo->update(['sede_id' => $this->sedeDestinazioneId]);
                    }
                }
                
                // Reset selezioni
                $this->articoliSelezionati = [];
                $this->prodottiFinitiSelezionati = [];
                $this->chiudiMovimentazioneModal();
                
                session()->flash('success', "Movimentazione completata! {$totaleMovimentazioni} articoli spostati.");
                
                // Redirect al DDT per stampa se disponibile
                if ($movimentazioneMaster) {
                    return redirect()->route('movimentazioni-interne.stampa', $movimentazioneMaster->id);
                }
            });
            
        } catch (\Exception $e) {
            \Log::error("❌ ERRORE MOVIMENTAZIONE: " . $e->getMessage());
            \Log::error("Stack trace: " . $e->getTraceAsString());
            session()->flash('error', 'Errore durante la movimentazione: ' . $e->getMessage());
        }
    }
    
    /**
     * Trova categoria merceologica compatibile nella sede destinazione
     */
    private function trovaCategoriaDaSede($sedeId, $articolo)
    {
        // Cerca categoria con stesso nome nella sede destinazione
        $categoria = CategoriaMerceologica::where('sede_id', $sedeId)
            ->where('nome', $articolo->categoriaMerceologica->nome)
            ->first();
            
        // Se non esiste, prendi la prima categoria della sede
        if (!$categoria) {
            $categoria = CategoriaMerceologica::where('sede_id', $sedeId)->first();
        }

        if (!$categoria) {
            throw new \Exception("Nessuna categoria merceologica disponibile per la sede di destinazione.");
        }
        
        return $categoria->id;
    }
    
    public function getTotaleSelezionati(): int
    {
        return count($this->articoliSelezionati) + count($this->prodottiFinitiSelezionati);
    }
    
    public function render()
    {
        return view('livewire.movimentazione-interna-new', [
            'sedi' => $this->sedi,
            'categorie' => $this->categorie,
            'articoliDisponibili' => $this->articoliDisponibili,
            'prodottiFinitiDisponibili' => $this->prodottiFinitiDisponibili,
        ]);
    }
}
