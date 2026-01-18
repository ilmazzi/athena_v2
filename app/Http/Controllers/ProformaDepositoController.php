<?php

namespace App\Http\Controllers;

use App\Models\ProformaDeposito;

class ProformaDepositoController extends Controller
{
    public function show(ProformaDeposito $proformaDeposito)
    {
        $proformaDeposito->load([
            'contoDeposito',
            'sede',
            'ddtInvio',
            'movimenti.articolo',
            'movimenti.prodottoFinito',
        ]);

        return view('proforme-deposito.dettaglio', [
            'proforma' => $proformaDeposito,
        ]);
    }

    public function stampa(ProformaDeposito $proformaDeposito)
    {
        $proformaDeposito->load([
            'contoDeposito',
            'sede',
            'ddtInvio',
            'movimenti.articolo.categoriaMerceologica',
            'movimenti.prodottoFinito.categoriaMerceologica',
        ]);

        return view('proforme-deposito.stampa', [
            'proforma' => $proformaDeposito,
        ]);
    }
}

