<?php

namespace App\Http\Livewire;

use App\Models\Fornitore;
use App\Models\CategoriaMerceologica;
use App\Models\Articolo;
use App\Models\FornitorePrezzo;
use App\Models\Ddt;
use App\Models\Fattura;
use App\Models\Sede;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('layouts.vertical', ['title' => 'Elenco Articoli'])]
class ArticoliTable extends Component
{
    use WithPagination, WithFileUploads;

    // ID componente (Livewire)
    public $id;
    
    // Filtri Avanzati
    public $search = '';
    public $magazzinoFilter = ''; // Filtro singolo per compatibilità
    public $magazziniSelezionati = []; // Filtro multiplo per categorie
    public $showMagazzinoDropdown = false; // Controllo dropdown personalizzato
    public $statoFilter = '';
    public $fornitoreFilter = '';
    public $marcaFilter = '';
    public $ubicazioneFilter = '';
    public $giacenzaFilter = ''; // '', 'giacenti', 'scarichi'
    public $giacenza = ''; // Nuovo parametro per filtri dalla dashboard: 'positiva', 'zero', 'negativa', 'nessuna'
    public $statoArticoloFilter = ''; // '', 'disponibile', 'scaricato'
    public $prezzoMin = '';
    public $prezzoMax = '';
    public $dataDocumentoFrom = '';
    public $dataDocumentoTo = '';
    public $soloVetrina = false;
    public $fotoFilter = ''; // '', 'con', 'senza'
    public $inDepositoFilter = ''; // '', '1'

    // Prezzi fornitori (aggiornamento massivo)
    public $prezziFornitoreId = '';
    public $prezziMatchType = 'referenza';
    public $prezziMatchValue = '';
    public $prezziNuovoPrezzo = '';
    public $prezziSoloSenzaPrezzo = true;
    public $prezziSalvaRegola = true;
    public $prezziPreview = [];
    public $prezziPreviewTotal = 0;
    public $prezziSelezionati = [];
    public $prezziApplicaATutti = false;
    public $prezziPreviewLoaded = false;
    
    // Modalità scarico parziale
    public $showModalScarico = false;
    public $articoloDaScaricare = null;
    public $quantitaDaScaricare = 1;
    public $giacenzaDisponibile = 0;

    // Modalità ricarico quantità
    public $showModalRicarico = false;
    public $articoloDaRicaricare = null;
    public $giacenzaMancante = 0;
    public $quantitaDaRicaricare = 1;
    
    // Modalità stampa etichetta
    public $showModalStampa = false;
    public $articoloDaStampare = null;
    public $prezzoEtichetta = '';
    public $formatoPrezzo = 'euro'; // 'euro' o 'codificato'
    public $prezzoEtichettaFonte = 'fornitore'; // fornitore|vetrina|manuale
    public $layoutEtichetta = 'standard'; // standard|nc_prezzo|nc_prezzo_carati|nc_prezzo_completo
    public $stampanteSelezionata = '';
    public $stampantiDisponibili = [];

    // Modalità modifica articolo
    public $showModalModifica = false;
    public $articoloDaModificare = null;
    public $modifica = [
        'id' => null,
        'codice' => '',
        'descrizione' => '',
        'descrizione_estesa' => '',
        'categoria_merceologica_id' => '',
        'fornitore_id' => '',
        'materiale' => '',
        'colore' => '',
        'peso_lordo' => '',
        'peso_netto' => '',
        'titolo' => '',
        'caratura' => '',
        'prezzo_acquisto' => '',
        'prezzo_fornitore' => '',
        'note' => '',
        'ean' => '',
        'numero_seriale' => '',
        'modello' => '',
        'marca' => '',
        'referenza' => '',
        'in_vetrina' => false,
        'inventariato' => false,
        'visibile_catalogo' => false,
    ];

    // Modalità gestione immagine articolo
    public $showModalFoto = false;
    public $articoloFotoTarget = null;
    public $fotoUpload = null;
    public $mobileUploadUrl = '';
    public $mobileUploadQrBase64 = '';
    public $fotoTargetSnapshot = '';
    
    // Paginazione e ordinamento
    public $perPage = 25;
    public $sortField = 'created_at';
    public $sortDirection = 'desc';

    // Colonne visibili in tabella
    public $visibleColumns = [];
    public $showColumnsDropdown = false;

    // Cache statistiche per evitare ricalcoli costosi durante la ricerca
    public $statsCache = null;

    private function isSearchActive(): bool
    {
        return trim((string) $this->search) !== '';
    }
    
    protected $queryString = [
        'search' => ['except' => ''],
        'magazzinoFilter' => ['except' => ''],
        'magazziniSelezionati' => ['except' => []],
        'statoFilter' => ['except' => ''],
        'fornitoreFilter' => ['except' => ''],
        'marcaFilter' => ['except' => ''],
        'ubicazioneFilter' => ['except' => ''],
        'giacenzaFilter' => ['except' => ''],
        'giacenza' => ['except' => ''], // Nuovo parametro per filtri dalla dashboard
        'statoArticoloFilter' => ['except' => ''],
        'prezzoMin' => ['except' => ''],
        'prezzoMax' => ['except' => ''],
        'dataDocumentoFrom' => ['except' => ''],
        'dataDocumentoTo' => ['except' => ''],
        'soloVetrina' => ['except' => false],
        'fotoFilter' => ['except' => ''],
        'inDepositoFilter' => ['except' => ''],
        'sortField' => ['except' => 'created_at'],
        'sortDirection' => ['except' => 'desc'],
    ];

    // RIMOSSO: Listener JavaScript non più necessario
    // Il dropdown si chiude automaticamente con Livewire

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedMagazzinoFilter()
    {
        $this->resetPage();
    }

    public function updatedMagazziniSelezionati()
    {
        $this->resetPage();
    }

    public function toggleMagazzinoDropdown()
    {
        $this->showMagazzinoDropdown = !$this->showMagazzinoDropdown;
    }

    public function toggleColumnsDropdown()
    {
        $this->showColumnsDropdown = !$this->showColumnsDropdown;
    }

    public function toggleMagazzino($magazzinoId)
    {
        if (in_array($magazzinoId, $this->magazziniSelezionati)) {
            $this->magazziniSelezionati = array_diff($this->magazziniSelezionati, [$magazzinoId]);
        } else {
            $this->magazziniSelezionati[] = $magazzinoId;
        }
        $this->resetPage();
    }

    public function selezionaTuttiMagazzini()
    {
        $magazziniQuery = CategoriaMerceologica::query();
        $userSedeId = auth()->user()?->sede_id;
        if ($userSedeId) {
            $magazziniQuery->where('sede_id', $userSedeId);
        }
        $this->magazziniSelezionati = $magazziniQuery->pluck('id')->toArray();
        $this->resetPage();
    }

    public function deselezionaTuttiMagazzini()
    {
        $this->magazziniSelezionati = [];
        $this->resetPage();
    }

    public function updatedStatoFilter()
    {
        $this->resetPage();
    }

    public function updatedDataDocumentoFrom()
    {
        $this->resetPage();
    }

    public function updatedDataDocumentoTo()
    {
        $this->resetPage();
    }

    public function mount()
    {
        $defaultColumns = [
            'codice' => true,
            'descrizione' => true,
            'specifiche' => true,
            'caratura' => true,
            'giacenza' => true,
            'costo_unitario' => true,
            'prezzo_fornitore' => true,
            'valore_totale' => true,
            'dati_carico' => true,
            'ubicazione' => true,
            'azioni' => true,
        ];

        $this->visibleColumns = session()->get('articoli.visible_columns', $defaultColumns);
    }

    public function updatedVisibleColumns()
    {
        session()->put('articoli.visible_columns', $this->visibleColumns);
    }

    public function resetVisibleColumns()
    {
        $this->visibleColumns = [
            'codice' => true,
            'descrizione' => true,
            'specifiche' => true,
            'caratura' => true,
            'giacenza' => true,
            'costo_unitario' => true,
            'prezzo_fornitore' => true,
            'valore_totale' => true,
            'dati_carico' => true,
            'ubicazione' => true,
            'azioni' => true,
        ];
        session()->put('articoli.visible_columns', $this->visibleColumns);
    }

    public function getColumnOptionsProperty(): array
    {
        return [
            'codice' => 'Codice',
            'descrizione' => 'Descrizione',
            'specifiche' => 'Specifiche',
            'caratura' => 'Caratura',
            'giacenza' => 'Giacenza',
            'costo_unitario' => 'Costo unitario',
            'prezzo_fornitore' => 'Prezzo fornitore',
            'valore_totale' => 'Valore totale',
            'dati_carico' => 'Dati carico',
            'ubicazione' => 'Ubicazione',
            'azioni' => 'Azioni',
        ];
    }

    public function updatedFornitoreFilter()
    {
        $this->resetPage();
    }

    public function updatedMarcaFilter()
    {
        $this->resetPage();
    }

    public function updatedUbicazioneFilter()
    {
        $this->resetPage();
    }

    public function updatedGiacenzaFilter()
    {
        $this->resetPage();
    }

    public function updatedGiacenza()
    {
        $this->resetPage();
    }

    public function updatedPrezzoMin()
    {
        $this->resetPage();
    }

    public function updatedPrezzoMax()
    {
        $this->resetPage();
    }

    public function updatedDataFrom()
    {
        $this->resetPage();
    }

    public function updatedDataTo()
    {
        $this->resetPage();
    }

    public function updatedSoloVetrina()
    {
        $this->resetPage();
    }

    public function updatedFotoFilter()
    {
        $this->resetPage();
    }

    public function updatedSoloInventariati()
    {
        $this->resetPage();
    }
    
    public function updatedPerPage()
    {
        $this->resetPage();
    }
    
    public function sortBy($field)
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }
    
    public function resetFilters()
    {
        $this->search = '';
        $this->magazzinoFilter = '';
        $this->magazziniSelezionati = [];
        $this->showMagazzinoDropdown = false;
        $this->statoFilter = '';
        $this->fornitoreFilter = '';
        $this->marcaFilter = '';
        $this->ubicazioneFilter = '';
        $this->giacenzaFilter = '';
        $this->giacenza = '';
        $this->statoArticoloFilter = '';
        $this->prezzoMin = '';
        $this->prezzoMax = '';
        $this->dataDocumentoFrom = '';
        $this->dataDocumentoTo = '';
        $this->soloVetrina = false;
        $this->fotoFilter = '';
        $this->inDepositoFilter = '';
        $this->sortField = 'created_at';
        $this->sortDirection = 'desc';
        $this->resetPage();
        
        // Emit event per resettare Flatpickr
        $this->dispatch('filters-reset');
        $this->dispatch('close-filters-canvas');
    }
    
    /**
     * Apre modal per scarico parziale o scarica direttamente
     */
    public function scaricaArticolo($articoloId)
    {
        try {
            $articolo = Articolo::findOrFail($articoloId);
            
            // Verifica che l'articolo sia disponibile
            if ($articolo->stato_articolo !== 'disponibile') {
                session()->flash('error', 'Articolo non disponibile per lo scarico');
                return;
            }
            
            $giacenza = $articolo->giacenza->quantita_residua ?? 0;
            
            // Se giacenza = 1, scarico diretto
            if ($giacenza == 1) {
                $this->eseguiScarico($articolo, 1);
            } else {
                // Se giacenza > 1, apri modal per scegliere quantità
                $this->articoloDaScaricare = $articolo;
                $this->giacenzaDisponibile = $giacenza;
                $this->quantitaDaScaricare = 1;
                $this->showModalScarico = true;
            }
            
        } catch (\Exception $e) {
            session()->flash('error', 'Errore durante lo scarico: ' . $e->getMessage());
        }
    }
    
    /**
     * Esegue lo scarico con quantità specificata
     */
    public function eseguiScarico($articolo, $quantita)
    {
        try {
            $giacenzaAttuale = $articolo->giacenza->quantita_residua ?? 0;
            
            if ($quantita > $giacenzaAttuale) {
                session()->flash('error', 'Quantità richiesta superiore alla giacenza disponibile');
                return;
            }
            
            $nuovaGiacenza = $giacenzaAttuale - $quantita;
            
            // Aggiorna giacenza
            $articolo->giacenza()->update([
                'quantita_residua' => $nuovaGiacenza
            ]);
            
            // Se giacenza diventa 0, marca come scaricato
            if ($nuovaGiacenza == 0) {
                $articolo->update([
                    'stato_articolo' => 'scaricato',
                    'scaricato_il' => now(),
                    'scaricato_da' => auth()->id()
                ]);
            }
            
            $messaggio = $nuovaGiacenza == 0 
                ? "Articolo {$articolo->codice} scaricato completamente" 
                : "Scaricati {$quantita} pezzi di {$articolo->codice}. Giacenza residua: {$nuovaGiacenza}";
                
            session()->flash('success', $messaggio);
            
            
        } catch (\Exception $e) {
            session()->flash('error', 'Errore durante lo scarico: ' . $e->getMessage());
        }
    }
    
    /**
     * Conferma scarico parziale dal modal
     */
    public function confermaScaricoParziale()
    {
        if (!$this->articoloDaScaricare || $this->quantitaDaScaricare <= 0) {
            session()->flash('error', 'Dati non validi');
            return;
        }
        
        if ($this->quantitaDaScaricare > $this->giacenzaDisponibile) {
            session()->flash('error', 'Quantità superiore alla giacenza disponibile');
            return;
        }
        
        $this->eseguiScarico($this->articoloDaScaricare, $this->quantitaDaScaricare);
        
        // Chiudi modal
        $this->showModalScarico = false;
        $this->articoloDaScaricare = null;
        $this->quantitaDaScaricare = 1;
        $this->giacenzaDisponibile = 0;
    }
    
    /**
     * Chiudi modal scarico
     */
    public function chiudiModalScarico()
    {
        $this->showModalScarico = false;
        $this->articoloDaScaricare = null;
        $this->quantitaDaScaricare = 1;
        $this->giacenzaDisponibile = 0;
    }

    /**
     * Apre modal per ricaricare quantità scaricate per errore
     */
    public function apriModalRicarico($articoloId)
    {
        try {
            $articolo = Articolo::with('giacenza')->findOrFail($articoloId);
            $quantita = $articolo->giacenza->quantita ?? 0;
            $residua = $articolo->giacenza->quantita_residua ?? 0;
            $mancante = max(0, $quantita - $residua);

            $this->articoloDaRicaricare = $articolo;
            $this->giacenzaMancante = $mancante > 0 ? $mancante : null;
            $this->quantitaDaRicaricare = 1;
            $this->showModalRicarico = true;
        } catch (\Exception $e) {
            session()->flash('error', 'Errore durante l\'apertura del ricarico: ' . $e->getMessage());
        }
    }

    /**
     * Conferma ricarico quantità
     */
    public function confermaRicarico()
    {
        if (!$this->articoloDaRicaricare || $this->quantitaDaRicaricare <= 0) {
            session()->flash('error', 'Dati non validi');
            return;
        }

        if ($this->giacenzaMancante !== null && $this->quantitaDaRicaricare > $this->giacenzaMancante) {
            session()->flash('error', 'Quantità superiore al massimo ripristinabile');
            return;
        }

        try {
            $articolo = Articolo::with('giacenza')->findOrFail($this->articoloDaRicaricare->id);
            if (!$articolo->giacenza) {
                DB::table('giacenze')->insert([
                    'articolo_id' => $articolo->id,
                    'categoria_merceologica_id' => $articolo->categoria_merceologica_id,
                    'sede_id' => $articolo->sede_id,
                    'quantita' => $this->quantitaDaRicaricare,
                    'quantita_residua' => $this->quantitaDaRicaricare,
                    'quantita_deposito' => 0,
                    'costo_unitario' => $articolo->prezzo_acquisto ?? 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $residua = $articolo->giacenza->quantita_residua ?? 0;
                $quantita = $articolo->giacenza->quantita ?? 0;
                $nuovaResidua = $residua + $this->quantitaDaRicaricare;
                $nuovaQuantita = max($quantita, $nuovaResidua);

                $articolo->giacenza()->update([
                    'quantita' => $nuovaQuantita,
                    'quantita_residua' => $nuovaResidua,
                ]);
            }

            $articolo->update([
                'stato_articolo' => 'disponibile',
                'stato' => 'disponibile',
            ]);

            session()->flash('success', "Ripristinati {$this->quantitaDaRicaricare} pezzi di {$articolo->codice}");
            $this->chiudiModalRicarico();
        } catch (\Exception $e) {
            session()->flash('error', 'Errore durante il ripristino: ' . $e->getMessage());
        }
    }

    public function chiudiModalRicarico()
    {
        $this->showModalRicarico = false;
        $this->articoloDaRicaricare = null;
        $this->giacenzaMancante = 0;
        $this->quantitaDaRicaricare = 1;
    }
    
    /**
     * Apre modal per stampa etichetta
     */
    public function apriModalStampa($articoloId)
    {
        try {
            $articolo = Articolo::findOrFail($articoloId);
            $this->articoloDaStampare = $articolo;
            
            $this->prezzoEtichettaFonte = $this->getDefaultPrezzoFonte($articolo);
            $this->impostaPrezzoEtichettaDaFonte($articolo);
            
            // Carica stampanti disponibili per questo articolo
            $this->caricaStampantiDisponibili($articolo);
            
            $this->showModalStampa = true;
            
            // Chiudi dropdown secondo documentazione Livewire 3
            
        } catch (\Exception $e) {
            session()->flash('error', 'Errore durante l\'apertura del modal: ' . $e->getMessage());
        }
    }

    public function updatedPrezzoEtichettaFonte()
    {
        if (!$this->articoloDaStampare) {
            return;
        }
        $this->impostaPrezzoEtichettaDaFonte($this->articoloDaStampare);
    }

    private function getPrezzoVetrinaArticolo(Articolo $articolo): ?string
    {
        $prezzo = $articolo->articoliVetrina()
            ->whereNull('data_rimozione')
            ->orderByDesc('data_inserimento')
            ->value('prezzo_vetrina');

        return $prezzo ? (string) $prezzo : null;
    }

    private function getDefaultPrezzoFonte(Articolo $articolo): string
    {
        if ($articolo->prezzo_fornitore) {
            return 'fornitore';
        }
        if ($this->getPrezzoVetrinaArticolo($articolo)) {
            return 'vetrina';
        }
        return 'manuale';
    }

    private function impostaPrezzoEtichettaDaFonte(Articolo $articolo): void
    {
        if ($this->prezzoEtichettaFonte === 'fornitore' && $articolo->prezzo_fornitore) {
            $this->prezzoEtichetta = number_format($articolo->prezzo_fornitore, 2, ',', '.');
            $this->formatoPrezzo = 'euro';
            return;
        }

        if ($this->prezzoEtichettaFonte === 'vetrina') {
            $prezzoVetrina = $this->getPrezzoVetrinaArticolo($articolo);
            if ($prezzoVetrina) {
                $this->prezzoEtichetta = $prezzoVetrina;
                $this->formatoPrezzo = is_numeric(str_replace(',', '.', preg_replace('/[^\d,.]/', '', $prezzoVetrina)))
                    ? 'euro'
                    : 'codificato';
                return;
            }
        }

        if ($this->prezzoEtichettaFonte === 'manuale') {
            $this->formatoPrezzo = $this->formatoPrezzo ?: 'euro';
            return;
        }

        $this->prezzoEtichetta = '';
        $this->formatoPrezzo = 'euro';
    }
    
    /**
     * Carica stampanti disponibili per l'articolo
     */
    private function caricaStampantiDisponibili($articolo)
    {
        $user = auth()->user();
        
        // Carica tutte le stampanti attive
        $stampanti = \App\Models\Stampante::where('attiva', true)->get();
        
        $this->stampantiDisponibili = $stampanti->filter(function ($stampante) use ($articolo, $user) {
            // Admin/superuser: può stampare su qualsiasi stampante attiva.
            if ($user && method_exists($user, 'isAdmin') && $user->isAdmin()) {
                return true;
            }

            // Utenti standard: rispetta i vincoli stampante per articolo.
            return $stampante->canPrintArticolo($articolo);
        })->map(function ($stampante) {
            return [
                'id' => $stampante->id,
                'nome' => $stampante->nome,
                'modello' => $stampante->modello,
                'ip_address' => $stampante->ip_address
            ];
        })->values()->toArray();
        
        // Seleziona automaticamente la stampante predefinita dell'utente se disponibile
        if ($user && $user->stampante_default_id) {
            $stampanteDefault = collect($this->stampantiDisponibili)->firstWhere('id', $user->stampante_default_id);
            if ($stampanteDefault) {
                $this->stampanteSelezionata = $user->stampante_default_id;
            }
        }
        
        // Se non c'è una stampante predefinita, seleziona la prima disponibile
        if (empty($this->stampanteSelezionata) && !empty($this->stampantiDisponibili)) {
            $this->stampanteSelezionata = $this->stampantiDisponibili[0]['id'];
        }
    }

    /**
     * Chiudi modal stampa
     */
    public function chiudiModalStampa()
    {
        $this->showModalStampa = false;
        $this->articoloDaStampare = null;
        $this->prezzoEtichetta = '';
        $this->formatoPrezzo = 'euro';
        $this->layoutEtichetta = 'standard';
        $this->stampanteSelezionata = '';
        $this->stampantiDisponibili = [];
    }

    public function apriModalModifica($articoloId)
    {
        try {
            $articolo = Articolo::with(['categoriaMerceologica', 'fornitore'])->findOrFail($articoloId);
            $caratteristiche = $articolo->caratteristiche ?? [];
            if (is_string($caratteristiche)) {
                $decoded = json_decode($caratteristiche, true);
                $caratteristiche = is_array($decoded) ? $decoded : [];
            } elseif (!is_array($caratteristiche)) {
                $caratteristiche = [];
            }

            $this->articoloDaModificare = $articolo;
            $this->modifica = [
                'id' => $articolo->id,
                'codice' => $articolo->codice,
                'descrizione' => $articolo->descrizione,
                'descrizione_estesa' => $articolo->descrizione_estesa,
                'categoria_merceologica_id' => $articolo->categoria_merceologica_id,
                'fornitore_id' => $articolo->fornitore_id,
                'materiale' => $articolo->materiale,
                'colore' => $articolo->colore,
                'peso_lordo' => $articolo->peso_lordo,
                'peso_netto' => $articolo->peso_netto,
                'titolo' => $articolo->titolo,
                'caratura' => $articolo->caratura,
                'prezzo_acquisto' => $articolo->prezzo_acquisto,
                'prezzo_fornitore' => $articolo->prezzo_fornitore,
                'note' => $articolo->note,
                'ean' => $articolo->ean,
                'numero_seriale' => $articolo->numero_seriale,
                'modello' => $articolo->modello,
                'marca' => $caratteristiche['marca'] ?? '',
                'referenza' => $caratteristiche['referenza'] ?? '',
                'in_vetrina' => (bool) $articolo->in_vetrina,
                'inventariato' => (bool) $articolo->inventariato,
                'visibile_catalogo' => (bool) $articolo->visibile_catalogo,
            ];

            $this->showModalModifica = true;
        } catch (\Exception $e) {
            session()->flash('error', 'Errore durante l\'apertura modifica: ' . $e->getMessage());
        }
    }

    public function chiudiModalModifica()
    {
        $this->showModalModifica = false;
        $this->articoloDaModificare = null;
        $this->reset('modifica');
    }

    public function apriModalFoto($articoloId)
    {
        try {
            $articolo = Articolo::findOrFail($articoloId);
            $this->articoloFotoTarget = $articolo;
            $this->fotoUpload = null;
            $this->fotoTargetSnapshot = (string) ($articolo->foto_principale ?? '');

            $this->mobileUploadUrl = URL::temporarySignedRoute(
                'articoli.foto.mobile.form',
                now()->addHours(12),
                ['articolo' => $articolo->id]
            );

            $qrCode = new QrCode($this->mobileUploadUrl);
            $writer = new PngWriter();
            $result = $writer->write($qrCode);
            $this->mobileUploadQrBase64 = base64_encode($result->getString());

            $this->showModalFoto = true;
        } catch (\Exception $e) {
            session()->flash('error', 'Errore apertura gestione foto: ' . $e->getMessage());
        }
    }

    public function chiudiModalFoto()
    {
        $this->showModalFoto = false;
        $this->articoloFotoTarget = null;
        $this->fotoUpload = null;
        $this->mobileUploadUrl = '';
        $this->mobileUploadQrBase64 = '';
        $this->fotoTargetSnapshot = '';
        $this->resetErrorBag('fotoUpload');
    }

    public function verificaUploadFotoMobile()
    {
        if (!$this->showModalFoto || !$this->articoloFotoTarget) {
            return;
        }

        try {
            $articolo = Articolo::find($this->articoloFotoTarget->id);
            if (!$articolo) {
                return;
            }

            $currentPath = (string) ($articolo->foto_principale ?? '');
            if ($currentPath !== $this->fotoTargetSnapshot) {
                $this->articoloFotoTarget = $articolo;
                $this->fotoUpload = null;
                $this->fotoTargetSnapshot = $currentPath;

                session()->flash('success', "Foto aggiornata da cellulare per articolo {$articolo->codice}");
                $this->dispatch('foto-mobile-upload-rilevato', codice: $articolo->codice);
                $this->chiudiModalFoto();
            }
        } catch (\Throwable $e) {
            // Polling silenzioso: non bloccare l'utente in caso di errore intermittente.
        }
    }

    public function salvaFotoArticolo()
    {
        if (!$this->articoloFotoTarget) {
            session()->flash('error', 'Nessun articolo selezionato');
            return;
        }

        $this->validate([
            'fotoUpload' => 'required|image|max:10240', // 10MB
        ]);

        try {
            $articolo = Articolo::findOrFail($this->articoloFotoTarget->id);
            $vecchioPath = $articolo->foto_principale;

            $nuovoPath = $this->fotoUpload->store("articoli/{$articolo->id}", 'public');
            $this->ottimizzaImmagineSalvata($nuovoPath);

            $articolo->update([
                'foto_principale' => $nuovoPath,
            ]);

            // Se il vecchio path era locale, rimuovilo.
            if (!empty($vecchioPath) && !str_starts_with($vecchioPath, 'http://') && !str_starts_with($vecchioPath, 'https://')) {
                $normalized = ltrim(str_replace('\\', '/', $vecchioPath), '/');
                if (str_starts_with($normalized, 'storage/')) {
                    $normalized = substr($normalized, 8);
                }
                if (Storage::disk('public')->exists($normalized)) {
                    Storage::disk('public')->delete($normalized);
                }
            }

            $this->articoloFotoTarget = $articolo->fresh();
            $this->fotoUpload = null;
            $this->fotoTargetSnapshot = (string) ($this->articoloFotoTarget->foto_principale ?? '');
            session()->flash('success', "Foto aggiornata per articolo {$articolo->codice}");
        } catch (\Exception $e) {
            session()->flash('error', 'Errore upload foto: ' . $e->getMessage());
        }
    }

    private function ottimizzaImmagineSalvata(string $relativePath): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            return;
        }

        $disk = Storage::disk('public');
        if (!$disk->exists($relativePath)) {
            return;
        }

        $absolutePath = $disk->path($relativePath);
        $imageInfo = @getimagesize($absolutePath);
        if ($imageInfo === false) {
            return;
        }

        [$width, $height, $imageType] = $imageInfo;
        $maxSide = 1920;
        $fileSize = @filesize($absolutePath) ?: 0;

        $shouldResize = $width > $maxSide || $height > $maxSide;
        $shouldCompress = $fileSize > (2 * 1024 * 1024);

        if (!$shouldResize && !$shouldCompress) {
            return;
        }

        $createFn = match ($imageType) {
            IMAGETYPE_JPEG => 'imagecreatefromjpeg',
            IMAGETYPE_PNG => 'imagecreatefrompng',
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? 'imagecreatefromwebp' : null,
            IMAGETYPE_GIF => 'imagecreatefromgif',
            default => null,
        };

        if (!$createFn || !function_exists($createFn)) {
            return;
        }

        $source = @$createFn($absolutePath);
        if (!$source) {
            return;
        }

        $targetWidth = $width;
        $targetHeight = $height;
        if ($shouldResize) {
            $ratio = min($maxSide / $width, $maxSide / $height);
            $targetWidth = max(1, (int) round($width * $ratio));
            $targetHeight = max(1, (int) round($height * $ratio));
        }

        $target = imagecreatetruecolor($targetWidth, $targetHeight);
        if (in_array($imageType, [IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF], true)) {
            imagealphablending($target, false);
            imagesavealpha($target, true);
            $transparent = imagecolorallocatealpha($target, 0, 0, 0, 127);
            imagefilledrectangle($target, 0, 0, $targetWidth, $targetHeight, $transparent);
        }

        imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        match ($imageType) {
            IMAGETYPE_JPEG => imagejpeg($target, $absolutePath, 82),
            IMAGETYPE_PNG => imagepng($target, $absolutePath, 7),
            IMAGETYPE_WEBP => function_exists('imagewebp') ? imagewebp($target, $absolutePath, 82) : null,
            IMAGETYPE_GIF => imagegif($target, $absolutePath),
            default => null,
        };

        imagedestroy($source);
        imagedestroy($target);
    }

    public function eliminaFotoArticolo()
    {
        if (!$this->articoloFotoTarget) {
            session()->flash('error', 'Nessun articolo selezionato');
            return;
        }

        try {
            $articolo = Articolo::findOrFail($this->articoloFotoTarget->id);
            $path = $articolo->foto_principale;

            if (!empty($path) && !str_starts_with($path, 'http://') && !str_starts_with($path, 'https://')) {
                $normalized = ltrim(str_replace('\\', '/', $path), '/');
                if (str_starts_with($normalized, 'storage/')) {
                    $normalized = substr($normalized, 8);
                }
                if (Storage::disk('public')->exists($normalized)) {
                    Storage::disk('public')->delete($normalized);
                }
            }

            $articolo->update(['foto_principale' => null]);
            $this->articoloFotoTarget = $articolo->fresh();
            $this->fotoUpload = null;
            $this->fotoTargetSnapshot = '';

            session()->flash('success', "Foto rimossa per articolo {$articolo->codice}");
        } catch (\Exception $e) {
            session()->flash('error', 'Errore eliminazione foto: ' . $e->getMessage());
        }
    }

    public function salvaModificaArticolo()
    {
        if (!$this->articoloDaModificare) {
            session()->flash('error', 'Nessun articolo selezionato');
            return;
        }

        $this->validate([
            'modifica.descrizione' => 'required|string|max:255',
            'modifica.descrizione_estesa' => 'nullable|string',
            'modifica.categoria_merceologica_id' => 'required|exists:categorie_merceologiche,id',
            'modifica.fornitore_id' => 'nullable|exists:fornitori,id',
            'modifica.materiale' => 'nullable|string|max:100',
            'modifica.colore' => 'nullable|string|max:100',
            'modifica.peso_lordo' => 'nullable',
            'modifica.peso_netto' => 'nullable',
            'modifica.titolo' => 'nullable|string|max:50',
            'modifica.caratura' => 'nullable|string|max:50',
            'modifica.prezzo_fornitore' => 'nullable',
            'modifica.note' => 'nullable|string',
            'modifica.ean' => 'nullable|string|max:60',
            'modifica.numero_seriale' => 'nullable|string|max:100',
            'modifica.modello' => 'nullable|string|max:100',
            'modifica.marca' => 'nullable|string|max:100',
            'modifica.referenza' => 'nullable|string|max:100',
            'modifica.in_vetrina' => 'boolean',
            'modifica.inventariato' => 'boolean',
            'modifica.visibile_catalogo' => 'boolean',
        ]);

        try {
            $articolo = Articolo::findOrFail($this->articoloDaModificare->id);

            $prezzoFornitore = $this->normalizePrezzo($this->modifica['prezzo_fornitore'] ?? null);
            $pesoLordo = $this->normalizePrezzo($this->modifica['peso_lordo'] ?? null);
            $pesoNetto = $this->normalizePrezzo($this->modifica['peso_netto'] ?? null);

            if (($this->modifica['prezzo_fornitore'] ?? '') !== '' && $prezzoFornitore === null) {
                session()->flash('error', 'Prezzo fornitore non valido.');
                return;
            }
            if (($this->modifica['peso_lordo'] ?? '') !== '' && $pesoLordo === null) {
                session()->flash('error', 'Peso lordo non valido.');
                return;
            }
            if (($this->modifica['peso_netto'] ?? '') !== '' && $pesoNetto === null) {
                session()->flash('error', 'Peso netto non valido.');
                return;
            }

            $caratteristiche = $articolo->caratteristiche ?? [];
            if (is_string($caratteristiche)) {
                $decoded = json_decode($caratteristiche, true);
                $caratteristiche = is_array($decoded) ? $decoded : [];
            } elseif (!is_array($caratteristiche)) {
                $caratteristiche = [];
            }
            $marca = trim((string) ($this->modifica['marca'] ?? ''));
            $referenza = trim((string) ($this->modifica['referenza'] ?? ''));
            if ($marca !== '') {
                $caratteristiche['marca'] = $marca;
            } else {
                unset($caratteristiche['marca']);
            }
            if ($referenza !== '') {
                $caratteristiche['referenza'] = $referenza;
            } else {
                unset($caratteristiche['referenza']);
            }

            $articolo->update([
                'descrizione' => $this->modifica['descrizione'],
                'descrizione_estesa' => $this->modifica['descrizione_estesa'] ?: null,
                'categoria_merceologica_id' => $this->modifica['categoria_merceologica_id'],
                'fornitore_id' => $this->modifica['fornitore_id'] ?: null,
                'materiale' => $this->modifica['materiale'] ?: null,
                'colore' => $this->modifica['colore'] ?: null,
                'peso_lordo' => $pesoLordo,
                'peso_netto' => $pesoNetto,
                'titolo' => $this->modifica['titolo'] ?: null,
                'caratura' => $this->modifica['caratura'] ?: null,
                'prezzo_fornitore' => $prezzoFornitore,
                'note' => $this->modifica['note'] ?: null,
                'ean' => $this->modifica['ean'] ?: null,
                'numero_seriale' => $this->modifica['numero_seriale'] ?: null,
                'modello' => $this->modifica['modello'] ?: null,
                'caratteristiche' => empty($caratteristiche) ? null : $caratteristiche,
                'in_vetrina' => (bool) ($this->modifica['in_vetrina'] ?? false),
                'inventariato' => (bool) ($this->modifica['inventariato'] ?? false),
                'visibile_catalogo' => (bool) ($this->modifica['visibile_catalogo'] ?? false),
            ]);

            $fornitoreId = $this->modifica['fornitore_id'] ?: null;
            if ($fornitoreId) {
                $ddtIds = $articolo->ddtDettaglio()
                    ->whereNotNull('ddt_id')
                    ->pluck('ddt_id')
                    ->unique()
                    ->filter()
                    ->values();

                if ($ddtIds->isNotEmpty()) {
                    Ddt::whereIn('id', $ddtIds)->update([
                        'fornitore_id' => $fornitoreId,
                    ]);
                }

                $fatturaIds = $articolo->fatturaDettaglio()
                    ->whereNotNull('fattura_id')
                    ->pluck('fattura_id')
                    ->unique()
                    ->filter()
                    ->values();

                if ($fatturaIds->isNotEmpty()) {
                    Fattura::whereIn('id', $fatturaIds)->update([
                        'fornitore_id' => $fornitoreId,
                    ]);
                }
            }

            $this->statsCache = null;
            session()->flash('success', "Articolo {$articolo->codice} aggiornato con successo");
            $this->chiudiModalModifica();
        } catch (\Exception $e) {
            session()->flash('error', 'Errore durante il salvataggio: ' . $e->getMessage());
        }
    }
    
    /**
     * Conferma stampa etichetta
     */
    public function confermaStampaEtichetta()
    {
        if (!$this->articoloDaStampare) {
            session()->flash('error', 'Nessun articolo selezionato');
            return;
        }
        
        if (empty($this->prezzoEtichetta)) {
            session()->flash('error', 'Il prezzo è obbligatorio');
            return;
        }
        
        if (empty($this->stampanteSelezionata)) {
            session()->flash('error', 'Seleziona una stampante');
            return;
        }
        
        try {
            // Prepara i dati per la stampa
            $datiStampa = [
                'articolo_id' => $this->articoloDaStampare->id,
                'prezzo' => $this->prezzoEtichetta,
                'formato_prezzo' => $this->formatoPrezzo,
                'layout' => $this->layoutEtichetta,
                'stampante_id' => $this->stampanteSelezionata
            ];
            
            // Chiama il controller di stampa
            $response = app(\App\Http\Controllers\StampaController::class)
                ->stampaEtichettaConPrezzo($datiStampa);
            
            if ($response['success']) {
                session()->flash('success', "Etichetta stampata con successo per l'articolo {$this->articoloDaStampare->codice}");
            } else {
                session()->flash('error', $response['message'] ?? 'Errore durante la stampa');
            }
            
            // Chiudi modal
            $this->chiudiModalStampa();
            
        } catch (\Exception $e) {
            session()->flash('error', 'Errore durante la stampa: ' . $e->getMessage());
        }
    }
    
    /**
     * Ripristina un articolo scaricato
     */
    public function ripristinaArticolo($articoloId)
    {
        try {
            $articolo = Articolo::findOrFail($articoloId);
            
            // Verifica che l'articolo sia scaricato
            if ($articolo->stato_articolo !== 'scaricato') {
                session()->flash('error', 'Articolo non è in stato scaricato');
                return;
            }
            
            // Ripristina stato articolo
            $articolo->update([
                'stato_articolo' => 'disponibile',
                'scaricato_il' => null,
                'scaricato_da' => null
            ]);
            
            // Ripristina giacenza originale
            $articolo->giacenza()->update([
                'quantita_residua' => $articolo->giacenza->quantita
            ]);
            
            session()->flash('success', "Articolo {$articolo->codice} ripristinato con successo");
            
            // Chiudi dropdown secondo documentazione Livewire 3
            
        } catch (\Exception $e) {
            session()->flash('error', 'Errore durante il ripristino: ' . $e->getMessage());
        }
    }

    /**
     * Crea una query con tutti i filtri applicati (senza paginazione)
     */
    private function getFilteredQuery()
    {
        $query = Articolo::query();

        // Applica tutti i filtri (stessa logica del render)
        $search = trim((string) $this->search);
        if ($search !== '') {
            $searchTerm = '%' . $search . '%';
            $query->where(function($q) use ($searchTerm, $search) {
                // Ricerca veloce su campi principali
                $q->where('articoli.codice', 'like', $searchTerm)
                  ->orWhere('articoli.codice_base', 'like', $searchTerm)
                  ->orWhere('articoli.descrizione', 'like', $searchTerm);

                // Ricerca estesa solo se il termine e' abbastanza lungo
                if (mb_strlen($search) >= 3) {
                    $q->orWhere('articoli.descrizione_estesa', 'like', $searchTerm)
                      ->orWhere('articoli.numero_documento_carico', 'like', $searchTerm)
                      ->orWhere('articoli.materiale', 'like', $searchTerm)
                      ->orWhere('articoli.colore', 'like', $searchTerm)
                      ->orWhere('articoli.numero_seriale', 'like', $searchTerm)
                      ->orWhere('articoli.ean', 'like', $searchTerm)
                      ->orWhere('articoli.modello', 'like', $searchTerm)
                      ->orWhereRaw("JSON_EXTRACT(articoli.caratteristiche, '$.referenza') LIKE ?", [$searchTerm])
                      ->orWhereRaw("JSON_EXTRACT(articoli.caratteristiche, '$.marca') LIKE ?", [$searchTerm])
                      ->orWhereHas('categoriaMerceologica', function($subQ) use ($searchTerm) {
                          $subQ->where('nome', 'like', $searchTerm)
                               ->orWhere('codice', 'like', $searchTerm);
                      })
                      ->orWhereHas('ddtDettaglio.ddt.fornitore', function($subQ) use ($searchTerm) {
                          $subQ->where('ragione_sociale', 'like', $searchTerm);
                      });
                }
            });
        }

        if (!empty($this->magazziniSelezionati)) {
            $this->applyMagazzinoFilter($query, $this->magazziniSelezionati);
        } elseif ($this->magazzinoFilter) {
            $this->applyMagazzinoFilter($query, [$this->magazzinoFilter]);
        }

        if ($this->statoFilter) {
            $query->where('articoli.stato', $this->statoFilter);
        }

        if ($this->fornitoreFilter) {
            $query->where(function ($q) {
                $q->where('articoli.fornitore_id', $this->fornitoreFilter)
                    ->orWhereHas('ddtDettaglio.ddt', function($subQ) {
                        $subQ->where('fornitore_id', $this->fornitoreFilter);
                    })
                    ->orWhereHas('fatturaDettaglio.fattura', function($subQ) {
                        $subQ->where('fornitore_id', $this->fornitoreFilter);
                    });
            });
        }

        if ($this->marcaFilter) {
            $query->whereRaw("JSON_EXTRACT(articoli.caratteristiche, '$.marca') LIKE ?", ['%' . $this->marcaFilter . '%']);
        }

        if ($this->ubicazioneFilter) {
            $query->where(function ($q) {
                $q->whereHas('giacenza', function($subQ) {
                    $subQ->where('sede_id', $this->ubicazioneFilter);
                })->orWhere(function ($subQ) {
                    $subQ->whereNotNull('articoli.conto_deposito_corrente_id')
                        ->where('articoli.quantita_in_deposito', '>', 0)
                        ->whereHas('contoDepositoCorrente', function ($cdQ) {
                            $cdQ->where('sede_destinataria_id', $this->ubicazioneFilter);
                        });
                });
            });
        }

        if ($this->giacenzaFilter) {
            if ($this->giacenzaFilter === 'giacenti') {
                $query->whereHas('giacenza', function($q) {
                    $q->where('quantita_residua', '>', 0);
                });
            } elseif ($this->giacenzaFilter === 'in_produzione') {
                $query->whereHas('giacenza', function($q) {
                    $q->where('quantita_residua', '=', 0);
                })->whereHas('componentiUtilizzatoIn.prodottoFinito', function($q) {
                    $q->where('stato', 'completato');
                });
            } elseif ($this->giacenzaFilter === 'scarichi') {
                $query->whereHas('giacenza', function($q) {
                    $q->where('quantita_residua', '=', 0);
                })->whereDoesntHave('componentiUtilizzatoIn.prodottoFinito', function($q) {
                    $q->where('stato', 'completato');
                });
            }
        }

        if ($this->giacenza) {
            if ($this->giacenza === 'positiva') {
                $query->whereHas('giacenze', function($q) {
                    $q->where('quantita_residua', '>', 0);
                });
            } elseif ($this->giacenza === 'zero') {
                $query->whereHas('giacenze', function($q) {
                    $q->where('quantita_residua', '=', 0);
                });
            } elseif ($this->giacenza === 'negativa') {
                $query->whereHas('giacenze', function($q) {
                    $q->where('quantita_residua', '<', 0);
                });
            } elseif ($this->giacenza === 'nessuna') {
                $query->whereDoesntHave('giacenze');
            }
        }

        if ($this->statoArticoloFilter) {
            $query->where('articoli.stato_articolo', $this->statoArticoloFilter);
        }

        if ($this->prezzoMin) {
            $query->where('articoli.prezzo_acquisto', '>=', $this->prezzoMin);
        }

        if ($this->prezzoMax) {
            $query->where('articoli.prezzo_acquisto', '<=', $this->prezzoMax);
        }

        if ($this->dataDocumentoFrom) {
            $query->where('articoli.data_carico', '>=', $this->dataDocumentoFrom);
        }

        if ($this->dataDocumentoTo) {
            $query->where('articoli.data_carico', '<=', $this->dataDocumentoTo);
        }

        if ($this->soloVetrina) {
            $query->where('articoli.in_vetrina', true);
        }

        if ($this->fotoFilter === 'con') {
            $query->whereNotNull('articoli.foto_principale')
                ->where('articoli.foto_principale', '!=', '');
        } elseif ($this->fotoFilter === 'senza') {
            $query->where(function ($q) {
                $q->whereNull('articoli.foto_principale')
                    ->orWhere('articoli.foto_principale', '=', '');
            });
        }

        if ($this->inDepositoFilter === '1') {
            $query->whereNotNull('articoli.conto_deposito_corrente_id')
                ->where('articoli.quantita_in_deposito', '>', 0);
        }

        return $query;
    }

    /**
     * Calcola il valore totale degli articoli filtrati
     */
    private function calcolaValoreTotale($query)
    {
        // Cloniamo la query e applichiamo il join per il calcolo del valore
        $valoreTotale = (clone $query)
            ->join('giacenze', 'articoli.id', '=', 'giacenze.articolo_id')
            ->whereNotNull('articoli.prezzo_acquisto')
            ->where('giacenze.quantita_residua', '>', 0)
            ->sum(DB::raw('articoli.prezzo_acquisto * giacenze.quantita_residua'));
            
        return $valoreTotale ?? 0;
    }

    private function normalizePrezzo($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        $clean = str_replace(['€', ' '], '', (string) $value);
        $clean = str_replace('.', '', $clean);
        $clean = str_replace(',', '.', $clean);
        if (!is_numeric($clean)) {
            return null;
        }
        return (float) $clean;
    }

    private function buildPrezziQuery()
    {
        $query = Articolo::query();

        if ($this->prezziFornitoreId) {
            $query->where('fornitore_id', $this->prezziFornitoreId);
        }

        $value = trim((string) $this->prezziMatchValue);
        if ($value === '') {
            return $query->whereRaw('1=0');
        }

        switch ($this->prezziMatchType) {
            case 'referenza':
                $query->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(caratteristiche, '$.referenza')) = ?", [$value]);
                break;
            case 'modello':
                $query->where('modello', $value);
                break;
            case 'seriale':
                $query->where('numero_seriale', $value);
                break;
            case 'ean':
                $query->where('ean', $value);
                break;
            case 'codice':
                $query->where(function ($q) use ($value) {
                    $q->where('codice', $value)
                      ->orWhere('codice_base', $value);
                });
                break;
            case 'descrizione':
                $query->where('descrizione', 'like', '%' . $value . '%');
                break;
            default:
                $query->whereRaw('1=0');
        }

        if ($this->prezziSoloSenzaPrezzo) {
            $query->where(function ($q) {
                $q->whereNull('prezzo_fornitore')
                    ->orWhere('prezzo_fornitore', '<=', 0);
            });
        }

        return $query;
    }

    public function aggiornaPreviewPrezzi()
    {
        if (!$this->prezziMatchValue) {
            session()->flash('error', 'Compila criterio e valore per la ricerca.');
            return;
        }

        $query = $this->buildPrezziQuery();
        $this->prezziPreviewTotal = (clone $query)->count();

        $items = $query->orderBy('id')
            ->limit(200)
            ->get([
                'id',
                'codice',
                'descrizione',
                'prezzo_fornitore',
                'prezzo_acquisto',
                'numero_seriale',
                'modello',
                'ean',
                'caratteristiche',
            ]);

        $this->prezziPreview = $items->toArray();
        $this->prezziSelezionati = $items->pluck('id')->toArray();
        $this->prezziApplicaATutti = false;
        $this->prezziPreviewLoaded = true;
    }

    public function toggleSelezionaTuttiPreview()
    {
        if (count($this->prezziSelezionati) === count($this->prezziPreview)) {
            $this->prezziSelezionati = [];
        } else {
            $this->prezziSelezionati = array_column($this->prezziPreview, 'id');
        }
    }

    public function applicaPrezzoFornitore()
    {
        $prezzo = $this->normalizePrezzo($this->prezziNuovoPrezzo);
        if (!$this->prezziMatchValue || $prezzo === null || $prezzo <= 0) {
            session()->flash('error', 'Compila criterio, valore e prezzo valido.');
            return;
        }

        if (!$this->prezziPreviewLoaded) {
            session()->flash('error', 'Prima fai una ricerca con "Cerca" e seleziona gli articoli da aggiornare.');
            return;
        }

        $query = $this->buildPrezziQuery();
        $aggiornati = 0;
        $modificati = 0;

        if (!$this->prezziApplicaATutti) {
            $ids = array_filter($this->prezziSelezionati);
            if (empty($ids)) {
                session()->flash('error', 'Seleziona almeno un articolo da aggiornare.');
                return;
            }
            $query->whereIn('id', $ids);
        }

        $query->select('id', 'prezzo_fornitore')
            ->orderBy('id')
            ->chunkById(200, function ($chunk) use ($prezzo, &$aggiornati, &$modificati) {
                foreach ($chunk as $articolo) {
                    $aggiornati++;
                    if ((float) $articolo->prezzo_fornitore === (float) $prezzo) {
                        continue;
                    }

                    Articolo::where('id', $articolo->id)->update([
                        'prezzo_fornitore' => $prezzo,
                    ]);

                    $modificati++;
                }
            });

        if ($this->prezziSalvaRegola && $this->prezziFornitoreId) {
            FornitorePrezzo::updateOrCreate(
                [
                    'fornitore_id' => $this->prezziFornitoreId,
                    'match_type' => $this->prezziMatchType,
                    'match_value' => trim((string) $this->prezziMatchValue),
                ],
                [
                    'prezzo' => $prezzo,
                ]
            );
        }

        session()->flash('success', "Aggiornati {$modificati} articoli (trovati {$aggiornati}).");
    }

    private function applyMagazzinoFilter($query, array $magazziniIds): void
    {
        $magazziniIds = array_filter($magazziniIds);
        if (empty($magazziniIds)) {
            return;
        }

        $cdIds = CategoriaMerceologica::whereIn('id', $magazziniIds)
            ->where('codice', 'like', 'CD-%')
            ->pluck('id')
            ->toArray();
        $nonCdIds = array_values(array_diff($magazziniIds, $cdIds));

        if (!empty($cdIds) && empty($nonCdIds)) {
            $query->whereIn('articoli.categoria_merceologica_id', $cdIds)
                ->where('articoli.quantita_in_deposito', '>', 0)
                ->whereNotNull('articoli.conto_deposito_corrente_id');
            return;
        }

        if (!empty($cdIds) && !empty($nonCdIds)) {
            $query->where(function ($q) use ($cdIds, $nonCdIds) {
                $q->whereIn('articoli.categoria_merceologica_id', $nonCdIds)
                    ->orWhere(function ($q2) use ($cdIds) {
                        $q2->whereIn('articoli.categoria_merceologica_id', $cdIds)
                            ->where('articoli.quantita_in_deposito', '>', 0)
                            ->whereNotNull('articoli.conto_deposito_corrente_id');
                    });
            });
            return;
        }

        $query->whereIn('articoli.categoria_merceologica_id', $nonCdIds);
    }

    public function render()
    {
        $relations = [
            'categoria',
            'sede',
            'giacenza',
            'categoriaMerceologica',
            'prodottoFinito.componentiArticoli.articolo',
            'contoDepositoCorrente.sedeDestinataria',
        ];

        if (($this->visibleColumns['dati_carico'] ?? true)) {
            $relations[] = 'ddtDettaglio.ddt.fornitore';
            $relations[] = 'fatturaDettaglio.fattura.fornitore';
            $relations[] = 'fornitore';
        }

        $query = Articolo::with($relations);

        // Applica filtri
        $search = trim((string) $this->search);
        if ($search !== '') {
            $searchTerm = '%' . $search . '%';
            $query->where(function($q) use ($searchTerm, $search) {
                $q->where('codice', 'like', $searchTerm)
                  ->orWhere('codice_base', 'like', $searchTerm)
                  ->orWhere('descrizione', 'like', $searchTerm);

                if (mb_strlen($search) >= 3) {
                    $q->orWhere('descrizione_estesa', 'like', $searchTerm)
                      ->orWhere('numero_documento_carico', 'like', $searchTerm)
                      ->orWhere('materiale', 'like', $searchTerm)
                      ->orWhere('colore', 'like', $searchTerm)
                      ->orWhere('numero_seriale', 'like', $searchTerm)
                      ->orWhere('ean', 'like', $searchTerm)
                      ->orWhere('modello', 'like', $searchTerm)
                      ->orWhereRaw("JSON_EXTRACT(caratteristiche, '$.referenza') LIKE ?", [$searchTerm])
                      ->orWhereRaw("JSON_EXTRACT(caratteristiche, '$.marca') LIKE ?", [$searchTerm])
                      ->orWhereHas('categoriaMerceologica', function($subQ) use ($searchTerm) {
                          $subQ->where('nome', 'like', $searchTerm)
                               ->orWhere('codice', 'like', $searchTerm);
                      })
                      ->orWhereHas('ddtDettaglio.ddt.fornitore', function($subQ) use ($searchTerm) {
                          $subQ->where('ragione_sociale', 'like', $searchTerm);
                      });
                }
            });
        }

        // Filtro per categorie (singolo o multiplo)
        if (!empty($this->magazziniSelezionati)) {
            $this->applyMagazzinoFilter($query, $this->magazziniSelezionati);
        } elseif ($this->magazzinoFilter) {
            $this->applyMagazzinoFilter($query, [$this->magazzinoFilter]);
        }

        if ($this->statoFilter) {
            $query->where('stato', $this->statoFilter);
        }

        // Filtro fornitore: campo diretto articolo + fallback documenti carico
        if ($this->fornitoreFilter) {
            $query->where(function ($q) {
                $q->where('fornitore_id', $this->fornitoreFilter)
                    ->orWhereHas('ddtDettaglio.ddt', function($subQ) {
                        $subQ->where('fornitore_id', $this->fornitoreFilter);
                    })
                    ->orWhereHas('fatturaDettaglio.fattura', function($subQ) {
                        $subQ->where('fornitore_id', $this->fornitoreFilter);
                    });
            });
        }

        if ($this->marcaFilter) {
            $query->whereRaw("JSON_EXTRACT(caratteristiche, '$.marca') LIKE ?", ['%' . $this->marcaFilter . '%']);
        }

        if ($this->ubicazioneFilter) {
            // Filtra per sede fisica (giacenza o conto deposito)
            $query->where(function ($q) {
                $q->whereHas('giacenza', function($subQ) {
                    $subQ->where('sede_id', $this->ubicazioneFilter);
                })->orWhere(function ($subQ) {
                    $subQ->whereNotNull('conto_deposito_corrente_id')
                        ->where('quantita_in_deposito', '>', 0)
                        ->whereHas('contoDepositoCorrente', function ($cdQ) {
                            $cdQ->where('sede_destinataria_id', $this->ubicazioneFilter);
                        });
                });
            });
        }

        if ($this->giacenzaFilter) {
            if ($this->giacenzaFilter === 'giacenti') {
                $query->whereHas('giacenza', function($q) {
                    $q->where('quantita_residua', '>', 0);
                });
            } elseif ($this->giacenzaFilter === 'in_produzione') {
                // Articoli con giacenza 0 MA usati in prodotti finiti completati
                $query->whereHas('giacenza', function($q) {
                    $q->where('quantita_residua', '=', 0);
                })->whereHas('componentiUtilizzatoIn.prodottoFinito', function($q) {
                    $q->where('stato', 'completato');
                });
            } elseif ($this->giacenzaFilter === 'scarichi') {
                // Articoli con giacenza 0 E NON usati in prodotti finiti
                $query->whereHas('giacenza', function($q) {
                    $q->where('quantita_residua', '=', 0);
                })->whereDoesntHave('componentiUtilizzatoIn.prodottoFinito', function($q) {
                    $q->where('stato', 'completato');
                });
            }
        }

        // Nuovo filtro per giacenza dalla dashboard
        if ($this->giacenza) {
            if ($this->giacenza === 'positiva') {
                $query->whereHas('giacenze', function($q) {
                    $q->where('quantita_residua', '>', 0);
                });
            } elseif ($this->giacenza === 'zero') {
                $query->whereHas('giacenze', function($q) {
                    $q->where('quantita_residua', '=', 0);
                });
            } elseif ($this->giacenza === 'negativa') {
                $query->whereHas('giacenze', function($q) {
                    $q->where('quantita_residua', '<', 0);
                });
            } elseif ($this->giacenza === 'nessuna') {
                $query->whereDoesntHave('giacenze');
            }
        }

        // Filtro stato articolo (disponibile/scaricato)
        if ($this->statoArticoloFilter) {
            $query->where('stato_articolo', $this->statoArticoloFilter);
        }

        if ($this->prezzoMin) {
            $query->where('prezzo_acquisto', '>=', $this->prezzoMin);
        }

        if ($this->prezzoMax) {
            $query->where('prezzo_acquisto', '<=', $this->prezzoMax);
        }

        if ($this->dataDocumentoFrom) {
            $query->where('data_carico', '>=', $this->dataDocumentoFrom);
        }

        if ($this->dataDocumentoTo) {
            $query->where('data_carico', '<=', $this->dataDocumentoTo);
        }

        if ($this->soloVetrina) {
            $query->where('in_vetrina', true);
        }

        if ($this->fotoFilter === 'con') {
            $query->whereNotNull('foto_principale')
                ->where('foto_principale', '!=', '');
        } elseif ($this->fotoFilter === 'senza') {
            $query->where(function ($q) {
                $q->whereNull('foto_principale')
                    ->orWhere('foto_principale', '=', '');
            });
        }

        if ($this->inDepositoFilter === '1') {
            $query->whereNotNull('conto_deposito_corrente_id')
                ->where('quantita_in_deposito', '>', 0);
        }

        // Applica sorting
        if ($this->sortField === 'codice') {
            $dir = $this->sortDirection === 'asc' ? 'asc' : 'desc';
            $baseExpr = "COALESCE(articoli.codice_base, articoli.codice)";
            $query->orderByRaw("SUBSTRING_INDEX({$baseExpr}, '-', 1) {$dir}")
                ->orderByRaw("CAST(SUBSTRING_INDEX({$baseExpr}, '-', -1) AS UNSIGNED) {$dir}")
                ->orderByRaw("{$baseExpr} {$dir}")
                ->orderBy('articoli.codice', $dir);
        } else {
            $query->orderBy($this->sortField, $this->sortDirection);
        }

        $articoli = $query->paginate($this->perPage);

        // Statistiche DINAMICHE basate sui filtri applicati
        // Statistiche dinamiche: evita ricalcoli pesanti durante la ricerca
        $stats = $this->statsCache;
        if (!$this->isSearchActive()) {
            $baseQuery = $this->getFilteredQuery();
            $stats = [
                'totali' => $baseQuery->count(),
                'con_giacenza' => (clone $baseQuery)
                    ->whereHas('giacenze', function($q) {
                        $q->where('quantita_residua', '>', 0);
                    })->count(),
                'giacenza_zero' => (clone $baseQuery)
                    ->whereHas('giacenze', function($q) {
                        $q->where('quantita_residua', '=', 0);
                    })->count(),
                'giacenza_negativa' => (clone $baseQuery)
                    ->whereHas('giacenze', function($q) {
                        $q->where('quantita_residua', '<', 0);
                    })->count(),
                'senza_giacenze' => (clone $baseQuery)
                    ->whereDoesntHave('giacenze')->count(),
                'in_vetrina' => (clone $baseQuery)->where('in_vetrina', true)->count(),
                'valore_totale' => $this->calcolaValoreTotale($baseQuery),
            ];
            $this->statsCache = $stats;
        } elseif ($stats === null) {
            $stats = [
                'totali' => $articoli->total(),
                'con_giacenza' => 0,
                'giacenza_zero' => 0,
                'giacenza_negativa' => 0,
                'senza_giacenze' => 0,
                'in_vetrina' => 0,
                'valore_totale' => 0,
            ];
        }

        // Opzioni per i filtri - TUTTE le categorie attive con count articoli
        $magazziniQuery = CategoriaMerceologica::where('attivo', true)
            ->orderBy('id');
        $userSedeId = auth()->user()?->sede_id;
        if ($userSedeId) {
            $magazziniQuery->where('sede_id', $userSedeId);
        }
        if (!$this->isSearchActive()) {
            $magazziniQuery->withCount('articoli');
        }
        $magazzini = $magazziniQuery->get();
        
        // Opzioni per filtri avanzati
        $fornitori = Fornitore::where('attivo', true)
            ->orderBy('ragione_sociale')
            ->get(['id', 'ragione_sociale']);
        
        // Marche estratte da JSON caratteristiche
        $marche = collect();
        if (!$this->isSearchActive()) {
            $marche = DB::table('articoli')
                ->whereNotNull('caratteristiche')
                ->whereRaw("JSON_EXTRACT(caratteristiche, '$.marca') IS NOT NULL")
                ->selectRaw("DISTINCT JSON_UNQUOTE(JSON_EXTRACT(caratteristiche, '$.marca')) as marca")
                ->orderBy('marca')
                ->pluck('marca')
                ->filter()
                ->values();
        }
        
        // Sedi per filtro (ex-ubicazioni)
        $sedi = Sede::orderBy('nome')->get();

        return view('livewire.articoli-table', compact('articoli', 'stats', 'magazzini', 'fornitori', 'marche', 'sedi'));
    }
}