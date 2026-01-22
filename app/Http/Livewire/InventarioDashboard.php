<?php

namespace App\Http\Livewire;

use App\Models\InventarioSessione;
use App\Models\Sede;
use App\Services\InventarioService;
use Livewire\Component;
use Livewire\WithPagination;

class InventarioDashboard extends Component
{
    use WithPagination;

    public $sessioniAttive = [];
    public $sessioniRecenti = [];
    public $statistiche = [];
    public $sedeSelezionata = '';
    public $sedi = [];

    protected $listeners = ['sessioneCreata' => 'aggiornaDashboard'];

    public function mount()
    {
        $this->sedi = Sede::all();
        $this->caricaDashboard();
    }

    public function caricaDashboard()
    {
        // Sessioni attive
        $this->sessioniAttive = InventarioSessione::attive()
            ->with(['sede', 'utente'])
            ->orderBy('data_inizio', 'desc')
            ->get();

        // Sessioni recenti (ultime 10)
        $this->sessioniRecenti = InventarioSessione::chiuse()
            ->with(['sede', 'utente'])
            ->orderBy('data_fine', 'desc')
            ->limit(10)
            ->get();

        // Statistiche generali
        $this->statistiche = [
            'sessioni_attive' => $this->sessioniAttive->count(),
            'sessioni_totali' => InventarioSessione::count(),
            'articoli_eliminati_totale' => \App\Models\ArticoloStorico::count(),
            'valore_eliminato_totale' => \App\Models\ArticoloStorico::sum('dati_completi->prezzo_acquisto')
        ];
    }

    public function aggiornaDashboard()
    {
        $this->caricaDashboard();
        session()->flash('success', 'Dashboard aggiornata');
    }

    public function filtraPerSede()
    {
        if ($this->sedeSelezionata) {
            $this->sessioniAttive = InventarioSessione::attive()
                ->perSede($this->sedeSelezionata)
                ->with(['sede', 'utente'])
                ->orderBy('data_inizio', 'desc')
                ->get();
        } else {
            $this->caricaDashboard();
        }
    }

    public function chiudiSessione($sessioneId)
    {
        try {
            $inventarioService = app(InventarioService::class);
            $sessione = $inventarioService->chiudiSessione($sessioneId);
            
            session()->flash('success', "Sessione '{$sessione->nome}' chiusa con successo. Articoli eliminati: {$sessione->articoli_eliminati}");
            $this->caricaDashboard();
        } catch (\Exception $e) {
            session()->flash('error', 'Errore durante la chiusura della sessione: ' . $e->getMessage());
        }
    }

    public function annullaSessione($sessioneId)
    {
        try {
            $inventarioService = app(InventarioService::class);
            $sessione = $inventarioService->annullaSessione($sessioneId);
            
            session()->flash('success', "Sessione '{$sessione->nome}' annullata");
            $this->caricaDashboard();
        } catch (\Exception $e) {
            session()->flash('error', 'Errore durante l\'annullamento della sessione: ' . $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.inventario-dashboard')
            ->layout('layouts.vertical');
    }
}