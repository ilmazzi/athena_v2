<?php

namespace App\Http\Livewire;

use Livewire\Component;
use App\Models\Movimentazione;

class ModificaMovimentazioneInterna extends Component
{
    public $movimentazioneId;
    public $movimentazione;

    public $dataMovimentazione = '';
    public $noteMovimentazione = '';
    public $trasportoMezzo = '';
    public $aspettoBeni = '';
    public $colli = '';
    public $vettore = '';

    protected $rules = [
        'dataMovimentazione' => 'required|date',
        'noteMovimentazione' => 'nullable|string|max:500',
        'trasportoMezzo' => 'nullable|string|max:100',
        'aspettoBeni' => 'nullable|string|max:100',
        'colli' => 'nullable|string|max:50',
        'vettore' => 'nullable|string|max:100',
    ];

    public function mount($movimentazione)
    {
        $this->movimentazioneId = $movimentazione;
        $this->movimentazione = Movimentazione::with([
            'dettagli.articolo' => fn($q) => $q->withoutGlobalScopes()->withTrashed()->with([
                'prodottoFinito.componentiArticoli.articolo' => fn($subQ) => $subQ->withoutGlobalScopes()->withTrashed(),
            ]),
            'dettagli.prodottoFinito.componentiArticoli.articolo' => fn($q) => $q->withoutGlobalScopes()->withTrashed(),
            'sedePartenza',
            'sedeDestinazione',
            'magazzinoPartenza' => fn($q) => $q->withoutGlobalScope('user_sede')->with('sede'),
            'magazzinoDestinazione' => fn($q) => $q->withoutGlobalScope('user_sede')->with('sede'),
            'creataDa',
        ])->findOrFail($movimentazione);

        $this->dataMovimentazione = $this->movimentazione->data_movimentazione?->format('Y-m-d') ?? '';
        $this->noteMovimentazione = $this->movimentazione->note ?? '';
        $this->trasportoMezzo = $this->movimentazione->trasporto_mezzo ?? '';
        $this->aspettoBeni = $this->movimentazione->aspetto_beni ?? '';
        $this->colli = $this->movimentazione->colli ?? '';
        $this->vettore = $this->movimentazione->vettore ?? ''; 
    }

    public function salvaModifiche()
    {
        $this->validate();

        $this->movimentazione->update([
            'data_movimentazione' => $this->dataMovimentazione,
            'note' => $this->noteMovimentazione,
            'trasporto_mezzo' => $this->trasportoMezzo,
            'aspetto_beni' => $this->aspettoBeni,
            'colli' => $this->colli,
            'vettore' => $this->vettore,
        ]);

        session()->flash('success', 'Movimentazione aggiornata.');
    }

    public function render()
    {
        return view('livewire.modifica-movimentazione-interna');
    }
}
