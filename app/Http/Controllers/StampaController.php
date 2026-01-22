<?php

namespace App\Http\Controllers;

use App\Models\Articolo;
use App\Models\Stampante;
use App\Services\EtichettaService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class StampaController extends Controller
{
    protected $etichettaService;

    public function __construct(EtichettaService $etichettaService)
    {
        $this->etichettaService = $etichettaService;
    }

    /**
     * Stampa etichetta singola
     */
    public function stampaEtichetta($articoloId, $stampanteId = null)
    {
        $articolo = Articolo::findOrFail($articoloId);
        
        
        // Verifica permessi utente
        if (!auth()->user()->canAccessArticolo($articolo)) {
            abort(403, 'Non hai i permessi per stampare questo articolo');
        }

        try {
            $success = $this->etichettaService->stampaEtichetta($articolo, $stampanteId);
            
            if ($success) {
                return redirect()->back()->with('success', 'Etichetta stampata con successo!');
            } else {
                return redirect()->back()->with('error', 'Errore durante la stampa dell\'etichetta');
            }
        } catch (\Exception $e) {
            \Log::error('Errore stampa etichetta: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Errore: ' . $e->getMessage());
        }
    }

    /**
     * Stampa etichetta con prezzo personalizzato
     */
    public function stampaEtichettaConPrezzo($datiStampa)
    {
        try {
            $articolo = Articolo::findOrFail($datiStampa['articolo_id']);
            
            // Verifica permessi utente
            if (!auth()->user()->canAccessArticolo($articolo)) {
                return [
                    'success' => false,
                    'message' => 'Non hai i permessi per stampare questo articolo'
                ];
            }
            
            // Trova stampante selezionata o predefinita
            if (isset($datiStampa['stampante_id'])) {
                $stampante = \App\Models\Stampante::find($datiStampa['stampante_id']);
            } else {
                $stampante = $this->etichettaService->getStampanteDefault($articolo);
            }
            
            // Genera ZPL con prezzo personalizzato
            $zpl = $this->etichettaService->generaEtichettaZPLConPrezzo(
                $articolo, 
                $datiStampa['prezzo'], 
                $datiStampa['formato_prezzo'],
                $stampante ? $stampante->id : null,
                $datiStampa['layout'] ?? 'standard'
            );
            
            if (!$stampante) {
                return [
                    'success' => false,
                    'message' => 'Nessuna stampante disponibile per questo articolo'
                ];
            }
            
            // Invia alla stampante
            $risultato = $this->etichettaService->inviaAllaStampante(
                $stampante->ip_address, 
                $stampante->port, 
                $zpl
            );
            
            if ($risultato) {
                return [
                    'success' => true,
                    'message' => "Etichetta inviata alla stampante {$stampante->nome}"
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Errore durante l\'invio alla stampante'
                ];
            }
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Errore durante la stampa: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Stampa etichette multiple
     */
    public function stampaBatch(Request $request)
    {
        $request->validate([
            'articoli' => 'required|array|min:1',
            'articoli.*' => 'integer|exists:articoli,id',
            'stampante' => 'nullable|exists:stampanti,id'
        ]);

        $articoliIds = $request->input('articoli');
        $stampanteId = $request->input('stampante');
        $successCount = 0;
        $errorCount = 0;

        foreach ($articoliIds as $articoloId) {
            $articolo = Articolo::find($articoloId);
            
            if (!$articolo || !auth()->user()->canAccessArticolo($articolo)) {
                $errorCount++;
                continue;
            }

            try {
                if ($this->etichettaService->stampaEtichetta($articolo, $stampanteId)) {
                    $successCount++;
                } else {
                    $errorCount++;
                }
            } catch (\Exception $e) {
                $errorCount++;
            }
        }

        if ($errorCount === 0) {
            return redirect()->back()->with('success', "Stampe completate: {$successCount} etichette");
        } else {
            return redirect()->back()->with('warning', "Stampe completate: {$successCount}, Errori: {$errorCount}");
        }
    }

    /**
     * Download del codice ZPL per test
     */
    public function downloadZPL($articoloId, $stampanteId = null)
    {
        $articolo = Articolo::findOrFail($articoloId);
        
        if (!auth()->user()->canAccessArticolo($articolo)) {
            abort(403, 'Non hai i permessi per questo articolo');
        }

        try {
            $zpl = $this->etichettaService->generaEtichettaZPL($articolo, $stampanteId);
            
            return response($zpl)
                ->header('Content-Type', 'text/plain')
                ->header('Content-Disposition', 'attachment; filename="etichetta_' . $articolo->codice . '.zpl"');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Errore: ' . $e->getMessage());
        }
    }

    /**
     * Anteprima etichetta (genera ZPL senza stampare)
     */
    public function anteprimaEtichetta($articoloId, $stampanteId = null)
    {
        $articolo = Articolo::findOrFail($articoloId);
        
        if (!auth()->user()->canAccessArticolo($articolo)) {
            abort(403, 'Non hai i permessi per questo articolo');
        }

        try {
            $zpl = $this->etichettaService->generaEtichettaZPL($articolo, $stampanteId);
            
            return response($zpl)
                ->header('Content-Type', 'text/plain')
                ->header('Content-Disposition', 'inline; filename="anteprima_etichetta.txt"');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Errore: ' . $e->getMessage());
        }
    }

    /**
     * Lista stampanti disponibili per l'utente
     */
    public function stampantiDisponibili()
    {
        $user = auth()->user();
        
        $stampanti = Stampante::where('attiva', true)
            ->get()
            ->filter(function ($stampante) use ($user) {
                // Filtra per permessi utente se definiti
                if ($user->categorie_permesse && $user->sedi_permesse) {
                    return !empty(array_intersect($stampante->categorie_permesse, $user->categorie_permesse)) &&
                           !empty(array_intersect($stampante->sedi_permesse, $user->sedi_permesse));
                }
                
                return true; // Se l'utente non ha restrizioni
            });

        return response()->json($stampanti);
    }

    /**
     * Test connessione stampante
     */
    public function testStampante($stampanteId)
    {
        $stampante = Stampante::findOrFail($stampanteId);
        
        try {
            // Test con etichetta di prova
            $testZpl = '^XA^FO50,50^A0N,30,30^FDTEST CONNECTION^FS^XZ';
            
            $success = $this->etichettaService->inviaAllaStampante(
                $stampante->ip_address, 
                $stampante->port, 
                $testZpl
            );
            
            if ($success) {
                return response()->json(['success' => true, 'message' => 'Connessione OK']);
            } else {
                return response()->json(['success' => false, 'message' => 'Errore di connessione']);
            }
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}