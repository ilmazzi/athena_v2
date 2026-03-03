<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DDT Movimentazione - {{ $movimentazione->numero_documento ?? 'MOV-' . $movimentazione->id }}</title>
    @vite(['resources/scss/app.scss'])
    <style>
        @media print {
            @page { margin: 1cm; size: A4; }
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .no-print { display: none; }
        }
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 10mm;
            background: white;
            font-size: 10px;
            line-height: 1.25;
        }
        .box {
            border: 1px solid #000;
            padding: 8px;
            min-height: 60px;
        }
        .box-title {
            font-weight: bold;
            font-size: 10px;
            margin-bottom: 6px;
        }
        .header-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 10px;
            margin-bottom: 10px;
        }
        .header-right {
            display: grid;
            gap: 8px;
        }
        .info-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr;
            gap: 8px;
            margin-bottom: 12px;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            margin: 12px 0;
        }
        .table th,
        .table td {
            border: 1px solid #000;
            padding: 6px;
            text-align: left;
        }
        .table th {
            background-color: #f5f5f5;
            font-weight: bold;
            text-align: center;
        }
        .footer-box {
            border: 1px solid #000;
            margin-top: 12px;
        }
        .footer-row {
            display: grid;
            grid-template-columns: 2fr 1fr;
            border-bottom: 1px solid #000;
        }
        .footer-row > div {
            padding: 6px;
            min-height: 40px;
        }
        .footer-sign {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            border-top: 1px solid #000;
        }
        .footer-sign > div {
            padding: 6px;
            min-height: 50px;
            border-right: 1px solid #000;
        }
        .footer-sign > div:last-child { border-right: 0; }
    </style>
</head>
<body>
    @php
        $numeroDocumento = $movimentazione->numero_documento ?? 'MOV-' . str_pad($movimentazione->id, 6, '0', STR_PAD_LEFT);
        $numeroProgressivo = $numeroDocumento;
        if (preg_match('/^MOV-\d{4}-(\d+)$/', $numeroDocumento, $matches)) {
            $numeroProgressivo = ltrim($matches[1], '0') ?: '1';
        }
        $sedeOrigine = $movimentazione->magazzinoPartenza?->sede;
        $sedeDestinazione = $movimentazione->magazzinoDestinazione?->sede;
    @endphp

    <div class="header-grid">
        <div class="box">
            <div class="box-title">MITTENTE</div>
            <strong>{{ $sedeOrigine->societa->ragione_sociale ?? $sedeOrigine->nome ?? 'N/A' }}</strong><br>
            @if($sedeOrigine)
                {{ $sedeOrigine->indirizzo ?? '' }}<br>
                {{ $sedeOrigine->cap ?? '' }} {{ $sedeOrigine->citta ?? '' }} {{ $sedeOrigine->provincia ?? '' }}<br>
                @if($sedeOrigine->telefono) Tel: {{ $sedeOrigine->telefono }}<br>@endif
                @if($sedeOrigine->email) Email: {{ $sedeOrigine->email }}@endif
            @endif
        </div>
        <div class="header-right">
            <div class="box">
                <div class="box-title">DOCUMENTO DI TRASPORTO</div>
                N.: {{ $numeroProgressivo }}<br>
                Del: {{ $movimentazione->data_movimentazione ? $movimentazione->data_movimentazione->format('d/m/Y') : now()->format('d/m/Y') }}
            </div>
            <div class="box">
                <div class="box-title">DESTINATARIO</div>
                <strong>{{ $sedeDestinazione->societa->ragione_sociale ?? $sedeDestinazione->nome ?? 'N/A' }}</strong><br>
                @if($sedeDestinazione)
                    {{ $sedeDestinazione->indirizzo ?? '' }}<br>
                    {{ $sedeDestinazione->cap ?? '' }} {{ $sedeDestinazione->citta ?? '' }} {{ $sedeDestinazione->provincia ?? '' }}
                @endif
            </div>
        </div>
    </div>

    <div class="info-grid">
        <div class="box">
            <div class="box-title">CAUSALE DEL TRASPORTO</div>
            Movimentazione Interna
        </div>
        <div class="box">
            <div class="box-title">ASPETTO ESTERIORE DEI BENI</div>
        </div>
        <div class="box">
            <div class="box-title">N. COLLI</div>
        </div>
    </div>

    <!-- Dettagli Articoli -->
    <table class="table">
        <thead>
            <tr>
                <th width="10%">Pos.</th>
                <th width="20%">Codice</th>
                <th width="40%">Descrizione</th>
                <th width="10%">Quantità</th>
                <th width="10%">U.M.</th>
                <th width="10%">Magazzino</th>
            </tr>
        </thead>
        <tbody>
            @php
                $righe = $movimentazione->dettagli ?? collect();
                $pfOrder = [];
                $pfRighe = [];
                $righeSingole = [];

                foreach ($righe as $riga) {
                    $pfCode = null;
                    if (!empty($riga->note)) {
                        if (preg_match('/Spostamento componente PF\s+([^\s\-]+)/i', $riga->note, $matches)) {
                            $pfCode = $matches[1];
                        }
                    }

                    if ($pfCode) {
                        if (!isset($pfRighe[$pfCode])) {
                            $pfRighe[$pfCode] = [];
                            $pfOrder[] = $pfCode;
                        }
                        $pfRighe[$pfCode][] = $riga;
                    } else {
                        $righeSingole[] = $riga;
                    }
                }

                $pos = 1;
            @endphp
            @if($righe->count() > 0)
                @foreach($pfOrder as $pfCode)
                    <tr>
                        <td></td>
                        <td colspan="5"><strong>PF {{ $pfCode }} (contiene {{ count($pfRighe[$pfCode]) }} articoli)</strong></td>
                    </tr>
                    @foreach($pfRighe[$pfCode] as $riga)
                        <tr>
                            <td>{{ $pos++ }}</td>
                            <td>{{ $riga->articolo->codice ?? 'N/A' }}</td>
                            <td>{{ $riga->articolo->descrizione ?? 'N/A' }}</td>
                            <td style="text-align: center;">{{ $riga->quantita }}</td>
                            <td style="text-align: center;">PZ</td>
                            <td>{{ $movimentazione->magazzinoPartenza->nome ?? 'N/A' }}</td>
                        </tr>
                    @endforeach
                @endforeach

                @if(count($righeSingole) > 0)
                    <tr>
                        <td></td>
                        <td colspan="5"><strong>Articoli sciolti</strong></td>
                    </tr>
                    @foreach($righeSingole as $riga)
                        <tr>
                            <td>{{ $pos++ }}</td>
                            <td>{{ $riga->articolo->codice ?? 'N/A' }}</td>
                            <td>{{ $riga->articolo->descrizione ?? 'N/A' }}</td>
                            <td style="text-align: center;">{{ $riga->quantita }}</td>
                            <td style="text-align: center;">PZ</td>
                            <td>{{ $movimentazione->magazzinoPartenza->nome ?? 'N/A' }}</td>
                        </tr>
                    @endforeach
                @endif
            @else
                <tr>
                    <td>1</td>
                    <td>{{ $movimentazione->articolo->codice ?? 'N/A' }}</td>
                    <td>{{ $movimentazione->articolo->descrizione ?? 'N/A' }}</td>
                    <td style="text-align: center;">{{ $movimentazione->quantita }}</td>
                    <td style="text-align: center;">PZ</td>
                    <td>{{ $movimentazione->magazzinoPartenza->nome ?? 'N/A' }}</td>
                </tr>
            @endif
        </tbody>
    </table>

    <!-- Note -->
    @if($movimentazione->note)
    <div style="margin: 20px 0;">
        <strong>Note:</strong><br>
        {{ $movimentazione->note }}
    </div>
    @endif

    <!-- Footer con Firme -->
    <div class="footer-box">
        <div class="footer-row">
            <div>
                <strong>TRASPORTO A MEZZO:</strong>
                <span style="margin-left: 8px;">☐ Mittente</span>
                <span style="margin-left: 8px;">☐ Vettore</span>
                <span style="margin-left: 8px;">☐ Destinatario</span>
            </div>
            <div>
                <strong>DATA RITIRO</strong>
            </div>
        </div>
        <div class="footer-row">
            <div><strong>VETTORE:</strong></div>
            <div></div>
        </div>
        <div class="footer-row">
            <div><strong>ANNOTAZIONI</strong></div>
            <div></div>
        </div>
        <div class="footer-sign">
            <div><strong>FIRMA MITTENTE</strong></div>
            <div><strong>FIRMA VETTORE</strong></div>
            <div><strong>FIRMA DESTINATARIO</strong></div>
        </div>
    </div>

    <!-- Pulsanti (solo a schermo) -->
    <div class="no-print" style="text-align: center; margin-top: 30px;">
        <button onclick="window.print()" class="btn btn-primary">
            Stampa DDT
        </button>
        <button onclick="window.close()" class="btn btn-secondary">
            Chiudi
        </button>
    </div>
</body>
</html>
