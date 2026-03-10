<?php

namespace App\Http\Controllers;

use App\Models\Vetrina;
use App\Models\ArticoloVetrina;
use Illuminate\Http\Request;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

class VetrinaController extends Controller
{
    /**
     * Stampa foglio vetrina con QR codes
     */
    public function stampaVetrina($id)
    {
        $vetrina = Vetrina::findOrFail($id);
        
        $articoliInVetrina = ArticoloVetrina::with([
            'articolo.categoriaMerceologica',
            'articolo.sede',
            'categoriaMerceologica',
            'sede',
        ])
            ->where('vetrina_id', $vetrina->id)
            ->whereNull('data_rimozione')
            ->orderBy('posizione')
            ->orderBy('created_at', 'desc')
            ->get();

        // Genera QR codes per ogni articolo
        $articoliConQr = $articoliInVetrina->map(function ($articoloVetrina) {
            $codice = $articoloVetrina->articolo?->codice;
            if ($articoloVetrina->is_esterno || empty($codice)) {
                $articoloVetrina->qr_code_base64 = null;
                return $articoloVetrina;
            }

            $qrCode = new QrCode($codice);
            $writer = new PngWriter();
            $result = $writer->write($qrCode);

            $articoloVetrina->qr_code_base64 = base64_encode($result->getString());
            return $articoloVetrina;
        });

        return view('vetrine.stampa', [
            'vetrina' => $vetrina,
            'articoli' => $articoliConQr,
        ]);
    }

    /**
     * Download PDF vetrina (TODO: Implementare con libreria PDF)
     */
    public function downloadPdfVetrina($id)
    {
        // TODO: Implementare generazione PDF
        return redirect()->route('vetrine.stampa', $id)->with('info', 'Funzionalità PDF in sviluppo. Usa la stampa browser.');
    }
}
