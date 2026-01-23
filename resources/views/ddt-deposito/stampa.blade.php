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
            padding: 10mm;
            background: white;
            font-size: 10px;
            line-height: 1.25;
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
            border-bottom: 1px solid var(--bs-dark);
            padding-bottom: 6px;
            margin-bottom: 8px;
        }
        .header h1 {
            margin: 0;
            font-size: 16px;
            color: var(--bs-dark);
            font-weight: bold;
        }
        .header .info {
            margin-top: 4px;
            color: var(--bs-secondary);
            font-size: 11px;
        }
        .document-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding: 8px 10px;
            background: var(--bs-light);
            border-radius: 4px;
            gap: 10px;
        }
        .info-section {
            flex: 1;
        }
        .info-title {
            margin-bottom: 6px;
            color: var(--bs-primary);
            font-weight: bold;
            font-size: 11px;
            border-bottom: 1px solid var(--bs-border-color);
            padding-bottom: 3px;
        }
        .info-row {
            margin-bottom: 3px;
            font-size: 10px;
        }
        .info-label {
            font-weight: bold;
            display: inline-block;
            width: 75px;
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
            padding: 8px 10px;
            margin: 10px 0;
            border-radius: 4px;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 4px;
            font-size: 10px;
        }
        .summary-total {
            font-weight: bold;
            font-size: 12px;
            border-top: 1px solid var(--bs-dark);
            padding-top: 6px;
            margin-top: 6px;
            color: var(--bs-primary);
        }
        .ddt-footer {
            margin-top: 12px;
            border-top: 1px solid var(--bs-border-color);
            padding-top: 8px;
            display: flex;
            justify-content: space-between;
        }
        .ddt-footer-section {
            width: 48%;
        }
        .ddt-signature-box {
            border: 1px solid var(--bs-border-color);
            height: 45px;
            margin-top: 6px;
            padding: 6px;
            background: white;
        }
        .note-box {
            border: 1px solid var(--bs-border-color);
            padding: 8px 10px;
            background: var(--bs-light);
            margin: 10px 0;
            border-radius: 4px;
        }
        .technical-info {
            margin-top: 12px;
            font-size: 8px;
            color: var(--bs-secondary);
            text-align: center;
            border-top: 1px solid var(--bs-border-color);
            padding-top: 6px;
        }
    </style>
</head>
<body>
    <button class="print-button no-print" onclick="window.print()">
        🖨️ Stampa
    </button>

    {{-- Header --}}
    <div class="header">
        <img src="/images/depascalis_small.svg" alt="De Pascalis" style="height: 32px; margin-bottom: 4px;">
        <h1>DOCUMENTO DI TRASPORTO - DEPOSITO</h1>
        <div class="info">
            <strong>{{ $ddtDeposito->numero }}</strong> - 
            {{ $ddtDeposito->data_documento ? $ddtDeposito->data_documento->format('d/m/Y') : 'N/A' }}
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
                <span class="info-label">Causale:</span>
                {{ $ddtDeposito->causale ?? '—' }}
            </div>
            <div class="info-row">
                <span class="info-label">Aspetto beni:</span>
                {{ $ddtDeposito->configurazione['aspetto_beni'] ?? '—' }}
            </div>
            <div class="info-row">
                <span class="info-label">Colli:</span>
                {{ $ddtDeposito->numero_colli ?? '—' }}
            </div>
            <div class="info-row">
                <span class="info-label">Trasporto:</span>
                {{ $ddtDeposito->configurazione['trasporto_a_mezzo'] ?? $ddtDeposito->configurazione['trasporto_mezzo'] ?? '—' }}
            </div>
            <div class="info-row">
                <span class="info-label">Corriere:</span>
                {{ $ddtDeposito->corriere ?? '—' }}
            </div>
            <div class="info-row">
                <span class="info-label">Tracking:</span>
                <strong>{{ $ddtDeposito->numero_tracking ?? '—' }}</strong>
            </div>
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
                        <td>
                            {{ $dettaglio->descrizione }}
                            @if($dettaglio->prodotto_finito_id && $dettaglio->prodottoFinito)
                                @php
                                    $componenti = ($dettaglio->prodottoFinito->componentiArticoli ?? collect())
                                        ->sortBy(function ($comp) {
                                            return $comp->articolo->codice ?? '';
                                        });
                                @endphp
                                @if($componenti->count() > 0)
                                    <div style="margin-top: 4px; font-size: 9px; color: var(--bs-secondary);">
                                        <div><strong>Componenti:</strong></div>
                                        @foreach($componenti as $comp)
                                            <div>
                                                {{ $comp->articolo->codice ?? 'N/D' }} -
                                                {{ $comp->articolo->descrizione ?? 'N/D' }}
                                                @if(!empty($comp->articolo?->caratura))
                                                    <span style="margin-left: 6px; font-weight: bold;">Carati:</span>
                                                    {{ $comp->articolo->caratura }} ct
                                                @endif
                                                x{{ $comp->quantita ?? 1 }}
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            @endif
                        </td>
                        <td class="text-center">
                            <strong>{{ $dettaglio->quantita }}</strong>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Riassunto --}}
    <div class="summary-box">
        <div class="summary-row">
            <span>Totale Articoli:</span>
            <strong>{{ $ddtDeposito->articoli_totali }}</strong>
        </div>
    </div>

    {{-- Note --}}
    <div class="note-box">
        <h4 style="color: var(--bs-primary); margin-bottom: 8px; font-size: 12px;">📝 Note</h4>
        <div style="font-size: 10px; line-height: 1.4;">
            {{ $ddtDeposito->note ?? '—' }}
        </div>
    </div>

    {{-- Footer con firme --}}
    <div class="ddt-footer">
        <div class="ddt-footer-section">
            <div class="info-title">👤 Mittente</div>
            <div style="margin-bottom: 5px;">
                <strong>{{ $ddtDeposito->sedeMittente->societa->ragione_sociale ?? $ddtDeposito->sedeMittente->nome }}</strong>
            </div>
            @if($ddtDeposito->sedeMittente->societa)
                <div style="font-size: 9px; margin-bottom: 5px;">Sede: {{ $ddtDeposito->sedeMittente->nome }}</div>
            @endif
            @if($ddtDeposito->sedeMittente->indirizzo)
                <div style="font-size: 9px; margin-bottom: 5px;">{{ $ddtDeposito->sedeMittente->indirizzo }}</div>
            @endif
            @if($ddtDeposito->sedeMittente->citta)
                <div style="font-size: 9px; margin-bottom: 5px;">{{ $ddtDeposito->sedeMittente->citta }} {{ $ddtDeposito->sedeMittente->cap ?? '' }}</div>
            @endif
            @if($ddtDeposito->sedeMittente->telefono)
                <div style="font-size: 9px; margin-bottom: 5px;">Tel: {{ $ddtDeposito->sedeMittente->telefono }}</div>
            @endif
            @if($ddtDeposito->sedeMittente->email)
                <div style="font-size: 9px; margin-bottom: 5px;">Email: {{ $ddtDeposito->sedeMittente->email }}</div>
            @endif
            @if($ddtDeposito->sedeMittente->sede_legale || $ddtDeposito->sedeMittente->partita_iva || $ddtDeposito->sedeMittente->codice_fiscale)
                <div style="font-size: 9px; margin-bottom: 5px;">
                    <strong>Sede legale:</strong>
                    {{ $ddtDeposito->sedeMittente->sede_legale ?? '—' }}
                </div>
                <div style="font-size: 9px; margin-bottom: 5px;">
                    {{ $ddtDeposito->sedeMittente->sede_legale_indirizzo ?? '' }}
                    {{ $ddtDeposito->sedeMittente->sede_legale_cap ?? '' }}
                    {{ $ddtDeposito->sedeMittente->sede_legale_citta ?? '' }}
                    {{ $ddtDeposito->sedeMittente->sede_legale_provincia ?? '' }}
                </div>
                <div style="font-size: 9px; margin-bottom: 5px;">
                    P.IVA: {{ $ddtDeposito->sedeMittente->partita_iva ?? '—' }}
                    @if($ddtDeposito->sedeMittente->codice_fiscale)
                        | C.F.: {{ $ddtDeposito->sedeMittente->codice_fiscale }}
                    @endif
                </div>
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
            <div style="margin-bottom: 5px;">
                <strong>{{ $ddtDeposito->sedeDestinataria->societa->ragione_sociale ?? $ddtDeposito->sedeDestinataria->nome }}</strong>
            </div>
            @if($ddtDeposito->sedeDestinataria->societa)
                <div style="font-size: 9px; margin-bottom: 5px;">Sede: {{ $ddtDeposito->sedeDestinataria->nome }}</div>
            @endif
            @if($ddtDeposito->sedeDestinataria->indirizzo)
                <div style="font-size: 9px; margin-bottom: 5px;">{{ $ddtDeposito->sedeDestinataria->indirizzo }}</div>
            @endif
            @if($ddtDeposito->sedeDestinataria->citta)
                <div style="font-size: 9px; margin-bottom: 5px;">{{ $ddtDeposito->sedeDestinataria->citta }} {{ $ddtDeposito->sedeDestinataria->cap ?? '' }}</div>
            @endif
            @if($ddtDeposito->sedeDestinataria->telefono)
                <div style="font-size: 9px; margin-bottom: 5px;">Tel: {{ $ddtDeposito->sedeDestinataria->telefono }}</div>
            @endif
            @if($ddtDeposito->sedeDestinataria->email)
                <div style="font-size: 9px; margin-bottom: 5px;">Email: {{ $ddtDeposito->sedeDestinataria->email }}</div>
            @endif
            @if($ddtDeposito->sedeDestinataria->sede_legale || $ddtDeposito->sedeDestinataria->partita_iva || $ddtDeposito->sedeDestinataria->codice_fiscale)
                <div style="font-size: 9px; margin-bottom: 5px;">
                    <strong>Sede legale:</strong>
                    {{ $ddtDeposito->sedeDestinataria->sede_legale ?? '—' }}
                </div>
                <div style="font-size: 9px; margin-bottom: 5px;">
                    {{ $ddtDeposito->sedeDestinataria->sede_legale_indirizzo ?? '' }}
                    {{ $ddtDeposito->sedeDestinataria->sede_legale_cap ?? '' }}
                    {{ $ddtDeposito->sedeDestinataria->sede_legale_citta ?? '' }}
                    {{ $ddtDeposito->sedeDestinataria->sede_legale_provincia ?? '' }}
                </div>
                <div style="font-size: 9px; margin-bottom: 5px;">
                    P.IVA: {{ $ddtDeposito->sedeDestinataria->partita_iva ?? '—' }}
                    @if($ddtDeposito->sedeDestinataria->codice_fiscale)
                        | C.F.: {{ $ddtDeposito->sedeDestinataria->codice_fiscale }}
                    @endif
                </div>
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
