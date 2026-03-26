<?php

namespace App\Http\Livewire;

use App\Models\Stampante;
use App\Services\EtichettaService;
use Livewire\Component;

class StampaNc extends Component
{
    public string $prezzo = '';

    public string $formatoPrezzo = 'codificato';

    public string $carati = '';

    public string $tipoNc = 'prezzo_carati';

    public int $quantita = 1;

    public string $stampanteId = '';

    public array $stampantiDisponibili = [];

    protected function rules(): array
    {
        return [
            'prezzo' => 'required|string|max:50',
            'formatoPrezzo' => 'required|in:codificato,euro',
            'carati' => 'nullable|string|max:50',
            'tipoNc' => 'required|in:solo_prezzo,prezzo_carati',
            'quantita' => 'required|integer|min:1|max:200',
            'stampanteId' => 'required|exists:stampanti,id',
        ];
    }

    public function mount(EtichettaService $etichettaService): void
    {
        $this->stampantiDisponibili = Stampante::where('attiva', true)
            ->orderBy('nome')
            ->get(['id', 'nome', 'modello', 'ip_address'])
            ->toArray();

        $default = $etichettaService->getStampanteDefaultNc();
        if ($default) {
            $this->stampanteId = (string) $default->id;
        } elseif (!empty($this->stampantiDisponibili)) {
            $this->stampanteId = (string) $this->stampantiDisponibili[0]['id'];
        }
    }

    public function updatedTipoNc(string $value): void
    {
        if ($value === 'solo_prezzo') {
            $this->carati = '';
        }
    }

    public function updatedPrezzo(string $value): void
    {
        if ($this->formatoPrezzo !== 'euro') {
            $this->prezzo = mb_strtoupper(trim($value), 'UTF-8');
        }
    }

    public function stampa(EtichettaService $etichettaService): void
    {
        $validated = $this->validate();

        $carati = $validated['tipoNc'] === 'prezzo_carati'
            ? trim($validated['carati'])
            : null;

        if ($validated['tipoNc'] === 'prezzo_carati' && $carati === '') {
            $this->addError('carati', 'I carati sono obbligatori per il cartellino prezzo + carati.');
            return;
        }

        $ok = $etichettaService->stampaEtichettaNc(
            $validated['prezzo'],
            $validated['formatoPrezzo'],
            $carati,
            (int) $validated['stampanteId'],
            (int) $validated['quantita']
        );

        if (!$ok) {
            session()->flash('error', 'Errore durante l\'invio alla stampante.');
            return;
        }

        $tipoLabel = $validated['tipoNc'] === 'solo_prezzo' ? 'solo prezzo' : 'prezzo + carati';
        session()->flash('success', "Cartellini NC {$tipoLabel} inviati in stampa: {$validated['quantita']} copie.");
    }

    public function render()
    {
        return view('livewire.stampa-nc');
    }
}
