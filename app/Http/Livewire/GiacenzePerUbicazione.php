<?php

namespace App\Http\Livewire;

use App\Models\Giacenza;
use App\Models\Sede;
use App\Models\Ubicazione;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.vertical', ['title' => 'Giacenze per Ubicazione'])]
class GiacenzePerUbicazione extends Component
{
    use WithPagination;

    public $search = '';
    public $sedeId = '';
    public $soloSenzaUbicazione = false;
    public $perPage = 25;

    public $showUbicazioneModal = false;
    public $giacenzaDaAssegnare = null;
    public $ubicazioneId = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'sedeId' => ['except' => ''],
        'soloSenzaUbicazione' => ['except' => false],
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingSedeId(): void
    {
        $this->resetPage();
    }

    public function updatingSoloSenzaUbicazione(): void
    {
        $this->resetPage();
    }

    public function getSediProperty()
    {
        return Sede::query()->orderBy('nome')->get();
    }

    public function getStatisticheProperty(): array
    {
        $base = Giacenza::query();
        if ($this->sedeId !== '') {
            $base->where('sede_id', $this->sedeId);
        }

        $totale = (clone $base)->count();
        $senzaUbicazione = (clone $base)->whereNull('ubicazione_id')->count();

        return [
            'totale' => $totale,
            'senza_ubicazione' => $senzaUbicazione,
            'coperte' => max(0, $totale - $senzaUbicazione),
        ];
    }

    public function apriAssegnazioneUbicazione(int $giacenzaId): void
    {
        $this->giacenzaDaAssegnare = Giacenza::with(['articolo', 'sede'])->findOrFail($giacenzaId);
        $this->ubicazioneId = (string) ($this->giacenzaDaAssegnare->ubicazione_id ?? '');
        $this->showUbicazioneModal = true;
    }

    public function chiudiAssegnazioneUbicazione(): void
    {
        $this->showUbicazioneModal = false;
        $this->giacenzaDaAssegnare = null;
        $this->ubicazioneId = '';
    }

    public function salvaAssegnazioneUbicazione(): void
    {
        if (!$this->giacenzaDaAssegnare) {
            session()->flash('error', 'Nessuna giacenza selezionata.');
            return;
        }

        $giacenza = Giacenza::findOrFail($this->giacenzaDaAssegnare->id);
        $targetUbicazione = null;

        if ($this->ubicazioneId !== '') {
            $targetUbicazione = Ubicazione::query()
                ->where('id', $this->ubicazioneId)
                ->where('sede_id', $giacenza->sede_id)
                ->first();

            if (!$targetUbicazione) {
                session()->flash('error', 'Ubicazione non valida per la sede selezionata.');
                return;
            }
        }

        $giacenza->update([
            'ubicazione_id' => $targetUbicazione?->id,
            'scaffale' => $targetUbicazione?->scaffale,
            'box' => $targetUbicazione?->box,
            'posizione' => $targetUbicazione?->posizione,
            'ultimo_movimento_at' => now(),
        ]);

        $codice = $giacenza->articolo->codice ?? ('#' . $giacenza->articolo_id);
        session()->flash('success', "Ubicazione aggiornata per articolo {$codice}.");
        $this->chiudiAssegnazioneUbicazione();
    }

    public function getUbicazioniDisponibiliProperty()
    {
        if (!$this->giacenzaDaAssegnare) {
            return collect();
        }

        return Ubicazione::query()
            ->where('attivo', true)
            ->where('sede_id', $this->giacenzaDaAssegnare->sede_id)
            ->orderBy('codice')
            ->get();
    }

    public function render()
    {
        $query = Giacenza::query()
            ->with(['articolo', 'sede', 'ubicazione']);

        if ($this->sedeId !== '') {
            $query->where('sede_id', $this->sedeId);
        }

        if ($this->soloSenzaUbicazione) {
            $query->whereNull('ubicazione_id');
        }

        if (trim((string) $this->search) !== '') {
            $search = trim((string) $this->search);
            $query->where(function ($q) use ($search) {
                $q->whereHas('articolo', function ($subQ) use ($search) {
                    $subQ->where('codice', 'like', '%' . $search . '%')
                        ->orWhere('descrizione', 'like', '%' . $search . '%');
                })->orWhereHas('ubicazione', function ($subQ) use ($search) {
                    $subQ->where('codice', 'like', '%' . $search . '%')
                        ->orWhere('descrizione', 'like', '%' . $search . '%');
                });
            });
        }

        $giacenze = $query
            ->orderByDesc('updated_at')
            ->paginate((int) $this->perPage);

        return view('livewire.giacenze-per-ubicazione', [
            'giacenze' => $giacenze,
            'sedi' => $this->sedi,
            'statistiche' => $this->statistiche,
            'ubicazioniDisponibili' => $this->ubicazioniDisponibili,
        ]);
    }
}

