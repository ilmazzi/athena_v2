<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DDT Deposito {{ $ddtDeposito->numero }}</title>
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
            body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
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
        .print-button {
            position: fixed;
            top: 10px;
            right: 10px;
            padding: 10px 20px;
            cursor: pointer;
            background-color: var(--bs-primary);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 14px;
            z-index: 1000;
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
            font-weight: bold;
        }
        .header .info {
            margin-top: 10px;
            color: var(--bs-secondary);
            font-size: 14px;
        }
        .document-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            padding: 15px;
            background: var(--bs-light);
            border-radius: 5px;
            gap: 20px;
        }
        .info-section {
            flex: 1;
        }
        .info-title {
            margin-bottom: 10px;
            color: var(--bs-primary);
            font-weight: bold;
            font-size: 14px;
            border-bottom: 1px solid var(--bs-border-color);
            padding-bottom: 5px;
        }
        .info-row {
            margin-bottom: 6px;
            font-size: 11px;
        }
        .info-label {
            font-weight: bold;
            display: inline-block;
            width: 90px;
            color: var(--bs-secondary);
        }
        .tipo-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            display: inline-block;
        }
        .tipo-invio {
            background-color: var(--bs-success-bg-subtle);
            color: var(--bs-success-text-emphasis);
        }
        .tipo-reso {
            background-color: var(--bs-warning-bg-subtle);
            color: var(--bs-warning-text-emphasis);
        }
        .tipo-rimando {
            background-color: var(--bs-info-bg-subtle);
            color: var(--bs-info-text-emphasis);
        }
        .status-indicator {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            display: inline-block;
        }
        .status-creato {
            background-color: var(--bs-secondary-bg-subtle);
            color: var(--bs-secondary-text-emphasis);
        }
        .status-stampato {
            background-color: var(--bs-info-bg-subtle);
            color: var(--bs-info-text-emphasis);
        }
        .status-in_transito {
            background-color: var(--bs-warning-bg-subtle);
            color: var(--bs-warning-text-emphasis);
        }
        .status-ricevuto {
            background-color: var(--bs-primary-bg-subtle);
            color: var(--bs-primary-text-emphasis);
        }
        .status-confermato {
            background-color: var(--bs-success-bg-subtle);
            color: var(--bs-success-text-emphasis);
        }
        .status-chiuso {
            background-color: var(--bs-dark);
            color: white;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            margin-bottom: 15px;
        }
        th, td {
            border: 1px solid var(--bs-border-color);
            padding: 8px 6px;
            text-align: left;
            vertical-align: top;
        }
        th {
            background-color: var(--bs-light);
            font-weight: bold;
            font-size: 11px;
            text-align: center;
            color: var(--bs-dark);
        }
        td {
            font-size: 10px;
        }
        tbody tr:nth-child(even) {
            background-color: var(--bs-light);
        }
        .text-center {
            text-align: center;
        }
        .text-right {
            text-align: right;
        }
        .text-primary {
            color: var(--bs-primary);
            font-weight: bold;
        }
        .text-success {
            color: var(--bs-success);
        }
        .text-warning {
            color: var(--bs-warning);
        }
        .summary-box {
            background: var(--bs-light);
            border: 1px solid var(--bs-border-color);
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 11px;
        }
        .summary-total {
            font-weight: bold;
            font-size: 14px;
            border-top: 2px solid var(--bs-dark);
            padding-top: 8px;
            margin-top: 10px;
            color: var(--bs-primary);
        }
        .ddt-footer {
            margin-top: 30px;
            border-top: 2px solid var(--bs-border-color);
            padding-top: 20px;
            display: flex;
            justify-content: space-between;
        }
        .ddt-footer-section {
            width: 48%;
        }
        .ddt-signature-box {
            border: 1px solid var(--bs-border-color);
            height: 60px;
            margin-top: 10px;
            padding: 8px;
            background: white;
        }
        .note-box {
            border: 1px solid var(--bs-border-color);
            padding: 12px;
            background: var(--bs-light);
            margin: 20px 0;
            border-radius: 5px;
        }
        .technical-info {
            margin-top: 30px;
            font-size: 9px;
            color: var(--bs-secondary);
            text-align: center;
            border-top: 1px solid var(--bs-border-color);
            padding-top: 10px;
        }
    </style>
</head>
<body>
    <button class="print-button no-print" onclick="window.print()">
        🖨️ Stampa
    </button>

    {{-- Header --}}
    <div class="header">
        <h1>DOCUMENTO DI TRASPORTO - DEPOSITO</h1>
        <div class="info">
            <strong>{{ $ddtDeposito->numero }}</strong> - 
            {{ $ddtDeposito->data_documento ? $ddtDeposito->data_documento->format('d/m/Y') : 'N/A' }}
            <br>
            <span class="tipo-badge tipo-{{ $ddtDeposito->tipo }}">
                {{ $ddtDeposito->tipo_label }}
            </span>
            <span class="status-indicator status-{{ str_replace('_', '-', $ddtDeposito->stato) }}">
                {{ $ddtDeposito->stato_label }}
            </span>
        </div>
    </div>

    {{-- Informazioni documento --}}
    <div class="document-info">
        <div class="info-section">
            <div class="info-title">📋 Informazioni DDT</div>
            <div class="info-row">
                <span class="info-label">Numero:</span>
                <span class="text-primary">{{ $ddtDeposito->numero }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Data:</span>
                {{ $ddtDeposito->data_documento ? $ddtDeposito->data_documento->format('d/m/Y') : 'N/A' }}
            </div>
            <div class="info-row">
                <span class="info-label">Anno:</span>
                {{ $ddtDeposito->anno }}
            </div>
            @if($ddtDeposito->data_stampa)
                <div class="info-row">
                    <span class="info-label">Stampato:</span>
                    {{ $ddtDeposito->data_stampa->format('d/m/Y H:i') }}
                </div>
            @endif
        </div>

        <div class="info-section">
            <div class="info-title">📦 Conto Deposito</div>
            @if($ddtDeposito->contoDeposito)
                <div class="info-row">
                    <span class="info-label">Codice:</span>
                    <strong>{{ $ddtDeposito->contoDeposito->codice }}</strong>
                </div>
                <div class="info-row">
                    <span class="info-label">Data Invio:</span>
                    {{ $ddtDeposito->contoDeposito->data_invio->format('d/m/Y') }}
                </div>
                <div class="info-row">
                    <span class="info-label">Scadenza:</span>
                    {{ $ddtDeposito->contoDeposito->data_scadenza->format('d/m/Y') }}
                </div>
            @else
                <div class="info-row">
                    <span class="info-label">Deposito:</span>
                    Non specificato
                </div>
            @endif
        </div>

        <div class="info-section">
            <div class="info-title">🏢 Trasporto</div>
            <div class="info-row">
                <span class="info-label">Mittente:</span>
                <strong>{{ $ddtDeposito->sedeMittente->nome }}</strong>
            </div>
            <div class="info-row">
                <span class="info-label">Destinatario:</span>
                <strong>{{ $ddtDeposito->sedeDestinataria->nome }}</strong>
            </div>
            @if($ddtDeposito->causale)
                <div class="info-row">
                    <span class="info-label">Causale:</span>
                    {{ $ddtDeposito->causale }}
                </div>
            @endif
            @if($ddtDeposito->numero_colli)
                <div class="info-row">
                    <span class="info-label">Colli:</span>
                    {{ $ddtDeposito->numero_colli }}
                </div>
            @endif
            @if($ddtDeposito->corriere)
                <div class="info-row">
                    <span class="info-label">Corriere:</span>
                    {{ $ddtDeposito->corriere }}
                </div>
            @endif
            @if($ddtDeposito->numero_tracking)
                <div class="info-row">
                    <span class="info-label">Tracking:</span>
                    <strong>{{ $ddtDeposito->numero_tracking }}</strong>
                </div>
            @endif
        </div>
    </div>

    {{-- Dettagli articoli --}}
    <div>
        <h3 style="color: var(--bs-primary); margin-bottom: 10px; font-size: 14px;">
            📋 Dettagli Articoli ({{ $ddtDeposito->dettagli->count() }} item)
        </h3>
        
        <table>
            <thead>
                <tr>
                    <th style="width: 40px;">#</th>
                    <th style="width: 60px;">Tipo</th>
                    <th style="width: 100px;">Codice</th>
                    <th>Descrizione</th>
                    <th style="width: 60px;" class="text-center">Q.tà</th>
                    <th style="width: 90px;" class="text-right">Valore Unit.</th>
                    <th style="width: 100px;" class="text-right">Valore Tot.</th>
                </tr>
            </thead>
            <tbody>
                @foreach($ddtDeposito->dettagli as $index => $dettaglio)
                    <tr>
                        <td class="text-center">{{ $index + 1 }}</td>
                        <td class="text-center">
                            @if($dettaglio->articolo_id)
                                <span class="tipo-badge" style="background-color: var(--bs-primary-bg-subtle); color: var(--bs-primary-text-emphasis);">ART</span>
                            @else
                                <span class="tipo-badge" style="background-color: var(--bs-warning-bg-subtle); color: var(--bs-warning-text-emphasis);">PF</span>
                            @endif
                        </td>
                        <td><strong class="text-primary">{{ $dettaglio->codice_item }}</strong></td>
                        <td>{{ $dettaglio->descrizione }}</td>
                        <td class="text-center">
                            <strong>{{ $dettaglio->quantita }}</strong>
                        </td>
                        <td class="text-right">€{{ number_format($dettaglio->valore_unitario, 2, ',', '.') }}</td>
                        <td class="text-right">
                            <strong>€{{ number_format($dettaglio->valore_totale, 2, ',', '.') }}</strong>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Riassunto valori --}}
    <div class="summary-box">
        <div class="summary-row">
            <span>Totale Articoli:</span>
            <strong>{{ $ddtDeposito->articoli_totali }}</strong>
        </div>
        <div class="summary-row summary-total">
            <span>Valore Totale Dichiarato:</span>
            <strong>€{{ number_format($ddtDeposito->valore_dichiarato, 2, ',', '.') }}</strong>
        </div>
    </div>

    {{-- Note --}}
    @if($ddtDeposito->note)
        <div class="note-box">
            <h4 style="color: var(--bs-primary); margin-bottom: 8px; font-size: 12px;">📝 Note</h4>
            <div style="font-size: 10px; line-height: 1.4;">
                {{ $ddtDeposito->note }}
            </div>
        </div>
    @endif

    {{-- Footer con firme --}}
    <div class="ddt-footer">
        <div class="ddt-footer-section">
            <div class="info-title">👤 Mittente</div>
            <div style="margin-bottom: 5px;"><strong>{{ $ddtDeposito->sedeMittente->nome }}</strong></div>
            @if($ddtDeposito->sedeMittente->indirizzo)
                <div style="font-size: 9px; margin-bottom: 5px;">{{ $ddtDeposito->sedeMittente->indirizzo }}</div>
            @endif
            @if($ddtDeposito->sedeMittente->citta)
                <div style="font-size: 9px; margin-bottom: 5px;">{{ $ddtDeposito->sedeMittente->citta }} {{ $ddtDeposito->sedeMittente->cap ?? '' }}</div>
            @endif
            @if($ddtDeposito->creatoDa)
                <div style="font-size: 9px; color: var(--bs-secondary); margin-bottom: 5px;">
                    Creato da: {{ $ddtDeposito->creatoDa->name }}
                </div>
            @endif
            <div class="ddt-signature-box">
                <div style="font-size: 9px; color: var(--bs-secondary);">Firma e Timbro:</div>
            </div>
        </div>

        <div class="ddt-footer-section">
            <div class="info-title">📝 Destinatario</div>
            <div style="margin-bottom: 5px;"><strong>{{ $ddtDeposito->sedeDestinataria->nome }}</strong></div>
            @if($ddtDeposito->sedeDestinataria->indirizzo)
                <div style="font-size: 9px; margin-bottom: 5px;">{{ $ddtDeposito->sedeDestinataria->indirizzo }}</div>
            @endif
            @if($ddtDeposito->sedeDestinataria->citta)
                <div style="font-size: 9px; margin-bottom: 5px;">{{ $ddtDeposito->sedeDestinataria->citta }} {{ $ddtDeposito->sedeDestinataria->cap ?? '' }}</div>
            @endif
            @if($ddtDeposito->confermatoDa)
                <div style="font-size: 9px; color: var(--bs-success); margin-bottom: 5px;">
                    ✓ Confermato da: {{ $ddtDeposito->confermatoDa->name }}
                </div>
            @endif
            <div class="ddt-signature-box">
                <div style="font-size: 9px; color: var(--bs-secondary);">Firma per ricevuta:</div>
            </div>
        </div>
    </div>

    {{-- Informazioni tecniche --}}
    <div class="technical-info">
        <div>📄 Documento generato automaticamente da {{ config('app.name', 'Athena v2') }} il {{ now()->format('d/m/Y H:i') }}</div>
        <div>🔒 ID DDT: {{ $ddtDeposito->id }} | Tipo: {{ $ddtDeposito->tipo_label }} | Stato: {{ $ddtDeposito->stato_label }}</div>
    </div>

    <script>
        // Auto-stampa se richiesto
        if (new URLSearchParams(window.location.search).get('auto_print') === '1') {
            window.onload = function() {
                setTimeout(function() {
                    window.print();
                }, 500);
            };
        }
    </script>
</body>
</html>
