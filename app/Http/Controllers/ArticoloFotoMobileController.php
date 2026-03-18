<?php

namespace App\Http\Controllers;

use App\Models\Articolo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ArticoloFotoMobileController extends Controller
{
    public function __invoke(Request $request, Articolo $articolo)
    {
        if ($request->isMethod('post')) {
            $validated = $request->validate([
                'foto' => 'required|image|max:10240', // 10MB
            ]);

            $vecchioPath = $articolo->foto_principale;
            $nuovoPath = $validated['foto']->store("articoli/{$articolo->id}", 'public');

            $articolo->update([
                'foto_principale' => $nuovoPath,
            ]);

            if (!empty($vecchioPath) && !str_starts_with($vecchioPath, 'http://') && !str_starts_with($vecchioPath, 'https://')) {
                $normalized = ltrim(str_replace('\\', '/', $vecchioPath), '/');
                if (str_starts_with($normalized, 'storage/')) {
                    $normalized = substr($normalized, 8);
                }
                if (Storage::disk('public')->exists($normalized)) {
                    Storage::disk('public')->delete($normalized);
                }
            }

            return back()->with('success', "Foto caricata con successo per {$articolo->codice}");
        }

        return view('articoli.mobile-upload-foto', [
            'articolo' => $articolo,
        ]);
    }
}
