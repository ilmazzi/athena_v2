<?php

namespace App\Http\Livewire;

use App\Models\Giacenza;
use App\Models\InventarioSessione;
use App\Models\Sede;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.vertical', ['title' => 'Giacenze e Inventario'])]
class GiacenzeInventario extends Component
{
    public $sedeId = '';

    public function getSediProperty()
    {
        return Sede::query()->orderBy('nome')->get();
    }

    public function getRiepilogoGiacenzeProperty()
    {
        $query = Giacenza::query()
            ->join('sedi', 'sedi.id', '=', 'giacenze.sede_id')
            ->selectRaw('
                giacenze.sede_id,
                sedi.nome as sede_nome,
                COUNT(*) as righe,
                SUM(COALESCE(giacenze.quantita_residua, 0)) as residua,
                SUM(CASE WHEN COALESCE(giacenze.quantita_residua, 0) = 0 THEN 1 ELSE 0 END) as esaurite
            ')
            ->groupBy('giacenze.sede_id', 'sedi.nome')
            ->orderBy('sedi.nome');

        if ($this->sedeId !== '') {
            $query->where('giacenze.sede_id', $this->sedeId);
        }

        return $query->get();
    }

    public function getSessioniProperty()
    {
        $query = InventarioSessione::query()
            ->with(['sede', 'utente'])
            ->orderByDesc('data_inizio');

        if ($this->sedeId !== '') {
            $query->where('sede_id', $this->sedeId);
        }

        return $query->limit(15)->get();
    }

    public function render()
    {
        return view('livewire.giacenze-inventario', [
            'sedi' => $this->sedi,
            'riepilogoGiacenze' => $this->riepilogoGiacenze,
            'sessioni' => $this->sessioni,
        ]);
    }
}

