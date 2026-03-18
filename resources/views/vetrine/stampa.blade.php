<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stampa {{ $vetrina->nome }}</title>
    @vite(['resources/scss/app.scss'])
    <style>
        @media print {
            @page {
                margin: 1cm;
                size: A4;
            }
            
            body {
                font-family: Arial, sans-serif;
                font-size: 12px;
                line-height: 1.4;
            }
            
            .no-print {
                display: none !important;
            }
        }
        
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 15mm;
            background: white;
            font-size: 11px;
            line-height: 1.3;
        }
        
        .header {
            text-align: center;
            border-bottom: 2px solid var(--bs-dark);
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        
        .header h1 {
            margin: 0;
            font-size: 24px;
            color: var(--bs-dark);
        }
        
        .header .info {
            margin-top: 10px;
            color: var(--bs-secondary);
            font-size: 14px;
        }
        
        .stats {
            display: flex;
            justify-content: space-around;
            margin-bottom: 20px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-value {
            font-size: 18px;
            font-weight: bold;
            color: var(--bs-primary);
        }
        
        .stat-label {
            font-size: 12px;
            color: var(--bs-secondary);
            margin-top: 2px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        th, td {
            border: 1px solid var(--bs-border-color);
            padding: 10px 8px;
            text-align: left;
            vertical-align: top;
        }
        
        th {
            background-color: var(--bs-light);
            font-weight: bold;
            font-size: 12px;
            text-align: center;
        }
        
        td {
            font-size: 11px;
        }
        
        .qr-code {
            text-align: center;
            width: 80px;
        }
        
        .qr-code img {
            width: 60px;
            height: 60px;
        }
        
        .codice {
            font-weight: bold;
            color: #007bff;
        }
        
        .prezzo {
            font-weight: bold;
            color: #28a745;
            text-align: right;
        }
        
        .testo-vetrina {
            max-width: 200px;
            word-wrap: break-word;
        }
        
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 10px;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }
        
        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .print-button:hover {
            background: #0056b3;
        }
    </style>
</head>
<body>
    <button class="print-button no-print" onclick="window.print()">
        🖨️ Stampa
    </button>

    <div class="header">
        <h1>{{ strtoupper($vetrina->nome) }}</h1>
        <div class="info">
            <strong>{{ $vetrina->getTipologiaLabel() }}</strong>
            @if($vetrina->sede)
                | {{ $vetrina->sede->nome }}
            @endif
            | Codice: {{ $vetrina->codice }}
            <br>
            Stampato il: {{ now()->format('d/m/Y H:i') }}
        </div>
    </div>

    <div class="stats">
        <div class="stat-item">
            <div class="stat-value">{{ $articoli->count() }}</div>
            <div class="stat-label">Articoli</div>
        </div>
        <div class="stat-item">
            <div class="stat-value">{{ $articoli->whereNotNull('prezzo_vetrina')->where('prezzo_vetrina', '!=', '')->count() }}</div>
            <div class="stat-label">Prezzi Codificati</div>
        </div>
        <div class="stat-item">
            <div class="stat-value">{{ $articoli->avg('giorni_esposizione') ? round($articoli->avg('giorni_esposizione')) : 0 }}</div>
            <div class="stat-label">Giorni Medi</div>
        </div>
    </div>

    @if($articoli->count() > 0)
        <table>
            <thead>
                <tr>
                    <th style="width: 80px;">QR Code</th>
                    <th style="width: 100px;">Codice</th>
                    <th>Descrizione</th>
                    <th style="width: 100px;">Prezzo</th>
                </tr>
            </thead>
            <tbody>
                @foreach($articoli as $articoloVetrina)
                    <tr>
                        <td class="qr-code">
                            @if($articoloVetrina->qr_code_base64)
                                <img src="data:image/png;base64,{{ $articoloVetrina->qr_code_base64 }}"
                                     alt="QR {{ $articoloVetrina->codice_display }}">
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td style="text-align: center; font-size: 12px; font-weight: bold; color: var(--bs-primary);">
                            {{ $articoloVetrina->codice_display }}
                        </td>
                        <td style="font-size: 12px;">
                            @if($articoloVetrina->testo_vetrina)
                                <div style="font-weight: bold; margin-bottom: 2px;">{{ $articoloVetrina->testo_vetrina }}</div>
                            @else
                                <div style="font-weight: bold; margin-bottom: 2px;">{{ Str::limit($articoloVetrina->descrizione_display, 50) }}</div>
                            @endif
                            @php
                                $pfId = $articoloVetrina->prodotto_finito_id
                                    ?? $articoloVetrina->articolo?->prodotto_finito_id;
                                $pfComponenti = $pfId
                                    ? ($componentiByPfId[$pfId] ?? collect())
                                    : collect();
                                $componenti = $pfComponenti
                                    ->map(function ($c) {
                                        return $c->articolo_codice
                                            ?: $c->articolo_descrizione
                                            ?: ('ID ' . $c->articolo_id);
                                    })
                                    ->filter()
                                    ->take(8)
                                    ->implode(', ');
                            @endphp
                            @if($pfId && $componenti)
                                <div style="font-size: 11px; color: var(--bs-dark); margin-top: 4px; font-weight: 600;">
                                    Componenti: {{ $componenti }}
                                </div>
                            @endif
                            <div style="font-size: 9px; color: var(--bs-secondary);">
                                @php
                                    $giorni = \Carbon\Carbon::parse($articoloVetrina->data_inserimento)->diffInDays(now());
                                @endphp
                                @if($giorni >= 1)
                                    {{ intval($giorni) }} gg in vetrina
                                @endif
                            </div>
                        </td>
                        <td style="text-align: center; font-weight: bold; font-size: 14px; color: var(--bs-success);">
                            @if(is_numeric($articoloVetrina->prezzo_vetrina))
                                €{{ number_format((float)$articoloVetrina->prezzo_vetrina, 0, ',', '.') }}
                            @else
                                {{ $articoloVetrina->prezzo_vetrina }}
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div style="text-align: center; padding: 50px; color: #666;">
            <h3>Nessun articolo in vetrina</h3>
        </div>
    @endif

    <div class="footer">
        Athena v2 - Sistema di Gestione Magazzino<br>
        Vetrina {{ $vetrina->codice }} - {{ $articoli->count() }} articoli -
        Prezzi codificati: {{ $articoli->whereNotNull('prezzo_vetrina')->where('prezzo_vetrina', '!=', '')->count() }}
    </div>

    <script>
        // Auto-stampa se richiesto
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('auto_print') === '1') {
            window.onload = function() {
                setTimeout(() => {
                    window.print();
                }, 500);
            };
        }
    </script>
</body>
</html>
