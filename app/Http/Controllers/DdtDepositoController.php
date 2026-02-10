<?php

namespace App\Http\Controllers;

use App\Models\DdtDeposito;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * DdtDepositoController - Controller per DDT specifici per Conti Deposito
 * 
 * Separato dal DdtController per mantenere logiche distinte
 * tra DDT acquisti e DDT trasferimenti deposito
 */
class DdtDepositoController extends Controller
{
    /**
     * Stampa DDT Deposito
     */
    public function stampa(Request $request, DdtDeposito $ddtDeposito)
    {
        // Carica relazioni necessarie per la stampa
        $ddtDeposito->load([
            'contoDeposito',
            'sedeMittente.societa',
            'sedeDestinataria.societa',
            'dettagli.articolo.categoriaMerceologica',
            'dettagli.prodottoFinito.categoriaMerceologica',
            'dettagli.prodottoFinito.componentiArticoli.articolo',
            'creatoDa'
        ]);

        // Marca come stampato se è la prima volta
        if ($ddtDeposito->stato === 'creato') {
            $ddtDeposito->marcaStampato();
        }

        $includeCosti = $request->boolean('include_costi');

        return view('ddt-deposito.stampa', compact('ddtDeposito', 'includeCosti'));
    }

    /**
     * Scarica PDF del DDT Deposito
     */
    public function scaricaPdf(Request $request, DdtDeposito $ddtDeposito)
    {
        // TODO: Implementare generazione PDF se necessaria
        // Per ora redirect alla stampa normale
        return $this->stampa($request, $ddtDeposito);
    }

    /**
     * Lista DDT Deposito (opzionale per future funzionalità)
     */
    public function index(Request $request)
    {
        $query = DdtDeposito::with([
            'contoDeposito',
            'sedeMittente',
            'sedeDestinataria'
        ]);

        // Filtri opzionali
        if ($request->filled('tipo')) {
            $query->where('tipo', $request->tipo);
        }

        if ($request->filled('stato')) {
            $query->where('stato', $request->stato);
        }

        if ($request->filled('sede')) {
            $query->perSede($request->sede);
        }

        if ($request->filled('anno')) {
            $query->perAnno($request->anno);
        }

        $ddtDepositi = $query->orderBy('created_at', 'desc')->paginate(20);

        return view('ddt-deposito.index', compact('ddtDepositi'));
    }

    /**
     * Dettaglio DDT Deposito
     */
    public function show(DdtDeposito $ddtDeposito)
    {
        $ddtDeposito->load([
            'contoDeposito',
            'sedeMittente',
            'sedeDestinataria',
            'dettagli.articolo',
            'dettagli.prodottoFinito',
            'creatoDa',
            'confermatoDa'
        ]);

        return view('ddt-deposito.dettaglio', compact('ddtDeposito'));
    }

    /**
     * Conferma ricezione DDT (per sede destinataria)
     */
    public function confermaRicezione(DdtDeposito $ddtDeposito, Request $request)
    {
        if ($ddtDeposito->stato !== 'in_transito') {
            return back()->with('error', 'Il DDT non è in stato corretto per la conferma');
        }

        try {
            // Prima marca come ricevuto
            $ddtDeposito->marcaRicevuto();
            
            // Poi conferma la ricezione
            $ddtDeposito->confermaRicezione();

            return back()->with('success', 'Ricezione DDT confermata con successo');
        } catch (\Exception $e) {
            return back()->with('error', 'Errore durante la conferma: ' . $e->getMessage());
        }
    }

    /**
     * Marca DDT come spedito (per sede mittente)
     */
    public function marcaSpedito(DdtDeposito $ddtDeposito, Request $request)
    {
        $request->validate([
            'numero_tracking' => 'nullable|string|max:100',
            'corriere' => 'nullable|string|max:255'
        ]);

        try {
            $ddtDeposito->marcaSpedito(
                $request->numero_tracking,
                $request->corriere
            );

            return back()->with('success', 'DDT marcato come spedito');
        } catch (\Exception $e) {
            return back()->with('error', 'Errore durante l\'aggiornamento: ' . $e->getMessage());
        }
    }
}
