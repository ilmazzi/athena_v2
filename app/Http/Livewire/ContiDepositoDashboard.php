<?php

namespace App\Http\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\ContoDeposito;
use App\Models\Sede;
use App\Services\ContoDepositoService;
use Carbon\Carbon;

/**
 * ContiDepositoDashboard - Dashboard gestione conti deposito
 * 
 * Fornisce una vista completa di tutti i conti deposito con:
 * - Statistiche generali
 * - Alert e notifiche scadenze
 * - Lista depositi con filtri
 * - Azioni rapide (rinnovi, resi, etc.)
 */
class ContiDepositoDashboard extends Component
{
    use WithPagination;

    // Filtri
    public $filtroStato = '';
    public $filtroSede = '';
    public $filtroScadenza = '';
    public $search = '';

    // Modali
    public $showNuovoDepositoModal = false;
    public $showDettaglioModal = false;
    public $depositoSelezionato = null;

    // Form nuovo deposito
    public $sedeMittenteId = '';
    public $sedeDestinatariaId = '';
    public $noteDeposito = '';
    public $stepCreazioneDeposito = 1; // 1 = Info, 2 = Anteprima

    protected $queryString = [
        'search' => ['except' => ''],
        'filtroStato' => ['except' => ''],
        'filtroSede' => ['except' => ''],
        'filtroScadenza' => ['except' => ''],
    ];

    protected $rules = [
        'sedeMittenteId' => 'required|exists:sedi,id|different:sedeDestinatariaId',
        'sedeDestinatariaId' => 'required|exists:sedi,id|different:sedeMittenteId',
        'noteDeposito' => 'nullable|string|max:1000',
    ];

    protected $messages = [
        'sedeMittenteId.different' => 'La sede mittente deve essere diversa dalla destinataria',
        'sedeDestinatariaId.different' => 'La sede destinataria deve essere diversa dalla mittente',
    ];

    public function mount()
    {
        // Inizializzazioni se necessarie
    }

    // ==========================================
    // COMPUTED PROPERTIES
    // ==========================================

    public function getStatisticheProperty()
    {
        $service = new ContoDepositoService();
        return $service->getStatisticheDepositi();
    }

    public function getSediProperty()
    {
        return Sede::where('attivo', true)->orderBy('nome')->get();
    }
    
    public function getCanContinueProperty()
    {
        return !empty($this->sedeMittenteId) && 
               !empty($this->sedeDestinatariaId) && 
               $this->sedeMittenteId != $this->sedeDestinatariaId;
    }

    public function getDepositi()
    {
        return ContoDeposito::with(['sedeMittente', 'sedeDestinataria', 'creatoDa', 'ddtInvio', 'ddtReso', 'ddtDepositi', 'proforme'])
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('codice', 'like', '%' . $this->search . '%')
                      ->orWhereHas('sedeMittente', function ($sq) {
                          $sq->where('nome', 'like', '%' . $this->search . '%');
                      })
                      ->orWhereHas('sedeDestinataria', function ($sq) {
                          $sq->where('nome', 'like', '%' . $this->search . '%');
                      });
                });
            })
            ->when($this->filtroStato, function ($query) {
                $query->where('stato', $this->filtroStato);
            })
            ->when($this->filtroSede, function ($query) {
                $query->where(function ($q) {
                    $q->where('sede_mittente_id', $this->filtroSede)
                      ->orWhere('sede_destinataria_id', $this->filtroSede);
                });
            })
            ->when($this->filtroScadenza, function ($query) {
                switch ($this->filtroScadenza) {
                    case 'scaduti':
                        $query->where('data_scadenza', '<', now()->toDateString())
                              ->where('stato', '!=', 'chiuso');
                        break;
                    case 'in_scadenza_30':
                        $query->where('data_scadenza', '<=', now()->addDays(30)->toDateString())
                              ->where('data_scadenza', '>=', now()->toDateString())
                              ->where('stato', 'attivo');
                        break;
                    case 'in_scadenza_60':
                        $query->where('data_scadenza', '<=', now()->addDays(60)->toDateString())
                              ->where('data_scadenza', '>=', now()->toDateString())
                              ->where('stato', 'attivo');
                        break;
                }
            })
            ->orderBy('data_scadenza', 'asc')
            ->paginate(15);
    }

    public function getAlertsProperty()
    {
        $alerts = collect();

        // Depositi scaduti
        $depositiScaduti = ContoDeposito::where('data_scadenza', '<', now()->toDateString())
            ->where('stato', '!=', 'chiuso')
            ->count();

        if ($depositiScaduti > 0) {
            $alerts->push([
                'tipo' => 'danger',
                'icona' => 'solar:danger-bold',
                'titolo' => 'Depositi Scaduti',
                'messaggio' => "{$depositiScaduti} depositi sono scaduti e richiedono attenzione",
                'azione' => 'filtroScadenza',
                'valore' => 'scaduti'
            ]);
        }

        // Depositi in scadenza (30 giorni)
        $depositiInScadenza = ContoDeposito::inScadenza(30)->count();
        if ($depositiInScadenza > 0) {
            $alerts->push([
                'tipo' => 'warning',
                'icona' => 'solar:clock-circle-bold',
                'titolo' => 'Depositi in Scadenza',
                'messaggio' => "{$depositiInScadenza} depositi scadranno entro 30 giorni",
                'azione' => 'filtroScadenza',
                'valore' => 'in_scadenza_30'
            ]);
        }

        return $alerts;
    }

    // ==========================================
    // ACTIONS
    // ==========================================

    public function applicaFiltroAlert($filtro, $valore)
    {
        $this->{$filtro} = $valore;
        $this->resetPage();
    }

    public function resetFiltri()
    {
        $this->reset(['filtroStato', 'filtroSede', 'filtroScadenza', 'search']);
        $this->resetPage();
    }

    public function apriNuovoDepositoModal()
    {
        $this->reset(['sedeMittenteId', 'sedeDestinatariaId', 'noteDeposito', 'stepCreazioneDeposito']);
        $this->stepCreazioneDeposito = 1;
        $this->showNuovoDepositoModal = true;
    }
    
    public function vaiAdAnteprima()
    {
        // Converti stringhe vuote in null per validazione
        $mittenteId = $this->sedeMittenteId === '' ? null : (int)$this->sedeMittenteId;
        $destinatariaId = $this->sedeDestinatariaId === '' ? null : (int)$this->sedeDestinatariaId;
        
        // Valida solo le info base
        $this->validate([
            'sedeMittenteId' => 'required|exists:sedi,id|different:sedeDestinatariaId',
            'sedeDestinatariaId' => 'required|exists:sedi,id|different:sedeMittenteId',
        ], [
            'sedeMittenteId.required' => 'Seleziona la sede mittente',
            'sedeDestinatariaId.required' => 'Seleziona la sede destinataria',
            'sedeMittenteId.different' => 'La sede mittente deve essere diversa dalla destinataria',
            'sedeDestinatariaId.different' => 'La sede destinataria deve essere diversa dalla mittente',
        ]);
        
        $this->stepCreazioneDeposito = 2; // Vai all'anteprima
    }
    
    public function tornaAllInfo()
    {
        $this->stepCreazioneDeposito = 1;
    }

    public function chiudiNuovoDepositoModal()
    {
        $this->showNuovoDepositoModal = false;
        $this->resetValidation();
    }

    public function apriDettaglioModal($depositoId)
    {
        $this->depositoSelezionato = ContoDeposito::with([
            'sedeMittente', 
            'sedeDestinataria', 
            'movimenti.articolo', 
            'movimenti.prodottoFinito',
            'creatoDa'
        ])->findOrFail($depositoId);
        
        $this->showDettaglioModal = true;
    }

    public function chiudiDettaglioModal()
    {
        $this->showDettaglioModal = false;
        $this->depositoSelezionato = null;
    }

    public function creaDeposito()
    {
        $this->validate();

        try {
            // Per ora creiamo solo il deposito vuoto
            // L'aggiunta di articoli/PF sarà gestita in un secondo step
            $deposito = ContoDeposito::create([
                'codice' => ContoDeposito::generaCodice(),
                'sede_mittente_id' => $this->sedeMittenteId,
                'sede_destinataria_id' => $this->sedeDestinatariaId,
                'data_invio' => now()->toDateString(),
                'data_scadenza' => now()->addYear()->toDateString(),
                'stato' => 'attivo',
                'note' => $this->noteDeposito,
                'creato_da' => auth()->id(),
            ]);

            $this->chiudiNuovoDepositoModal();
            
            session()->flash('success', "✅ Deposito <strong>{$deposito->codice}</strong> creato con successo!<br>
                <small>Mittente: {$deposito->sedeMittente->nome} → Destinataria: {$deposito->sedeDestinataria->nome}</small><br>
                <small>Scadenza: {$deposito->data_scadenza->format('d/m/Y')} (1 anno)</small><br>
                <a href='" . route('conti-deposito.gestisci', $deposito->id) . "' class='btn btn-sm btn-primary mt-2'>
                    <iconify-icon icon='solar:settings-bold' class='me-1'></iconify-icon>
                    Aggiungi Articoli al Deposito
                </a>");
            
            // Redirect alla pagina di gestione del deposito per aggiungere articoli
            return redirect()->route('conti-deposito.gestisci', $deposito->id);

        } catch (\Exception $e) {
            session()->flash('error', 'Errore durante la creazione: ' . $e->getMessage());
        }
    }

    public function gestisciResoScadenza($depositoId)
    {
        try {
            $deposito = ContoDeposito::findOrFail($depositoId);
            $service = new ContoDepositoService();
            
            $movimentiReso = $service->gestisciResoScadenza($deposito);
            
            session()->flash('success', "Reso automatico completato per {$movimentiReso->count()} articoli/PF");
            
        } catch (\Exception $e) {
            session()->flash('error', 'Errore durante il reso: ' . $e->getMessage());
        }
    }

    public function creaRimando($depositoId)
    {
        try {
            $deposito = ContoDeposito::findOrFail($depositoId);
            $service = new ContoDepositoService();
            
            $nuovoDeposito = $service->creaRimandoDopoReso($deposito);
            
            session()->flash('success', "Nuovo deposito {$nuovoDeposito->codice} creato come rimando");
            
        } catch (\Exception $e) {
            session()->flash('error', 'Errore durante il rimando: ' . $e->getMessage());
        }
    }

    // ==========================================
    // HELPERS
    // ==========================================

    public function getGiorniRimanenti($dataScadenza): int
    {
        return max(0, Carbon::parse($dataScadenza)->diffInDays(now(), false));
    }

    public function getColoreScadenza($dataScadenza): string
    {
        $giorni = $this->getGiorniRimanenti($dataScadenza);
        
        if ($giorni < 0) return 'danger';
        if ($giorni <= 30) return 'warning';
        if ($giorni <= 60) return 'info';
        return 'success';
    }

    public function render()
    {
        return view('livewire.conti-deposito-dashboard', [
            'depositi' => $this->getDepositi(),
            'statistiche' => $this->statistiche,
            'sedi' => $this->sedi,
            'alerts' => $this->alerts,
        ]);
    }
}