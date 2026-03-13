<?php

namespace App\Http\Controllers;

use App\Models\ProdottoFinito;
use App\Services\ProdottoFinitoService;
use Illuminate\Http\RedirectResponse;

class ProdottiFinitiController extends Controller
{
    public function smonta(int $id, ProdottoFinitoService $service): RedirectResponse
    {
        $prodotto = ProdottoFinito::with([
            'categoria.sede',
            'articoloRisultante.giacenza',
            'articoloRisultante.sede',
        ])->findOrFail($id);

        if (in_array($prodotto->stato, ['venduto', 'scartato', 'annullato'], true)) {
            return back()->with('error', 'Il prodotto finito non è smontabile nello stato attuale.');
        }

        if ($prodotto->isInContoDeposito()) {
            return back()->with('error', 'Il prodotto finito è in conto deposito e non può essere smontato.');
        }

        if ($prodotto->articoloRisultante && $prodotto->articoloRisultante->giacenza) {
            if ($prodotto->articoloRisultante->giacenza->quantita_residua <= 0) {
                return back()->with('error', 'Il prodotto finito risulta già scaricato/venduto.');
            }
        }

        $sedeCreazioneId = $prodotto->categoria?->sede_id;
        $sedeCorrenteId = $prodotto->articoloRisultante?->sede_id
            ?? $prodotto->articoloRisultante?->giacenza?->sede_id;
        if (!$sedeCreazioneId || !$sedeCorrenteId) {
            return back()->with('error', 'Impossibile determinare la sede del PF per lo smontaggio.');
        }
        if ($sedeCreazioneId !== $sedeCorrenteId) {
            $sedeCreazioneNome = $prodotto->categoria?->sede?->nome ?? 'sede di creazione';
            $sedeCorrenteNome = $prodotto->articoloRisultante?->sede?->nome ?? 'sede attuale';
            return back()->with(
                'error',
                "Puoi smontare il PF solo nella sede di creazione ({$sedeCreazioneNome}). " .
                "Attualmente è in {$sedeCorrenteNome}: movimentalo prima."
            );
        }

        $service->annullaAssemblaggio($prodotto->id);

        return back()->with('success', 'Prodotto finito smontato e componenti ripristinati.');
    }
}
