<?php

namespace App\Http\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use App\Models\ContoDeposito;
use App\Models\Articolo;
use App\Models\ProdottoFinito;
use App\Models\ProformaDeposito;
use App\Services\ContoDepositoService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * GestisciContoDeposito - Gestione singolo conto deposito
 * 
 * Permette di:
 * - Visualizzare dettagli deposito
 * - Aggiungere articoli/PF al deposito
 * - Registrare vendite
 * - Gestire resi
 */
class GestisciContoDeposito extends Component
{
    use WithPagination, WithFileUploads;

    public $depositoId;
    public $deposito;

    // Modali
    public $showAggiungiArticoliModal = false;
    public $showRegistraVenditaModal = false;
    public $showResoManualeModal = false;
    public $showAnnullaDdtInvioModal = false;
    
    // Form reso manuale
    public $articoliSelezionatiReso = [];
    public $prodottiFinitiSelezionatiReso = [];
    
    // Modal generazione DDT reso
    public $showGeneraDdtResoModal = false;

    // Dati DDT (invio/reso)
    public $ddtCausale = '';
    public $ddtNumeroColli = '';
    public $ddtCorriere = '';
    public $ddtNumeroTracking = '';
    public $ddtTrasportoMezzo = '';
    public $ddtAspettoBeni = '';
    public $ddtNote = '';

    // Form aggiunta articoli
    public $search = '';
    public $tipoItem = 'articoli'; // 'articoli' o 'prodotti_finiti'
    public $articoliSelezionati = [];
    public $prodottiFinitiSelezionati = [];

    // Form vendita
    public $itemVendita = null;
    public $itemVenditaTipo = null;
    public $itemVenditaId = null;
    public $quantitaVendita = 1;
    
    // Form vendita multipla
    public $showVenditaMultiplaModal = false;
    public $articoliSelezionatiVendita = [];
    public $prodottiFinitiSelezionatiVendita = [];
    
    // Dati proforma deposito (condivisi tra vendita singola e multipla)
    public $numeroProforma = '';
    public $dataProforma = '';
    public $clienteNome = '';
    public $clienteCognome = '';
    public $clienteTelefono = '';
    public $clienteEmail = '';
    public $importoTotaleProforma = 0;
    public $noteProforma = '';

    // Gestione fattura definitiva (documento fiscale)
    public $showSegnaFatturataModal = false;
    public $proformaSelezionataId = null;
    public $fatturaPdf;
    public $fatturaNumero = '';
    public $fatturaData = '';
    public $fatturaNote = '';

    // Rinnovo
    public $showRinnovoModal = false;
    public $rinnovoModalita = 'rimanenti';

    // Anteprima invio
    public $showAnteprimaInvioModal = false;

    // ==========================================
    // DERIVED PROPERTIES (ACCESS CONTROLS)
    // ==========================================

    public function getUserSedeIdProperty(): ?int
    {
        return Auth::user()?->sede_id;
    }

    public function getIsSuperAdminProperty(): bool
    {
        $user = Auth::user();
        if (!$user) {
            return false;
        }
        if (method_exists($user, 'hasAnyRole')) {
            return $user->hasAnyRole(['admin', 'amministrazione']);
        }
        if (method_exists($user, 'hasRole')) {
            return $user->hasRole('admin') || $user->hasRole('amministrazione');
        }
        return false;
    }

    public function getIsMittenteProperty(): bool
    {
        if ($this->isSuperAdmin) {
            return true;
        }
        return $this->userSedeId && $this->deposito && $this->userSedeId === $this->deposito->sede_mittente_id;
    }

    public function getIsDestinatarioProperty(): bool
    {
        if ($this->isSuperAdmin) {
            return true;
        }
        return $this->userSedeId && $this->deposito && $this->userSedeId === $this->deposito->sede_destinataria_id;
    }

    public function getPuoGestireMittenteProperty(): bool
    {
        if (!$this->deposito) {
            return false;
        }
        $user = Auth::user();
        if (!$user) {
            return false;
        }

        $ddtInvio = null;
        if ($this->deposito->ddt_invio_id) {
            $ddtInvio = \App\Models\DdtDeposito::withTrashed()->find($this->deposito->ddt_invio_id);
        }
        $ddtInvioBloccante = $ddtInvio && !$ddtInvio->trashed();

        return $this->deposito->stato === 'attivo'
            && !$ddtInvioBloccante
            && $user->can('conti_deposito.manage')
            && ($this->isMittente || $this->isSuperAdmin);
    }

    public function getPuoAnnullareDdtInvioProperty(): bool
    {
        if (!$this->deposito || !$this->deposito->ddtInvio) {
            return false;
        }
        $user = Auth::user();
        if (!$user) {
            return false;
        }
        $stato = $this->deposito->ddtInvio->stato ?? null;
        return $this->deposito->stato === 'attivo'
            && in_array($stato, ['creato', 'stampato'])
            && $user->can('conti_deposito.manage')
            && ($this->isMittente || $this->isSuperAdmin);
    }

    public function getPuoGestireDestinatarioProperty(): bool
    {
        if (!$this->deposito) {
            return false;
        }
        $user = Auth::user();
        if (!$user) {
            return false;
        }

        if ($this->deposito->stato === 'chiuso') {
            return false;
        }

        $haPermesso = $user->can('conti_deposito.manage') || $user->can('conti_deposito.resi');

        return $haPermesso
            && ($this->isDestinatario || $this->isSuperAdmin);
    }

    public function getPuoRinnovareProperty(): bool
    {
        if (!$this->deposito) {
            return false;
        }
        $user = Auth::user();
        if (!$user) {
            return false;
        }

        if ($this->deposito->stato === 'chiuso') {
            return false;
        }

        $haPermesso = $user->can('conti_deposito.manage') || $user->can('conti_deposito.resi');

        return $haPermesso
            && ($this->isMittente || $this->isDestinatario || $this->isSuperAdmin);
    }

    public function getHaContenutoDepositoProperty(): bool
    {
        return $this->articoliInDeposito->isNotEmpty() || $this->prodottiFinitiInDeposito->isNotEmpty();
    }

    protected $rules = [
        'articoliSelezionati.*.quantita' => 'required|integer|min:1',
        'quantitaVendita' => 'required|integer|min:1',

        // Regole proforma deposito - OBBLIGATORIE per tutte le vendite
        'numeroProforma' => 'required|string|max:50',
        'dataProforma' => 'required|date',
        'clienteNome' => 'required|string|max:100',
        'clienteCognome' => 'required|string|max:100',
        'clienteTelefono' => 'nullable|string|max:20',
        'clienteEmail' => 'nullable|email|max:100',
        'importoTotaleProforma' => 'nullable|numeric|min:0.01', // Opzionale: calcolato automaticamente se vuoto
        'noteProforma' => 'nullable|string|max:500',

        // Selezioni - OPZIONALI
        'articoliSelezionatiVendita' => 'nullable|array',
        'prodottiFinitiSelezionatiVendita' => 'nullable|array',
    ];

    public function mount($depositoId)
    {
        $user = Auth::user();
        if (!$user || !$user->can('conti_deposito.view')) {
            abort(403);
        }
        $this->depositoId = $depositoId;
        $this->deposito = ContoDeposito::with(['ddtResi.dettagli', 'ddtInvio', 'ddtRimando',
            'sedeMittente', 
            'sedeDestinataria',
            'movimenti.articolo.giacenza',
            'movimenti.prodottoFinito',
            'movimentiVendita.proforma',
            'proforme',
            'creatoDa'
        ])->findOrFail($depositoId);
        
        // Inizializza data proforma e fattura definitiva
        $this->dataProforma = now()->format('Y-m-d');
        $this->fatturaData = now()->format('Y-m-d');
    }

    // ==========================================
    // COMPUTED PROPERTIES
    // ==========================================

    public function getArticoliDisponibiliProperty()
    {
        if (!$this->showAggiungiArticoliModal || $this->tipoItem !== 'articoli') {
            return collect();
        }

        $sedeMittenteId = $this->deposito->sede_mittente_id;

        return Articolo::with(['categoriaMerceologica', 'sede', 'giacenza'])
            ->where(function ($query) use ($sedeMittenteId) {
                $query->where('sede_id', $sedeMittenteId)
                      ->orWhereHas('giacenzePerSede', function ($sub) use ($sedeMittenteId) {
                          $sub->where('sede_id', $sedeMittenteId)
                              ->where('quantita_residua', '>', 0);
                      });
            })
            ->where(function ($query) use ($sedeMittenteId) {
                $query->whereHas('giacenza', function ($sub) {
                    $sub->where('quantita_residua', '>', 0);
                })
                ->orWhereHas('giacenzePerSede', function ($sub) use ($sedeMittenteId) {
                    $sub->where('sede_id', $sedeMittenteId)
                        ->where('quantita_residua', '>', 0);
                });
            })
            ->where(function ($query) {
                $query->whereNull('conto_deposito_corrente_id')
                      ->orWhere('quantita_in_deposito', 0)
                      ->orWhereNull('quantita_in_deposito');
            })
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('codice', 'like', '%' . $this->search . '%')
                      ->orWhere('descrizione', 'like', '%' . $this->search . '%');
                });
            })
            ->orderBy('codice')
            ->limit(50)
            ->get();
    }

    public function getProdottiFinitiDisponibiliProperty()
    {
        if (!$this->showAggiungiArticoliModal || $this->tipoItem !== 'prodotti_finiti') {
            return collect();
        }

        return ProdottoFinito::with(['categoriaMerceologica'])
            ->where('stato', 'completato')
            ->where('in_conto_deposito', false)
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('codice', 'like', '%' . $this->search . '%')
                      ->orWhere('descrizione', 'like', '%' . $this->search . '%');
                });
            })
            ->orderBy('codice')
            ->limit(50)
            ->get();
    }

    public function getArticoliInDepositoProperty()
    {
        $service = new ContoDepositoService();
        return $service->getArticoliRimanentiInDeposito($this->deposito);
    }

    public function getProdottiFinitiInDepositoProperty()
    {
        $service = new ContoDepositoService();
        return $service->getProdottiFinitiRimanentiInDeposito($this->deposito);
    }

    // ==========================================
    // ACTIONS - GESTIONE ARTICOLI
    // ==========================================

    public function apriAggiungiArticoliModal()
    {
        if (!$this->puoGestireMittente) {
            session()->flash('error', 'Solo la sede mittente può aggiungere articoli prima della generazione del DDT di invio.');
            return;
        }
        $this->reset(['search', 'articoliSelezionati', 'prodottiFinitiSelezionati']);
        $this->tipoItem = 'articoli';
        $this->showAggiungiArticoliModal = true;
    }

    public function chiudiAggiungiArticoliModal()
    {
        $this->showAggiungiArticoliModal = false;
        $this->resetValidation();
    }

    public function apriAnteprimaInvioModal()
    {
        if (!$this->puoGestireMittente) {
            session()->flash('error', 'Solo la sede mittente può visualizzare l\'anteprima del DDT.');
            return;
        }

        if (!$this->haContenutoDeposito) {
            session()->flash('error', 'Aggiungi almeno un articolo o prodotto finito prima di generare l\'anteprima.');
            return;
        }

        if ($this->ddtCausale === '') {
            $this->ddtCausale = 'Conto deposito';
        }
        $this->showAnteprimaInvioModal = true;
    }

    public function chiudiAnteprimaInvioModal()
    {
        $this->showAnteprimaInvioModal = false;
        $this->resetValidation();
    }

    public function apriAnnullaDdtInvioModal()
    {
        if (!$this->puoAnnullareDdtInvio) {
            session()->flash('error', 'Non hai i permessi per annullare il DDT di invio.');
            return;
        }

        $ddtInvio = $this->deposito?->ddtInvio;
        if (!$ddtInvio) {
            session()->flash('error', 'Nessun DDT di invio da annullare.');
            return;
        }

        if (!in_array($ddtInvio->stato, ['creato', 'stampato'])) {
            session()->flash('error', 'Il DDT non è annullabile nello stato attuale.');
            return;
        }

        $this->showAnnullaDdtInvioModal = true;
    }

    public function chiudiAnnullaDdtInvioModal()
    {
        $this->showAnnullaDdtInvioModal = false;
    }

    public function annullaDdtInvio()
    {
        if (!$this->puoAnnullareDdtInvio) {
            session()->flash('error', 'Non hai i permessi per annullare il DDT di invio.');
            return;
        }

        $ddtInvio = $this->deposito?->ddtInvio;
        if (!$ddtInvio) {
            session()->flash('error', 'Nessun DDT di invio da annullare.');
            return;
        }

        if (!in_array($ddtInvio->stato, ['creato', 'stampato'])) {
            session()->flash('error', 'Il DDT non è annullabile nello stato attuale.');
            return;
        }

        try {
            DB::transaction(function () use ($ddtInvio) {
                $ddtInvio->dettagli()->delete();
                $ddtInvio->forceDelete();
                \App\Models\ContoDeposito::withoutGlobalScopes()
                    ->where('id', $this->deposito->id)
                    ->update(['ddt_invio_id' => null]);
            });

            $this->deposito->refresh();
            $this->showAnnullaDdtInvioModal = false;
            session()->flash('success', 'DDT di invio annullato. Ora puoi aggiungere articoli e rigenerare il DDT.');
        } catch (\Exception $e) {
            session()->flash('error', 'Errore durante l\'annullamento: ' . $e->getMessage());
        }
    }

    public function toggleArticolo($articoloId)
    {
        if (isset($this->articoliSelezionati[$articoloId])) {
            unset($this->articoliSelezionati[$articoloId]);
        } else {
            $articolo = Articolo::with('giacenza')->find($articoloId);
            $this->articoliSelezionati[$articoloId] = [
                'articolo_id' => $articoloId,
                'quantita' => 1,
                'max_quantita' => $articolo->getQuantitaDisponibile(),
                'costo_unitario' => $articolo->prezzo_acquisto ?? 0
            ];
        }
    }

    public function toggleProdottoFinito($pfId)
    {
        if (isset($this->prodottiFinitiSelezionati[$pfId])) {
            unset($this->prodottiFinitiSelezionati[$pfId]);
        } else {
            $pf = ProdottoFinito::find($pfId);
            $this->prodottiFinitiSelezionati[$pfId] = [
                'prodotto_finito_id' => $pfId,
                'costo_unitario' => $pf->costo_totale ?? 0
            ];
        }
    }

    public function aggiungiArticoliAlDeposito()
    {
        if (!$this->puoGestireMittente) {
            session()->flash('error', 'Deposito bloccato: non è possibile aggiungere articoli dopo l\'invio.');
            return;
        }
        // Validazione specifica per aggiunta articoli (solo quantità degli articoli selezionati)
        $this->validate([
            'articoliSelezionati.*.quantita' => 'required|integer|min:1',
        ]);

        try {
            $service = new ContoDepositoService();
            $articoliAggiunti = 0;

            // Aggiungi articoli selezionati
            foreach ($this->articoliSelezionati as $articoloData) {
                $service->inviaArticoloInDeposito(
                    $this->deposito,
                    $articoloData['articolo_id'],
                    $articoloData['quantita'],
                    $articoloData['costo_unitario']
                );
                $articoliAggiunti++;
            }

            // Aggiungi prodotti finiti selezionati
            foreach ($this->prodottiFinitiSelezionati as $pfData) {
                $service->inviaProdottoFinitoInDeposito(
                    $this->deposito,
                    $pfData['prodotto_finito_id'],
                    $pfData['costo_unitario']
                );
                $articoliAggiunti++;
            }

            // Aggiorna statistiche deposito
            $this->deposito->aggiornaStatistiche();
            $this->deposito->refresh();

            session()->flash('success', "{$articoliAggiunti} articoli/PF aggiunti al deposito");
            $this->chiudiAggiungiArticoliModal();

        } catch (\Exception $e) {
            session()->flash('error', 'Errore durante l\'aggiunta: ' . $e->getMessage());
        }
    }

    // ==========================================
    // ACTIONS - VENDITE
    // ==========================================
    
    /**
     * Apre il modal per vendita multipla con proforma
     */
    public function apriVenditaMultiplaModal()
    {
        if (!$this->puoGestireDestinatario) {
            session()->flash('error', 'Solo la sede destinataria può registrare vendite da questo deposito.');
            return;
        }
        // NON resettare le selezioni! Solo i campi proforma
        $this->reset([
            'numeroProforma',
            'clienteNome',
            'clienteCognome', 
            'clienteTelefono',
            'clienteEmail',
            'importoTotaleProforma',
            'noteProforma'
        ]);
        
        $this->dataProforma = now()->format('Y-m-d');
        $this->showVenditaMultiplaModal = true;
    }
    
    /**
     * Chiude il modal vendita multipla
     */
    public function chiudiVenditaMultiplaModal()
    {
        $this->showVenditaMultiplaModal = false;
        $this->resetValidation();
    }
    
    /**
     * Toggle selezione articolo per vendita
     */
    public function toggleArticoloVendita($articoloId)
    {
        if (isset($this->articoliSelezionatiVendita[$articoloId])) {
            unset($this->articoliSelezionatiVendita[$articoloId]);
        } else {
            $articoloData = $this->articoliInDeposito->firstWhere('articolo.id', $articoloId);
            if ($articoloData) {
                // SOLO dati essenziali, NO oggetti Eloquent
                $this->articoliSelezionatiVendita[$articoloId] = [
                    'articolo_id' => $articoloId,
                    'quantita' => min(1, $articoloData['quantita']),
                    'max_quantita' => $articoloData['quantita'],
                    'costo_unitario' => $articoloData['costo_unitario'],
                    // Dati per display (solo stringhe/numeri)
                    'codice' => $articoloData['articolo']['codice'] ?? '',
                    'descrizione' => $articoloData['articolo']['descrizione'] ?? '',
                ];
            }
        }
        
        $this->calcolaImportoTotale();
    }
    
    /**
     * Toggle selezione prodotto finito per vendita
     */
    public function toggleProdottoFinitoVendita($pfId)
    {
        // Debug log per verificare che il metodo viene chiamato
        Log::info("toggleProdottoFinitoVendita chiamato con ID: {$pfId}");
        
        if (isset($this->prodottiFinitiSelezionatiVendita[$pfId])) {
            unset($this->prodottiFinitiSelezionatiVendita[$pfId]);
            Log::info("PF {$pfId} rimosso dalla selezione");
        } else {
            $pfData = $this->prodottiFinitiInDeposito->firstWhere('prodotto_finito.id', $pfId);
            if ($pfData) {
                // SOLO dati essenziali, NO oggetti Eloquent
                $this->prodottiFinitiSelezionatiVendita[$pfId] = [
                    'prodotto_finito_id' => $pfId,
                    'quantita' => 1,
                    'costo_unitario' => $pfData['costo_unitario'],
                    // Dati per display (solo stringhe/numeri)
                    'codice' => $pfData['prodotto_finito']['codice'] ?? '',
                    'descrizione' => $pfData['prodotto_finito']['descrizione'] ?? '',
                ];
                Log::info("PF {$pfId} aggiunto alla selezione");
            } else {
                Log::error("PF {$pfId} non trovato nella collection prodottiFinitiInDeposito");
            }
        }
        
        $this->calcolaImportoTotale();
        
        // Debug della selezione attuale
        Log::info("Selezione attuale: " . count($this->prodottiFinitiSelezionatiVendita) . " PF selezionati");
    }
    
    /**
     * Calcola automaticamente l'importo totale
     */
    public function calcolaImportoTotale()
    {
        $totale = 0;
        
        // Somma articoli selezionati
        foreach ($this->articoliSelezionatiVendita as $articolo) {
            $totale += $articolo['quantita'] * $articolo['costo_unitario'];
        }
        
        // Somma prodotti finiti selezionati
        foreach ($this->prodottiFinitiSelezionatiVendita as $pf) {
            $totale += $pf['costo_unitario'];
        }
        
        $this->importoTotaleProforma = $totale;
    }
    
    /**
     * Aggiorna quantità e ricalcola totale
     */
    public function updatedArticoliSelezionatiVendita()
    {
        $this->calcolaImportoTotale();
    }
    
    /**
     * Registra vendita multipla con proforma
     */
    public function registraVenditaMultipla()
    {
        if (!$this->puoGestireDestinatario) {
            session()->flash('error', 'Solo la sede destinataria può registrare vendite da questo deposito.');
            return;
        }
        Log::info("🔥 registraVenditaMultipla CHIAMATO!");
        Log::info("📊 Selezioni: Articoli=" . count($this->articoliSelezionatiVendita) . ", PF=" . count($this->prodottiFinitiSelezionatiVendita));
        Log::info("📝 Campi proforma: numeroProforma='{$this->numeroProforma}', clienteNome='{$this->clienteNome}', clienteCognome='{$this->clienteCognome}'");
        
        // Validazione specifica per vendita multipla
        Log::info("🔍 Pre-validazione...");
        try {
            $this->validate([
                'numeroProforma' => 'required|string|max:50',
                'dataProforma' => 'required|date',
                'clienteNome' => 'required|string|max:100',
                'clienteCognome' => 'required|string|max:100',
                'clienteTelefono' => 'nullable|string|max:20',
                'clienteEmail' => 'nullable|email|max:100',
                'importoTotaleProforma' => 'nullable|numeric|min:0', // Opzionale: calcolato se vuoto
                'noteProforma' => 'nullable|string|max:500',
            ]);
            Log::info("✅ Validazione OK!");
        } catch (\Exception $e) {
            Log::error("❌ Validazione FALLITA: " . $e->getMessage());
            throw $e;
        }
        
        Log::info("🔍 Controllo selezioni...");
        if (empty($this->articoliSelezionatiVendita) && empty($this->prodottiFinitiSelezionatiVendita)) {
            Log::error("❌ Nessuna selezione trovata!");
            session()->flash('error', 'Seleziona almeno un articolo o prodotto finito da vendere');
            return;
        }
        Log::info("✅ Selezioni trovate, procedo...");

        Log::info("🚀 Inizio transazione...");
        try {
            DB::transaction(function () {
                Log::info("📦 Dentro transazione DB...");
                
                // Calcola totale articoli per proforma
                $totaleArticoli = 0;
                $importoCalcolato = 0;
                
                foreach ($this->articoliSelezionatiVendita as $articoloId => $articoloData) {
                    $totaleArticoli += $articoloData['quantita'];
                    // Calcola importo per articolo (se necessario)
                    $articolo = Articolo::findOrFail($articoloId);
                    $importoCalcolato += ($articolo->prezzo_acquisto ?? 0) * $articoloData['quantita'];
                }
                
                foreach ($this->prodottiFinitiSelezionatiVendita as $pfId => $pfData) {
                    $totaleArticoli += 1;
                    $prodottoFinito = ProdottoFinito::findOrFail($pfId);
                    $importoCalcolato += ($prodottoFinito->costo_totale ?? 0);
                }
                
                // Se importo non specificato, usa il calcolato
                if (empty($this->importoTotaleProforma) || $this->importoTotaleProforma == 0) {
                    $this->importoTotaleProforma = $importoCalcolato;
                }
                
                // Crea proforma deposito
                Log::info("📄 Creazione proforma deposito...");
                $proforma = $this->creaProforma();
                $proforma->update([
                    'quantita_totale' => $totaleArticoli,
                    'numero_articoli' => count($this->articoliSelezionatiVendita) + count($this->prodottiFinitiSelezionatiVendita),
                ]);
                Log::info("✅ Proforma creata: {$proforma->numero}");
                
                Log::info("🔧 Creazione ContoDepositoService...");
                $service = new ContoDepositoService();
                Log::info("✅ ContoDepositoService creato!");
                
                Log::info("🛒 Inizio registrazione vendite...");
                
                // Registra vendite articoli
                Log::info("🔍 Articoli selezionati: " . count($this->articoliSelezionatiVendita));
                foreach ($this->articoliSelezionatiVendita as $articoloId => $articoloData) {
                    Log::info("📦 Registro vendita articolo ID: {$articoloId}...");
                    $articolo = Articolo::findOrFail($articoloId);
                    $service->registraVendita(
                        $this->deposito,
                        $articolo,
                        $articoloData['quantita'],
                        $proforma
                    );
                }
                
                // Registra vendite prodotti finiti
                Log::info("🔍 PF selezionati: " . count($this->prodottiFinitiSelezionatiVendita));
                foreach ($this->prodottiFinitiSelezionatiVendita as $pfId => $pfData) {
                    Log::info("🏆 Registro vendita PF ID: {$pfId}...");
                    $prodottoFinito = ProdottoFinito::findOrFail($pfId);
                    $service->registraVendita(
                        $this->deposito,
                        $prodottoFinito,
                        1,
                        $proforma
                    );
                }
                
                // Aggiorna deposito
                $this->deposito->refresh();
            });
            
            // Ricarica proforme dopo la transazione
            $this->deposito->load('proforme');
            
            $totaleItemsVenduti = count($this->articoliSelezionatiVendita) + count($this->prodottiFinitiSelezionatiVendita);
            
            Log::info("🎉 VENDITA COMPLETATA! Items venduti: {$totaleItemsVenduti}");
            
            // Reset selezioni dopo vendita
            $this->articoliSelezionatiVendita = [];
            $this->prodottiFinitiSelezionatiVendita = [];
            
            session()->flash('success', "🎉 Vendita registrata con successo! {$totaleItemsVenduti} articoli venduti per €" . number_format($this->importoTotaleProforma, 2, ',', '.'));
            
            $this->chiudiVenditaMultiplaModal();
            
            // Forza refresh della pagina per mostrare i cambiamenti
            $this->redirect(route('conti-deposito.gestisci', $this->depositoId));
            
        } catch (\Exception $e) {
            session()->flash('error', 'Errore durante la registrazione: ' . $e->getMessage());
        }
    }
    
    /**
     * Verifica se un articolo è selezionato per vendita
     */
    public function isArticoloSelezionatoVendita($articoloId): bool
    {
        return isset($this->articoliSelezionatiVendita[$articoloId]);
    }
    
    /**
     * Verifica se un PF è selezionato per vendita
     */
    public function isProdottoFinitoSelezionatoVendita($pfId): bool
    {
        return isset($this->prodottiFinitiSelezionatiVendita[$pfId]);
    }
    
    /**
     * Ottiene il totale articoli selezionati per vendita
     */
    public function getTotaleSelezionatiVendita(): int
    {
        return count($this->articoliSelezionatiVendita) + count($this->prodottiFinitiSelezionatiVendita);
    }

    public function apriRegistraVenditaModal($tipo, $itemId)
    {
        if (!$this->puoGestireDestinatario) {
            session()->flash('error', 'Solo la sede destinataria può registrare vendite da questo deposito.');
            return;
        }
        if ($tipo === 'articolo') {
            $articoloData = $this->articoliInDeposito->firstWhere('articolo.id', $itemId);
            $articolo = $articoloData['articolo'];
            // Serializza solo dati necessari invece dell'oggetto Eloquent
            $this->itemVendita = [
                'tipo' => 'articolo',
                'item_id' => $articolo->id,
                'item_codice' => $articolo->codice,
                'item_descrizione' => $articolo->descrizione,
                'quantita_disponibile' => $articoloData['quantita'],
                'costo_unitario' => $articoloData['costo_unitario']
            ];
            $this->itemVenditaTipo = 'articolo';
            $this->itemVenditaId = $articolo->id;
        } else {
            $pfData = $this->prodottiFinitiInDeposito->firstWhere('prodotto_finito.id', $itemId);
            $pf = $pfData['prodotto_finito'];
            // Serializza solo dati necessari invece dell'oggetto Eloquent
            $this->itemVendita = [
                'tipo' => 'prodotto_finito',
                'item_id' => $pf->id,
                'item_codice' => $pf->codice,
                'item_descrizione' => $pf->descrizione,
                'quantita_disponibile' => 1,
                'costo_unitario' => $pfData['costo_unitario']
            ];
            $this->itemVenditaTipo = 'prodotto_finito';
            $this->itemVenditaId = $pf->id;
        }

        $this->quantitaVendita = 1;
        
        // Calcola e inizializza importo totale automaticamente
        $costoUnitario = $this->itemVendita['costo_unitario'];
        $this->importoTotaleProforma = $costoUnitario * $this->quantitaVendita;
        
        // Inizializza data proforma solo se vuota
        if (empty($this->dataProforma)) {
            $this->dataProforma = now()->format('Y-m-d');
        }
        // Reset validazione precedente
        $this->resetValidation();
        $this->showRegistraVenditaModal = true;
        Log::info("✅ Modal vendita aperto per {$tipo} ID: {$itemId}, totale iniziale: {$this->importoTotaleProforma}");
    }

    public function chiudiRegistraVenditaModal()
    {
        $this->showRegistraVenditaModal = false;
        $this->itemVendita = null;
        // NON resettare i campi proforma - potrebbero essere riutilizzati
        $this->resetValidation();
    }
    
    
    public function registraVendita()
    {
        if (!$this->puoGestireDestinatario) {
            session()->flash('error', 'Solo la sede destinataria può registrare vendite da questo deposito.');
            return;
        }
        Log::info("🔥 registraVendita CHIAMATO!");
        Log::info("📊 Dati: quantitaVendita={$this->quantitaVendita}, numeroProforma={$this->numeroProforma}, clienteNome={$this->clienteNome}");
        Log::info("📦 itemVenditaTipo={$this->itemVenditaTipo}, itemVenditaId={$this->itemVenditaId}");
        
        try {
            // Recupera item dal database per calcolare totale
            if ($this->itemVenditaTipo === 'articolo') {
                $item = Articolo::findOrFail($this->itemVenditaId);
                $costoUnitario = $item->prezzo_acquisto ?? 0;
            } else {
                $item = ProdottoFinito::findOrFail($this->itemVenditaId);
                $costoUnitario = $item->costo_totale ?? 0;
            }

            // Calcola importo totale se non specificato
            if (empty($this->importoTotaleProforma) || $this->importoTotaleProforma == 0) {
                $this->importoTotaleProforma = $costoUnitario * $this->quantitaVendita;
                Log::info("💰 Importo proforma calcolato automaticamente: {$this->importoTotaleProforma}");
            }
            
            // Validazione per vendita singola (inclusi campi proforma)
            $this->validate([
                'quantitaVendita' => 'required|integer|min:1',
                'numeroProforma' => 'required|string|max:50',
                'dataProforma' => 'required|date',
                'clienteNome' => 'required|string|max:100',
                'clienteCognome' => 'required|string|max:100',
                'clienteTelefono' => 'nullable|string|max:20',
                'clienteEmail' => 'nullable|email|max:100',
                'importoTotaleProforma' => 'required|numeric|min:0.01',
                'noteProforma' => 'nullable|string|max:500',
                'itemVenditaTipo' => 'required|in:articolo,prodotto_finito',
                'itemVenditaId' => 'required|integer',
            ]);
            Log::info("✅ Validazione OK!");
            
            Log::info("📄 Creazione proforma...");
            // Crea proforma deposito (il totale è già stato calcolato sopra)
            $proforma = $this->creaProforma();
            Log::info("✅ Proforma creata: {$proforma->numero}");
            
            $service = new ContoDepositoService();
            
            Log::info("📦 Registrazione vendita nel service...");
            $movimento = $service->registraVendita(
                $this->deposito,
                $item,
                $this->quantitaVendita,
                $proforma
            );
            Log::info("✅ Movimento creato!");

            // Aggiorna deposito e ricarica proforme
            $this->deposito->refresh();
            $this->deposito->load('proforme');

            session()->flash('success', "✅ Vendita registrata con successo!<br>
                <small>Proforma: <strong>{$proforma->numero}</strong> | Cliente: {$this->clienteNome} {$this->clienteCognome}</small>");
            
            // Chiudi modal solo se tutto è OK
            $this->chiudiRegistraVenditaModal();
            
            Log::info("🎉 VENDITA COMPLETATA!");

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error("❌ Errore validazione: " . json_encode($e->errors()));
            session()->flash('error', 'Errore di validazione. Verifica i campi inseriti.');
            // NON chiudere il modal se c'è errore di validazione
        } catch (\Exception $e) {
            Log::error("❌ Errore durante registrazione: " . $e->getMessage());
            Log::error("Stack trace: " . $e->getTraceAsString());
            session()->flash('error', 'Errore durante la registrazione: ' . $e->getMessage());
            // NON chiudere il modal se c'è errore
        }
    }


    private function creaProforma(): ProformaDeposito
    {
        $ddtInvio = $this->deposito->ddtInvio;

        $noteArray = [];
        if (!empty($this->noteProforma)) {
            $noteArray[] = $this->noteProforma;
        }
        if ($ddtInvio) {
            $noteArray[] = "DDT Invio: {$ddtInvio->numero}";
        }

        $note = !empty($noteArray) ? implode(' | ', $noteArray) : null;

        $importoTotale = $this->importoTotaleProforma;

        return ProformaDeposito::create([
            'numero' => $this->numeroProforma,
            'anno' => date('Y', strtotime($this->dataProforma)),
            'data_documento' => $this->dataProforma,
            'cliente_nome' => $this->clienteNome,
            'cliente_cognome' => $this->clienteCognome,
            'cliente_telefono' => $this->clienteTelefono,
            'cliente_email' => $this->clienteEmail,
            'totale' => $importoTotale,
            'imponibile' => $importoTotale,
            'iva' => 0,
            'sede_id' => $this->deposito->sede_destinataria_id,
            'conto_deposito_id' => $this->deposito->id,
            'ddt_invio_id' => $ddtInvio?->id,
            'quantita_totale' => isset($this->itemVendita) ? $this->quantitaVendita : 0,
            'numero_articoli' => isset($this->itemVendita) ? 1 : 0,
            'note' => $note,
            'stato' => ProformaDeposito::STATO_DA_FATTURARE,
        ]);
    }


    // ==========================================
    // ACTIONS - PROFORME / FATTURAZIONE
    // ==========================================

    public function apriSegnaFatturataModal(int $proformaId): void
    {
        if (!$this->puoGestireDestinatario) {
            session()->flash('error', 'Solo la sede destinataria può aggiornare lo stato della proforma.');
            return;
        }

        $proforma = ProformaDeposito::where('conto_deposito_id', $this->deposito->id)
            ->findOrFail($proformaId);

        $this->proformaSelezionataId = $proforma->id;
        $this->fatturaNumero = $proforma->fattura_numero ?? '';
        $this->fatturaData = $proforma->fattura_data ? $proforma->fattura_data->format('Y-m-d') : now()->format('Y-m-d');
        $this->fatturaNote = $proforma->fattura_note ?? '';
        $this->fatturaPdf = null;

        $this->resetValidation();
        $this->showSegnaFatturataModal = true;
    }

    public function chiudiSegnaFatturataModal(): void
    {
        $this->showSegnaFatturataModal = false;
        $this->reset(['fatturaPdf', 'fatturaNumero', 'fatturaData', 'fatturaNote']);
        $this->resetValidation();
    }

    public function salvaFatturaProforma(): void
    {
        if (!$this->puoGestireDestinatario) {
            session()->flash('error', 'Solo la sede destinataria può aggiornare lo stato della proforma.');
            return;
        }

        if (!$this->proformaSelezionataId) {
            session()->flash('error', 'Seleziona una proforma valida.');
            return;
        }

        $proforma = ProformaDeposito::where('conto_deposito_id', $this->deposito->id)
            ->findOrFail($this->proformaSelezionataId);

        $requirePdf = !$proforma->fattura_pdf_path;

        $rules = [
            'fatturaNumero' => 'nullable|string|max:100',
            'fatturaData' => 'nullable|date',
            'fatturaNote' => 'nullable|string|max:500',
        ];

        $rules['fatturaPdf'] = ($requirePdf ? 'required' : 'nullable') . '|file|mimes:pdf|max:20480';

        $this->validate($rules, [
            'fatturaPdf.required' => 'Carica il PDF della fattura per completare la fatturazione.',
            'fatturaPdf.mimes' => 'Il documento deve essere un PDF.',
            'fatturaPdf.max' => 'Il PDF non può superare i 20MB.',
        ]);

        $path = $proforma->fattura_pdf_path;
        if ($this->fatturaPdf) {
            if ($path && Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
            $path = $this->fatturaPdf->store("proforme/{$proforma->id}", 'public');
        }

        $proforma->update([
            'stato' => ProformaDeposito::STATO_FATTURATA,
            'fattura_pdf_path' => $path,
            'fatturata_da' => Auth::id(),
            'fatturata_il' => now(),
            'fattura_numero' => $this->fatturaNumero ?: null,
            'fattura_data' => $this->fatturaData ?: null,
            'fattura_note' => $this->fatturaNote ?: null,
        ]);

        $this->deposito->refresh()->load('proforme');

        $this->chiudiSegnaFatturataModal();

        session()->flash('success', 'La proforma è stata marcata come fatturata e il PDF è stato salvato.');
    }

    public function riapriProforma(int $proformaId): void
    {
        if (!$this->puoGestireDestinatario) {
            session()->flash('error', 'Solo la sede destinataria può aggiornare lo stato della proforma.');
            return;
        }

        $proforma = ProformaDeposito::where('conto_deposito_id', $this->deposito->id)
            ->findOrFail($proformaId);

        if ($proforma->fattura_pdf_path && Storage::disk('public')->exists($proforma->fattura_pdf_path)) {
            Storage::disk('public')->delete($proforma->fattura_pdf_path);
        }

        $proforma->update([
            'stato' => ProformaDeposito::STATO_DA_FATTURARE,
            'fattura_pdf_path' => null,
            'fatturata_da' => null,
            'fatturata_il' => null,
            'fattura_numero' => null,
            'fattura_data' => null,
            'fattura_note' => null,
        ]);

        $this->deposito->refresh()->load('proforme');

        session()->flash('success', 'La proforma è tornata in stato "da fatturare" e il PDF è stato rimosso.');
    }


    // ==========================================
    // ACTIONS - DDT
    // ==========================================

    private function getDatiDdt(): array
    {
        return [
            'causale' => $this->ddtCausale ?: null,
            'numero_colli' => $this->ddtNumeroColli !== '' ? (int) $this->ddtNumeroColli : null,
            'corriere' => $this->ddtCorriere ?: null,
            'numero_tracking' => $this->ddtNumeroTracking ?: null,
            'trasporto_mezzo' => $this->ddtTrasportoMezzo ?: null,
            'aspetto_beni' => $this->ddtAspettoBeni ?: null,
            'note' => $this->ddtNote ?: null,
        ];
    }

    public function generaDdtInvio()
    {
        if (!$this->puoGestireMittente) {
            session()->flash('error', 'Solo la sede mittente può generare il DDT di invio.');
            return;
        }

        if (!$this->haContenutoDeposito) {
            session()->flash('error', 'Aggiungi almeno un articolo o prodotto finito prima di generare il DDT.');
            return;
        }
        try {
            $service = new ContoDepositoService();
            $ddtDeposito = $service->generaDdtInvio($this->deposito, $this->getDatiDdt());
            
            // Aggiorna deposito
            $this->deposito->refresh();
            $this->showAnteprimaInvioModal = false;

            $url = route('ddt-deposito.stampa', $ddtDeposito->id);
            session()->flash('success', "DDT di invio {$ddtDeposito->numero} generato con successo.<br><a class='btn btn-sm btn-outline-dark mt-2' target='_blank' rel='noopener' href='{$url}'>Apri stampa DDT</a>");

        } catch (\Exception $e) {
            session()->flash('error', 'Errore durante la generazione DDT: ' . $e->getMessage());
        }
    }

    public function apriGeneraDdtResoModal()
    {
        if (!$this->puoGestireDestinatario) {
            session()->flash('error', 'Solo la sede destinataria può generare il DDT di reso.');
            return;
        }
        if ($this->ddtCausale === '') {
            $this->ddtCausale = 'Reso conto deposito';
        }
        $this->showGeneraDdtResoModal = true;
    }
    
    public function chiudiGeneraDdtResoModal()
    {
        $this->showGeneraDdtResoModal = false;
        $this->resetValidation();
    }
    
    public function getAnteprimaMovimentiResoProperty()
    {
        $service = new ContoDepositoService();
        
        // Ottieni movimenti reso NON ancora inclusi in DDT
        $tuttiMovimentiReso = $this->deposito->movimenti()
            ->where('tipo_movimento', 'reso')
            ->with(['articolo', 'prodottoFinito'])
            ->get();
            
        // Verifica quali sono già in DDT
        $ddtResiEsistenti = $this->deposito->ddtResi()->with('dettagli')->get();
        $movimentiGiaInDdt = collect();
        
        foreach ($ddtResiEsistenti as $ddtReso) {
            foreach ($ddtReso->dettagli as $dettaglio) {
                $movimentiGiaInDdt->push([
                    'articolo_id' => $dettaglio->articolo_id,
                    'prodotto_finito_id' => $dettaglio->prodotto_finito_id,
                    'quantita' => $dettaglio->quantita,
                ]);
            }
        }
        
        // Filtra movimenti disponibili per nuovo DDT
        $movimentiDisponibili = $tuttiMovimentiReso->filter(function ($movimento) use ($movimentiGiaInDdt) {
            foreach ($movimentiGiaInDdt as $giaInDdt) {
                if ($giaInDdt['articolo_id'] == $movimento->articolo_id && 
                    $giaInDdt['prodotto_finito_id'] == $movimento->prodotto_finito_id &&
                    $giaInDdt['quantita'] == $movimento->quantita) {
                    return false;
                }
            }
            return true;
        });
        
        return $movimentiDisponibili;
    }
    
    public function generaDdtReso()
    {
        if (!$this->puoGestireDestinatario) {
            session()->flash('error', 'Solo la sede destinataria può generare il DDT di reso.');
            return;
        }
        try {
            // Verifica se ci sono movimenti disponibili
            $movimentiDisponibili = $this->getAnteprimaMovimentiResoProperty();
            
            if ($movimentiDisponibili->isEmpty()) {
                session()->flash('warning', 'Non ci sono movimenti di reso disponibili per generare un nuovo DDT. Tutti i resi sono già stati inclusi in DDT precedenti.');
                return;
            }
            
            $service = new ContoDepositoService();
            
            // Se il deposito è scaduto, gestisci il reso automatico di tutti i rimanenti
            if ($this->deposito->isScaduto() && $this->deposito->getArticoliRimanenti() > 0) {
                $movimentiReso = $service->gestisciResoScadenza($this->deposito);
                $this->deposito->refresh();
            }
            
            // Genera il DDT (include solo movimenti reso non ancora in DDT)
            $ddtDeposito = $service->generaDdtReso($this->deposito, $this->getDatiDdt());
            
            // Aggiorna deposito
            $this->deposito->refresh();
            $this->chiudiGeneraDdtResoModal();

            $articoliTotali = $ddtDeposito->articoli_totali;
            $valoreTotale = $ddtDeposito->valore_dichiarato ?? 0;
            
            session()->flash('success', "✅ DDT di reso <strong>{$ddtDeposito->numero}</strong> generato con successo!<br>
                <small>Articoli: {$articoliTotali} | Valore: €" . number_format($valoreTotale, 2, ',', '.') . "</small><br>
                <a href='" . route('ddt-deposito.stampa', $ddtDeposito->id) . "' target='_blank' class='btn btn-sm btn-info mt-2'>
                    <iconify-icon icon='solar:printer-bold' class='me-1'></iconify-icon>
                    Apri e Stampa DDT
                </a>");

        } catch (\Exception $e) {
            session()->flash('error', 'Errore durante la generazione DDT reso: ' . $e->getMessage());
        }
    }

    /**
     * Rinnova il deposito per un altro anno creando reso + nuovo invio
     */
    public function apriRinnovoModal()
    {
        if (!$this->puoRinnovare) {
            session()->flash('error', 'Non hai i permessi per rinnovare questo deposito.');
            return;
        }
        $this->rinnovoModalita = 'rimanenti';
        $this->showRinnovoModal = true;
    }

    public function chiudiRinnovoModal()
    {
        $this->showRinnovoModal = false;
    }

    public function confermaRinnovoDeposito()
    {
        if (!$this->puoRinnovare) {
            session()->flash('error', 'Non hai i permessi per rinnovare questo deposito.');
            return;
        }

        $modalita = $this->rinnovoModalita === 'tutti' ? 'tutti' : 'rimanenti';

        try {
            $service = new ContoDepositoService();
            $nuovo = $service->rinnovaDeposito($this->deposito, $modalita);
            $this->showRinnovoModal = false;
            session()->flash('success', "✅ Deposito rinnovato (modalità: {$modalita}). Nuovo deposito: {$nuovo->codice}");
            return redirect()->route('conti-deposito.show', $nuovo->id);
        } catch (\Throwable $e) {
            session()->flash('error', '❌ Errore rinnovo: ' . $e->getMessage());
        }
    }

    // ==========================================
    // ACTIONS - RESO MANUALE
    // ==========================================

    public function apriResoManualeModal()
    {
        if (!$this->puoGestireDestinatario) {
            session()->flash('error', 'Solo la sede destinataria può effettuare resi manuali.');
            return;
        }
        // NON resettare le selezioni: permette di selezionare prima di aprire il modal
        $this->showResoManualeModal = true;
    }

    public function chiudiResoManualeModal()
    {
        $this->showResoManualeModal = false;
        $this->articoliSelezionatiReso = [];
        $this->prodottiFinitiSelezionatiReso = [];
        $this->resetValidation();
    }

    public function toggleArticoloReso($articoloId)
    {
        if (isset($this->articoliSelezionatiReso[$articoloId])) {
            unset($this->articoliSelezionatiReso[$articoloId]);
        } else {
            // Cerca l'articolo nei dati del deposito
            $articoliDeposito = $this->articoliInDeposito;
            $articoloData = null;
            
            foreach ($articoliDeposito as $data) {
                if (isset($data['articolo']) && $data['articolo']->id == $articoloId) {
                    $articoloData = $data;
                    break;
                }
            }
            
            if ($articoloData && isset($articoloData['articolo'])) {
                $this->articoliSelezionatiReso[$articoloId] = [
                    'articolo_id' => $articoloId,
                    'quantita' => min(1, $articoloData['quantita']),
                    'max_quantita' => $articoloData['quantita'],
                    'costo_unitario' => $articoloData['costo_unitario'] ?? 0,
                ];
            }
        }
    }

    public function toggleProdottoFinitoReso($pfId)
    {
        if (isset($this->prodottiFinitiSelezionatiReso[$pfId])) {
            unset($this->prodottiFinitiSelezionatiReso[$pfId]);
        } else {
            $pfData = $this->prodottiFinitiInDeposito->firstWhere('prodotto_finito.id', $pfId);
            if ($pfData) {
                $this->prodottiFinitiSelezionatiReso[$pfId] = [
                    'prodotto_finito_id' => $pfId,
                    'costo_unitario' => $pfData['costo_unitario'],
                ];
            }
        }
    }

    public function eseguiResoManuale()
    {
        if (!$this->puoGestireDestinatario) {
            session()->flash('error', 'Solo la sede destinataria può effettuare resi manuali.');
            return;
        }
        if (empty($this->articoliSelezionatiReso) && empty($this->prodottiFinitiSelezionatiReso)) {
            session()->flash('error', 'Seleziona almeno un articolo o prodotto finito da restituire');
            return;
        }

        // Validazione quantità
        foreach ($this->articoliSelezionatiReso as $articoloId => $articoloData) {
            if ($articoloData['quantita'] < 1 || $articoloData['quantita'] > $articoloData['max_quantita']) {
                session()->flash('error', "Quantità non valida per l'articolo selezionato");
                return;
            }
        }

        try {
            $service = new ContoDepositoService();
            
            // Prepara array per il Service
            $articoli = array_values($this->articoliSelezionatiReso);
            $prodottiFiniti = array_values($this->prodottiFinitiSelezionatiReso);
            
            $movimentiReso = $service->gestisciResoManuale(
                $this->deposito,
                $articoli,
                $prodottiFiniti
            );
            
            // Aggiorna deposito
            $this->deposito->refresh();
            
            $totaleReso = $movimentiReso->count();
            session()->flash('success', "Reso effettuato con successo per {$totaleReso} articolo/i. Vuoi generare il DDT di reso?");
            
            $this->chiudiResoManualeModal();

        } catch (\Exception $e) {
            session()->flash('error', 'Errore durante il reso: ' . $e->getMessage());
        }
    }

    // ==========================================
    // HELPERS
    // ==========================================

    public function isArticoloSelezionato($articoloId): bool
    {
        return isset($this->articoliSelezionati[$articoloId]);
    }

    public function isProdottoFinitoSelezionato($pfId): bool
    {
        return isset($this->prodottiFinitiSelezionati[$pfId]);
    }

    public function getTotaleSelezionati(): int
    {
        return count($this->articoliSelezionati) + count($this->prodottiFinitiSelezionati);
    }

    public function isArticoloSelezionatoReso($articoloId): bool
    {
        return isset($this->articoliSelezionatiReso[$articoloId]);
    }

    public function isProdottoFinitoSelezionatoReso($pfId): bool
    {
        return isset($this->prodottiFinitiSelezionatiReso[$pfId]);
    }

    public function getTotaleSelezionatiReso(): int
    {
        return count($this->articoliSelezionatiReso) + count($this->prodottiFinitiSelezionatiReso);
    }

    public function render()
    {
        // Assicura che ddtResi e fatture siano sempre caricati
        $this->deposito->load(['ddtResi.dettagli', 'movimentiVendita.proforma', 'proforme']);
        
        return view('livewire.gestisci-conto-deposito', [
            'articoliDisponibili' => $this->articoliDisponibili,
            'prodottiFinitiDisponibili' => $this->prodottiFinitiDisponibili,
            'articoliInDeposito' => $this->articoliInDeposito,
            'prodottiFinitiInDeposito' => $this->prodottiFinitiInDeposito,
            // Variabili per vendita multipla
            'articoliSelezionatiVendita' => $this->articoliSelezionatiVendita,
            'prodottiFinitiSelezionatiVendita' => $this->prodottiFinitiSelezionatiVendita,
        ]);
    }
}
