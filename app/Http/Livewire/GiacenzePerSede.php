<?php

namespace App\Http\Livewire;

use App\Models\Giacenza;
use App\Models\Sede;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.vertical', ['title' => 'Giacenze per Sede'])]
class GiacenzePerSede extends Component
{
    use WithPagination;

    public $search = '';
    public $sedeId = '';
    public $soloCritiche = false;
    public $perPage = 25;

    public $showRettificaModal = false;
    public $giacenzaDaRettificare = null;
    public $nuovaQuantita = null;
    public $nuovaQuantitaResidua = null;
    public $noteRettifica = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'sedeId' => ['except' => ''],
        'soloCritiche' => ['except' => false],
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingSedeId(): void
    {
        $this->resetPage();
    }

    public function updatingSoloCritiche(): void
    {
        $this->resetPage();
    }

    public function getSediProperty()
    {
        return Sede::query()->orderBy('nome')->get();
    }

    public function getRiepilogoSediProperty()
    {
        return Giacenza::query()
            ->join('sedi', 'sedi.id', '=', 'giacenze.sede_id')
            ->selectRaw('
                giacenze.sede_id,
                sedi.nome as sede_nome,
                COUNT(*) as righe_giacenza,
                SUM(COALESCE(giacenze.quantita, 0)) as quantita_totale,
                SUM(COALESCE(giacenze.quantita_residua, 0)) as quantita_residua_totale,
                SUM(CASE WHEN COALESCE(giacenze.quantita_residua, 0) <= COALESCE(giacenze.quantita_minima, 0) THEN 1 ELSE 0 END) as righe_critiche
            ')
            ->groupBy('giacenze.sede_id', 'sedi.nome')
            ->orderBy('sedi.nome')
            ->get();
    }

    public function apriRettifica(int $giacenzaId): void
    {
        $giacenza = Giacenza::with('articolo')->findOrFail($giacenzaId);
        $this->giacenzaDaRettificare = $giacenza;
        $this->nuovaQuantita = (int) ($giacenza->quantita ?? 0);
        $this->nuovaQuantitaResidua = (int) ($giacenza->quantita_residua ?? 0);
        $this->noteRettifica = '';
        $this->showRettificaModal = true;
    }

    public function chiudiRettifica(): void
    {
        $this->showRettificaModal = false;
        $this->giacenzaDaRettificare = null;
        $this->nuovaQuantita = null;
        $this->nuovaQuantitaResidua = null;
        $this->noteRettifica = '';
    }

    public function salvaRettifica(): void
    {
        if (!$this->giacenzaDaRettificare) {
            session()->flash('error', 'Nessuna giacenza selezionata.');
            return;
        }

        $this->validate([
            'nuovaQuantita' => 'required|integer|min:0',
            'nuovaQuantitaResidua' => 'required|integer|min:0',
            'noteRettifica' => 'nullable|string|max:500',
        ]);

        if ((int) $this->nuovaQuantitaResidua > (int) $this->nuovaQuantita) {
            session()->flash('error', 'La quantita residua non puo superare la quantita totale.');
            return;
        }

        try {
            DB::transaction(function () {
                $giacenza = Giacenza::findOrFail($this->giacenzaDaRettificare->id);

                $noteEsistenti = trim((string) ($giacenza->note ?? ''));
                $nuovaNota = trim((string) $this->noteRettifica);
                $prefisso = '[' . now()->format('d/m/Y H:i') . '] Rettifica manuale';
                if ($nuovaNota !== '') {
                    $prefisso .= ': ' . $nuovaNota;
                }

                $giacenza->update([
                    'quantita' => (int) $this->nuovaQuantita,
                    'quantita_residua' => (int) $this->nuovaQuantitaResidua,
                    'ultimo_movimento_at' => now(),
                    'note' => trim($noteEsistenti . PHP_EOL . $prefisso),
                ]);
            });

            $codice = $this->giacenzaDaRettificare->articolo->codice ?? ('#' . $this->giacenzaDaRettificare->articolo_id);
            session()->flash('success', "Rettifica salvata per articolo {$codice}.");
            $this->chiudiRettifica();
        } catch (\Throwable $e) {
            session()->flash('error', 'Errore durante la rettifica giacenza.');
        }
    }

    public function render()
    {
        $query = Giacenza::query()
            ->with(['articolo', 'sede', 'ubicazione']);

        if ($this->sedeId !== '') {
            $query->where('sede_id', $this->sedeId);
        }

        if ($this->soloCritiche) {
            $query->where(function ($q) {
                $q->whereRaw('COALESCE(quantita_residua, 0) <= COALESCE(quantita_minima, 0)')
                    ->orWhere('quantita_residua', '<', 0);
            });
        }

        if (trim((string) $this->search) !== '') {
            $search = trim((string) $this->search);
            $query->where(function ($q) use ($search) {
                $q->whereHas('articolo', function ($subQ) use ($search) {
                    $subQ->where('codice', 'like', '%' . $search . '%')
                        ->orWhere('descrizione', 'like', '%' . $search . '%')
                        ->orWhere('numero_seriale', 'like', '%' . $search . '%');
                });
            });
        }

        $giacenze = $query
            ->orderByDesc('updated_at')
            ->paginate((int) $this->perPage);

        return view('livewire.giacenze-per-sede', [
            'giacenze' => $giacenze,
            'sedi' => $this->sedi,
            'riepilogoSedi' => $this->riepilogoSedi,
        ]);
    }
}

