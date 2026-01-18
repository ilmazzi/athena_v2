<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Proforma {{ $proforma->numero }}</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .table td, .table th { padding: 0.5rem; }
        .header { display: flex; justify-content: space-between; margin-bottom: 1.5rem; }
        .badge { display: inline-block; padding: 0.25rem 0.5rem; border-radius: 0.25rem; font-size: 0.75rem; }
        .badge-success { background: #d1f0d3; color: #0f5132; }
        .badge-warning { background: #fff3cd; color: #664d03; }
    </style>
</head>
<body onload="window.print()">
    <div class="container my-4">
        <div class="header">
            <div>
                <h2>Proforma {{ $proforma->numero }}</h2>
                <p class="mb-0">Data: {{ $proforma->data_documento->format('d/m/Y') }}</p>
                <p class="mb-0">Conto deposito: {{ $proforma->contoDeposito?->codice }}</p>
            </div>
            <div>
                @if($proforma->stato === \App\Models\ProformaDeposito::STATO_FATTURATA)
                    <span class="badge badge-success">Fatturata</span>
                @else
                    <span class="badge badge-warning">Da fatturare</span>
                @endif
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-6">
                <h5>Dati cliente</h5>
                <p class="mb-0"><strong>{{ $proforma->cliente_nome }} {{ $proforma->cliente_cognome }}</strong></p>
                @if($proforma->cliente_telefono)
                    <p class="mb-0">Tel: {{ $proforma->cliente_telefono }}</p>
                @endif
                @if($proforma->cliente_email)
                    <p class="mb-0">Email: {{ $proforma->cliente_email }}</p>
                @endif
            </div>
            <div class="col-6 text-end">
                <h5>Totali</h5>
                <p class="mb-0">Imponibile: €{{ number_format($proforma->imponibile, 2, ',', '.') }}</p>
                <p class="mb-0">IVA: €{{ number_format($proforma->iva, 2, ',', '.') }}</p>
                <p class="mb-0"><strong>Totale: €{{ number_format($proforma->totale, 2, ',', '.') }}</strong></p>
            </div>
        </div>

        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Tipo</th>
                    <th>Codice</th>
                    <th>Descrizione</th>
                    <th class="text-center">Q.tà</th>
                    <th class="text-end">Costo unit.</th>
                    <th class="text-end">Totale</th>
                </tr>
            </thead>
            <tbody>
                @foreach($proforma->movimenti as $movimento)
                    <tr>
                        <td>{{ $movimento->isArticolo() ? 'Articolo' : 'Prodotto Finito' }}</td>
                        <td>{{ $movimento->getCodiceItem() }}</td>
                        <td>{{ $movimento->getDescrizioneItem() }}</td>
                        <td class="text-center">{{ $movimento->quantita }}</td>
                        <td class="text-end">€{{ number_format($movimento->costo_unitario, 2, ',', '.') }}</td>
                        <td class="text-end">€{{ number_format($movimento->costo_totale, 2, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        @if($proforma->note)
            <div class="mt-4">
                <h6>Note</h6>
                <p>{{ $proforma->note }}</p>
            </div>
        @endif

        @if($proforma->fattura_note)
            <div class="mt-3">
                <h6>Note fatturazione</h6>
                <p>{{ $proforma->fattura_note }}</p>
            </div>
        @endif
    </div>
</body>
</html>



