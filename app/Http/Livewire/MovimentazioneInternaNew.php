<?php

namespace App\Http\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Sede;
use App\Models\Articolo;
use App\Models\Giacenza;
use App\Models\CategoriaMerceologica;
use App\Services\MovimentazioneService;
use App\Services\ArticoloSplitService;
use App\Services\MagazzinoLogicoService;
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
    public $trasportoMezzo = '';
    public $aspettoBeni = '';
    public $colli = '';
    public $vettore = '';
    
    // Filtri
    public $categoriaId = null;
    public $search = '';
    
    // Selezioni
    public $articoliSelezionati = [];
    
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
        'trasportoMezzo' => 'nullable|string|max:100',
        'aspettoBeni' => 'nullable|string|max:100',
        'colli' => 'nullable|string|max:50',
        'vettore' => 'nullable|string|max:100',
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

        if ($this->categoriaId && !$this->getCategorieProperty()->contains('id', (int) $this->categoriaId)) {
            $this->categoriaId = null;
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

        $service = app(MagazzinoLogicoService::class);

        return CategoriaMerceologica::withoutGlobalScopes()
            ->where('sede_id', $this->sedeOrigineId)
            ->orderBy('nome')
            ->get()
            ->map(function (CategoriaMerceologica $categoria) use ($service) {
                $magazzinoLogico = $service->resolveFromCategoria($categoria);
                if (!$magazzinoLogico) {
                    return null;
                }

                return (object) [
                    'id' => $magazzinoLogico,
                    'nome' => $service->getLabelForCategoria($categoria),
                    'categoria_locale_id' => $categoria->id,
                    'categoria_locale_codice' => $categoria->codice,
                ];
            })
            ->filter()
            ->unique('id')
            ->sortBy('id')
            ->values();
    }
    
    public function getArticoliDisponibiliProperty()
    {
        if (!$this->sedeOrigineId) {
            return collect();
        }

        $query = Articolo::with(['categoriaMerceologica', 'giacenza', 'prodottoFinito.componentiArticoli.articolo'])
            ->where('stato', 'disponibile')
            // SOLO articoli con giacenza disponibile
            ->whereHas('giacenza', function($q) {
                $q->where('quantita_residua', '>', 0);
            })
            // Articoli della sede o con giacenza legata alla sede
            ->where(function ($q) {
                $q->where('sede_id', $this->sedeOrigineId)
                  ->orWhereHas('giacenza', function ($subQ) {
                      $subQ->where('sede_id', $this->sedeOrigineId);
                  });
            })
            // ESCLUDI articoli in conto deposito
            ->whereNull('conto_deposito_corrente_id');
            
        if ($this->categoriaId) {
            $query->where('magazzino_logico', $this->categoriaId);
        }
        
        if ($this->search) {
            $query->where(function($q) {
                $q->where('codice', 'like', "%{$this->search}%")
                  ->orWhere('codice_base', 'like', "%{$this->search}%")
                  ->orWhere('descrizione', 'like', "%{$this->search}%");
            });
        }
        
        return $query->orderByRaw("COALESCE(codice_base, codice)")->paginate(20);
    }
    
    
    // ==========================================
    // ACTIONS
    // ==========================================
    
    public function toggleArticolo($articoloId)
    {
        if (isset($this->articoliSelezionati[$articoloId])) {
            unset($this->articoliSelezionati[$articoloId]);
        } else {
            $articolo = Articolo::with(['giacenza', 'categoriaMerceologica', 'prodottoFinito.componentiArticoli.articolo'])->findOrFail($articoloId);
            
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
            
            $isPf = (bool) $articolo->prodottoFinito;
            $componenti = [];
            if ($isPf) {
                $componenti = $articolo->prodottoFinito->componentiArticoli->map(function ($componente) {
                    return [
                        'articolo_id' => $componente->articolo_id,
                        'codice' => $componente->articolo->codice ?? 'N/A',
                        'descrizione' => $componente->articolo->descrizione ?? 'N/A',
                        'quantita' => $componente->quantita,
                    ];
                })->values()->all();
            }

            $this->articoliSelezionati[$articoloId] = [
                'articolo_id' => $articoloId,
                'quantita' => 1,
                'max_quantita' => $isPf ? min(1, $quantitaDisponibile) : $quantitaDisponibile,
                'codice' => $articolo->codice,
                'descrizione' => $articolo->descrizione,
                'categoria' => $this->labelMagazzinoLogico($articolo->magazzino_logico),
                'in_vetrina' => $articolo->isInVetrina(),
                'warning_vetrina' => $articolo->isInVetrina() ? "Articolo in vetrina - sarà rimosso automaticamente" : null,
                'is_pf' => $isPf,
                'componenti' => $componenti,
            ];
            
            // Alert se in vetrina
            if ($articolo->isInVetrina()) {
                session()->flash('warning', "⚠️ L'articolo {$articolo->codice} è attualmente in vetrina. Se movimentato, sarà automaticamente rimosso dalla vetrina.");
            }
        }
    }
    

    public function rimuoviArticoloSelezionato($articoloId)
    {
        unset($this->articoliSelezionati[$articoloId]);
    }

    
    public function apriMovimentazioneModal()
    {
        if (empty($this->articoliSelezionati)) {
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
        \Log::info("🏆 PF selezionati: " . collect($this->articoliSelezionati)->where('is_pf', true)->count());
        
        $this->validate();
        
        if (empty($this->articoliSelezionati)) {
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
                    $this->noteMovimentazione,
                    $this->trasportoMezzo,
                    $this->aspettoBeni,
                    $this->colli,
                    $this->vettore
                );

                // Movimenta articoli selezionati
                foreach ($this->articoliSelezionati as $articoloData) {
                    $articolo = Articolo::findOrFail($articoloData['articolo_id']);
                    $quantita = (int) ($articoloData['quantita'] ?? 1);
                    
                    if (!empty($articoloData['is_pf'])) {
                        $pf = $articolo->prodottoFinito()
                            ->withTrashed()
                            ->with('componentiArticoli.articolo')
                            ->first();
                        if (!$pf) {
                            \Log::error('❌ PF non collegato correttamente, fallback a movimentazione articolo', [
                                'articolo_id' => $articolo->id,
                                'articolo_codice' => $articolo->codice,
                                'prodotto_finito_id' => $articolo->prodotto_finito_id,
                                'sede_origine_id' => $this->sedeOrigineId,
                                'sede_destinazione_id' => $this->sedeDestinazioneId,
                            ]);
                        } else {
                        $destCategoriaResult = $this->getPfCategoriaBySede($this->sedeDestinazioneId);
                        if (!$articolo->giacenza || $articolo->giacenza->quantita_residua <= 0) {
                            throw new \Exception("Il PF {$articolo->codice} non ha giacenza disponibile per movimentazione.");
                        }

                        $magazzinoOrigineId = $this->trovaCategoriaOrigineDaSedeCompat($this->sedeOrigineId, $articolo);
                        if ($magazzinoOrigineId <= 0) {
                            throw new \Exception("Categoria origine non valida per articolo {$articolo->id} (sede {$this->sedeOrigineId}).");
                        }
                        $dto = new MovimentazioneDTO(
                            articoloId: $articolo->id,
                            quantita: $articoloData['quantita'] ?? 1,
                            magazzinoOrigineId: $magazzinoOrigineId,
                            magazzinoDestinazioneId: $destCategoriaResult,
                            dataMovimentazione: $this->dataMovimentazione,
                            note: "Spostamento PF {$pf->codice} | {$pf->descrizione}" . ($this->noteMovimentazione ? " - {$this->noteMovimentazione}" : ''),
                            prodottoFinitoId: $pf->id
                        );
                        $movimentazioneService->eseguiMovimentazioneDettaglio($movimentazioneMaster, $dto);
                        $totaleMovimentazioni++;

                        $articolo->update([
                            'sede_id' => $this->sedeDestinazioneId,
                            'categoria_merceologica_id' => $destCategoriaResult,
                            'magazzino_logico' => $this->resolveMagazzinoLogicoForCategoria($destCategoriaResult),
                        ]);
                        $this->syncSedeGiacenza($articolo->id, $this->sedeDestinazioneId);
                        $pf->update(['magazzino_id' => $destCategoriaResult]);

                        foreach ($pf->componentiArticoli as $componente) {
                            $articoloComponente = $componente->articolo;
                            $destCategoria = $this->trovaCategoriaDaSede($this->sedeDestinazioneId, $articoloComponente);

                            $magazzinoOrigineId = $this->trovaCategoriaOrigineDaSedeCompat($this->sedeOrigineId, $articoloComponente);
                            if ($magazzinoOrigineId <= 0) {
                                throw new \Exception("Categoria origine non valida per articolo {$articoloComponente->id} (sede {$this->sedeOrigineId}).");
                            }
                            $dto = new MovimentazioneDTO(
                                articoloId: $articoloComponente->id,
                                quantita: $componente->quantita,
                                magazzinoOrigineId: $magazzinoOrigineId,
                                magazzinoDestinazioneId: $destCategoria,
                                dataMovimentazione: $this->dataMovimentazione,
                                note: "Spostamento componente PF {$pf->codice} | {$pf->descrizione}" . ($this->noteMovimentazione ? " - {$this->noteMovimentazione}" : ''),
                                prodottoFinitoId: $pf->id
                            );
                            $movimentazioneService->eseguiMovimentazioneDettaglio($movimentazioneMaster, $dto);
                            $totaleMovimentazioni++;
                            $articoloComponente->update(['sede_id' => $this->sedeDestinazioneId]);
                            $this->syncSedeGiacenza($articoloComponente->id, $this->sedeDestinazioneId);
                        }

                        continue;
                        }
                    }

                    // Verifica finale prima della movimentazione
                    if ($articolo->isInContoDeposito()) {
                        throw new \Exception("L'articolo {$articolo->codice} è in conto deposito e non può essere movimentato.");
                    }
                    
                    $giacenzaDisponibile = $articolo->giacenza?->quantita_residua ?? ($articolo->giacenza?->quantita ?? 0);
                    if ($quantita < $giacenzaDisponibile) {
                        $articolo = app(ArticoloSplitService::class)->splitArticolo($articolo, $quantita);
                    }

                    $magazzinoOrigineId = $this->trovaCategoriaOrigineDaSedeCompat($this->sedeOrigineId, $articolo);
                    if ($magazzinoOrigineId <= 0) {
                        throw new \Exception("Categoria origine non valida per articolo {$articolo->id} (sede {$this->sedeOrigineId}).");
                    }
                    $dto = new MovimentazioneDTO(
                        articoloId: $articolo->id,
                        quantita: $quantita,
                        magazzinoOrigineId: $magazzinoOrigineId,
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
                    $categoriaDestinazioneId = $this->trovaCategoriaDaSede($this->sedeDestinazioneId, $articolo);
                    $articolo->update([
                        'sede_id' => $this->sedeDestinazioneId,
                        'categoria_merceologica_id' => $categoriaDestinazioneId,
                        'magazzino_logico' => $this->resolveMagazzinoLogicoForCategoria($categoriaDestinazioneId),
                    ]);
                    $this->syncSedeGiacenza($articolo->id, $this->sedeDestinazioneId);
                }
                
                
                // Reset selezioni
                $this->articoliSelezionati = [];
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
        $categoriaOrigine = $articolo->categoriaMerceologica;
        $magazzinoCode = $this->resolveMagazzinoCodeFromCategoria($categoriaOrigine);

        $categoria = CategoriaMerceologica::withoutGlobalScopes()
            ->where('sede_id', $sedeId)
            ->get()
            ->first(function ($candidate) use ($magazzinoCode) {
                return $this->resolveMagazzinoCodeFromCategoria($candidate) === $magazzinoCode;
            });

        if (!$categoria) {
            throw new \Exception(
                "Categoria '{$categoriaOrigine->nome}' non presente nella sede di destinazione."
            );
        }
        
        return $categoria->id;
    }

    private function resolveMagazzinoCodeFromCategoria(?CategoriaMerceologica $categoria): ?int
    {
        if (!$categoria) {
            return null;
        }

        $codice = trim((string) $categoria->codice);
        $nome = trim((string) $categoria->nome);

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

    private function trovaCategoriaOrigineDaSede(int $sedeId, Articolo $articolo): int
    {
        $giacenza = Giacenza::where('articolo_id', $articolo->id)
            ->where('sede_id', $sedeId)
            ->where(function ($q) {
                $q->where('quantita_residua', '>', 0)
                  ->orWhere('quantita', '>', 0);
            })
            ->orderByDesc('quantita_residua')
            ->first();

        if ($giacenza) {
            return (int) $giacenza->categoria_merceologica_id;
        }

        $categoriaId = $this->trovaCategoriaDaSede($sedeId, $articolo);
        if (!$categoriaId) {
            throw new \Exception("Categoria origine non trovata per sede {$sedeId} e articolo {$articolo->id}.");
        }

        return (int) $categoriaId;
    }

    private function trovaCategoriaOrigineDaSedeCompat(int $sedeId, Articolo $articolo): int
    {
        $categoriaCompatibileSede = $this->trovaCategoriaCompatibilePerSede($sedeId, $articolo);

        $giacenza = Giacenza::where('articolo_id', $articolo->id)
            ->where('sede_id', $sedeId)
            ->where(function ($q) {
                $q->where('quantita_residua', '>', 0)
                  ->orWhere('quantita', '>', 0);
            })
            ->orderByDesc('quantita_residua')
            ->first();

        if ($giacenza && !empty($giacenza->categoria_merceologica_id)) {
            return (int) $giacenza->categoria_merceologica_id;
        }

        if ($categoriaCompatibileSede > 0) {
            return $categoriaCompatibileSede;
        }

        $categoriaId = $this->trovaCategoriaDaSede($sedeId, $articolo);
        if (!$categoriaId) {
            throw new \Exception("Categoria origine non trovata per sede {$sedeId} e articolo {$articolo->id}.");
        }

        return (int) $categoriaId;
    }

    private function trovaCategoriaCompatibilePerSede(int $sedeId, Articolo $articolo): int
    {
        $categoriaOrigine = $articolo->categoriaMerceologica;
        $magazzinoCode = $this->resolveMagazzinoCodeFromCategoria($categoriaOrigine);

        if (!$magazzinoCode) {
            return 0;
        }

        $categoria = CategoriaMerceologica::withoutGlobalScopes()
            ->where('sede_id', $sedeId)
            ->get()
            ->first(function ($candidate) use ($magazzinoCode) {
                return $this->resolveMagazzinoCodeFromCategoria($candidate) === $magazzinoCode;
            });

        return $categoria?->id ? (int) $categoria->id : 0;
    }

    private function getPfCategoriaBySede(int $sedeId): int
    {
        return $sedeId === 5 ? 22 : 9;
    }

    private function syncSedeGiacenza(int $articoloId, int $sedeId): void
    {
        $giacenza = Giacenza::where('articolo_id', $articoloId)->first();
        if ($giacenza) {
            $giacenza->update(['sede_id' => $sedeId]);
        }
    }

    private function resolveMagazzinoLogicoForCategoria(?int $categoriaId): ?int
    {
        if (!$categoriaId) {
            return null;
        }

        return app(MagazzinoLogicoService::class)->resolveFromCategoriaId($categoriaId);
    }

    private function labelMagazzinoLogico(?int $magazzinoLogico): string
    {
        if (!$magazzinoLogico) {
            return 'N/D';
        }

        return 'Magazzino ' . $magazzinoLogico;
    }
    
    public function getTotaleSelezionati(): int
    {
        return count($this->articoliSelezionati);
    }
    
    public function render()
    {
        return view('livewire.movimentazione-interna-new', [
            'sedi' => $this->sedi,
            'categorie' => $this->categorie,
            'articoliDisponibili' => $this->articoliDisponibili,
        ]);
    }
}
