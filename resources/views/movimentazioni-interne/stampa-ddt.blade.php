<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DDT Movimentazione - {{ $movimentazione->numero_documento ?? 'MOV-' . $movimentazione->id }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            margin: 0;
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #000;
            padding-bottom: 15px;
        }
        .title {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .subtitle {
            font-size: 16px;
            color: #666;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        .info-box {
            border: 1px solid #ccc;
            padding: 10px;
            width: 48%;
        }
        .info-box h4 {
            margin: 0 0 10px 0;
            font-size: 14px;
            font-weight: bold;
            color: #333;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .table th,
        .table td {
            border: 1px solid #ccc;
            padding: 8px;
            text-align: left;
        }
        .table th {
            background-color: #f5f5f5;
            font-weight: bold;
        }
        .footer {
            margin-top: 50px;
            border-top: 1px solid #ccc;
            padding-top: 20px;
        }
        .signature-box {
            border: 1px solid #ccc;
            height: 80px;
            width: 200px;
            display: inline-block;
            margin-right: 50px;
        }
        @media print {
            body { margin: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="title">DOCUMENTO DI TRASPORTO</div>
        <div class="subtitle">Movimentazione Interna</div>
    </div>

    <!-- Informazioni Generali -->
    <div class="info-row">
        <div class="info-box">
            <h4>SEDE ORIGINE</h4>
            <strong>{{ $movimentazione->magazzinoPartenza->sede->nome ?? 'N/A' }}</strong><br>
            {{ $movimentazione->magazzinoPartenza->sede->indirizzo ?? '' }}<br>
            {{ $movimentazione->magazzinoPartenza->sede->citta ?? '' }} {{ $movimentazione->magazzinoPartenza->sede->provincia ?? '' }} {{ $movimentazione->magazzinoPartenza->sede->cap ?? '' }}<br>
            @if($movimentazione->magazzinoPartenza && $movimentazione->magazzinoPartenza->sede && $movimentazione->magazzinoPartenza->sede->telefono)
                Tel: {{ $movimentazione->magazzinoPartenza->sede->telefono }}
            @endif
        </div>
        <div class="info-box">
            <h4>SEDE DESTINAZIONE</h4>
            <strong>{{ $movimentazione->magazzinoDestinazione->sede->nome ?? 'N/A' }}</strong><br>
            {{ $movimentazione->magazzinoDestinazione->sede->indirizzo ?? '' }}<br>
            {{ $movimentazione->magazzinoDestinazione->sede->citta ?? '' }} {{ $movimentazione->magazzinoDestinazione->sede->provincia ?? '' }} {{ $movimentazione->magazzinoDestinazione->sede->cap ?? '' }}<br>
            @if($movimentazione->magazzinoDestinazione && $movimentazione->magazzinoDestinazione->sede && $movimentazione->magazzinoDestinazione->sede->telefono)
                Tel: {{ $movimentazione->magazzinoDestinazione->sede->telefono }}
            @endif
        </div>
    </div>

    <!-- Dettagli DDT -->
    <table class="table">
        <tr>
            <td><strong>Numero DDT:</strong></td>
            <td>{{ $movimentazione->numero_documento ?? 'MOV-' . str_pad($movimentazione->id, 6, '0', STR_PAD_LEFT) }}</td>
            <td><strong>Data:</strong></td>
            <td>{{ $movimentazione->data_movimentazione ? $movimentazione->data_movimentazione->format('d/m/Y') : now()->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td><strong>Operatore:</strong></td>
            <td>{{ $movimentazione->creataDa->name ?? 'Sistema' }}</td>
            <td><strong>Ora:</strong></td>
            <td>{{ now()->format('H:i') }}</td>
        </tr>
    </table>

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
    <div class="footer">
        <div style="display: flex; justify-content: space-between;">
            <div>
                <strong>Firma Mittente</strong><br><br>
                <div class="signature-box"></div><br>
                {{ $movimentazione->magazzinoPartenza->sede->nome ?? 'Sede Origine' }}
            </div>
            <div>
                <strong>Firma Destinatario</strong><br><br>
                <div class="signature-box"></div><br>
                {{ $movimentazione->magazzinoDestinazione->sede->nome ?? 'Sede Destinazione' }}
            </div>
        </div>
        <div style="margin-top: 30px; text-align: center; font-size: 10px; color: #666;">
            Documento generato il {{ now()->format('d/m/Y H:i') }} - Sistema Athena v2
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
