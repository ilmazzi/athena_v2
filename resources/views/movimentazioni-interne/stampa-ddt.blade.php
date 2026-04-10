<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DDT Movimentazione {{ $movimentazione->numero_documento ?? 'MOV-' . $movimentazione->id }}</title>
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
        .print-actions {
            position: fixed;
            top: 10px;
            right: 10px;
            display: flex;
            flex-direction: column;
            gap: 6px;
            z-index: 1000;
        }
        .print-button {
            padding: 10px 20px;
            cursor: pointer;
            background-color: var(--bs-primary);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 14px;
            text-decoration: none;
            text-align: center;
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
        .text-primary {
            color: var(--bs-primary);
            font-weight: bold;
        }
        .summary-box {
            background: var(--bs-light);
            border: 1px solid var(--bs-border-color);
            padding: 10px;
            margin-bottom: 10px;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 3px;
            font-size: 10px;
        }
        .note-box {
            background: white;
            border: 1px solid var(--bs-border-color);
            padding: 10px;
            margin-bottom: 10px;
        }
        .ddt-footer {
            display: flex;
            gap: 10px;
        }
        .ddt-footer-section {
            flex: 1;
            border: 1px solid var(--bs-border-color);
            padding: 10px;
        }
        .ddt-signature-box {
            margin-top: 10px;
            height: 50px;
            border: 1px dashed var(--bs-border-color);
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
    <div class="print-actions no-print">
        <button class="print-button" onclick="window.print()">
            🖨️ Stampa
        </button>
    </div>

    @php
        $numeroDocumento = $movimentazione->numero_documento ?? 'MOV-' . str_pad($movimentazione->id, 6, '0', STR_PAD_LEFT);
        $numeroProgressivo = $numeroDocumento;
        if (preg_match('/^MOV-\d{4}-(\d+)$/', $numeroDocumento, $matches)) {
            $numeroProgressivo = ltrim($matches[1], '0') ?: '1';
        }
        $canInferSedeFromCategorie = $movimentazione->magazzino_partenza_id !== $movimentazione->magazzino_destinazione_id;
        $sedeOrigine = $movimentazione->sedePartenza ?? ($canInferSedeFromCategorie ? $movimentazione->magazzinoPartenza?->sede : null);
        $sedeDestinazione = $movimentazione->sedeDestinazione ?? ($canInferSedeFromCategorie ? $movimentazione->magazzinoDestinazione?->sede : null);
        $mittenteLuogo = $movimentazione->magazzinoPartenza;
        $destinatarioLuogo = $movimentazione->magazzinoDestinazione;
        $mittente = $sedeOrigine ?? $mittenteLuogo;
        $destinatario = $sedeDestinazione ?? $destinatarioLuogo;
        $mittenteIndirizzo = $mittenteLuogo?->indirizzo ?? $sedeOrigine?->indirizzo;
        $mittenteCitta = $mittenteLuogo?->citta ?? $sedeOrigine?->citta;
        $mittenteCap = $mittenteLuogo?->cap ?? $sedeOrigine?->cap;
        $mittenteTelefono = $mittenteLuogo?->telefono ?? $sedeOrigine?->telefono;
        $mittenteEmail = $mittenteLuogo?->email ?? $sedeOrigine?->email;
        $destinatarioIndirizzo = $destinatarioLuogo?->indirizzo ?? $sedeDestinazione?->indirizzo;
        $destinatarioCitta = $destinatarioLuogo?->citta ?? $sedeDestinazione?->citta;
        $destinatarioCap = $destinatarioLuogo?->cap ?? $sedeDestinazione?->cap;
        $destinatarioTelefono = $destinatarioLuogo?->telefono ?? $sedeDestinazione?->telefono;
        $destinatarioEmail = $destinatarioLuogo?->email ?? $sedeDestinazione?->email;
        $righe = $movimentazione->dettagli ?? collect();
    @endphp

    <div class="header">
        <img src="/images/depascalis_small.svg" alt="De Pascalis" style="height: 32px; margin-bottom: 4px;">
        <h1>DOCUMENTO DI TRASPORTO - MOVIMENTAZIONE</h1>
        <div class="info">
            <strong>{{ $numeroProgressivo }}</strong> -
            {{ $movimentazione->data_movimentazione ? $movimentazione->data_movimentazione->format('d/m/Y') : 'N/A' }}
            <span class="tipo-badge" style="background-color: var(--bs-info-bg-subtle); color: var(--bs-info-text-emphasis);">INTERNA</span>
        </div>
    </div>

    <div class="document-info">
        <div class="info-section">
            <div class="info-title">📋 Informazioni DDT</div>
            <div class="info-row">
                <span class="info-label">Numero:</span>
                <span class="text-primary">{{ $numeroProgressivo }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Data:</span>
                {{ $movimentazione->data_movimentazione ? $movimentazione->data_movimentazione->format('d/m/Y') : 'N/A' }}
            </div>
            <div class="info-row">
                <span class="info-label">Anno:</span>
                {{ $movimentazione->data_movimentazione ? $movimentazione->data_movimentazione->format('Y') : now()->format('Y') }}
            </div>
            <div class="info-row">
                <span class="info-label">Creato:</span>
                {{ $movimentazione->created_at ? $movimentazione->created_at->format('d/m/Y H:i') : '—' }}
            </div>
        </div>

        <div class="info-section">
            <div class="info-title">📦 Movimentazione</div>
            <div class="info-row">
                <span class="info-label">Causale:</span>
                Movimentazione Interna
            </div>
    <div class="info-row">
                <span class="info-label">Operatore:</span>
                {{ $movimentazione->creataDa->name ?? 'Sistema' }}
            </div>
        </div>

        <div class="info-section">
            <div class="info-title">🏢 Trasporto</div>
            <div class="info-row">
                <span class="info-label">Aspetto beni:</span>
                {{ $movimentazione->aspetto_beni ?? '—' }}
            </div>
            <div class="info-row">
                <span class="info-label">Colli:</span>
                {{ $movimentazione->colli ?? '—' }}
            </div>
            <div class="info-row">
                <span class="info-label">Trasporto:</span>
                {{ $movimentazione->trasporto_mezzo ?? '—' }}
            </div>
            <div class="info-row">
                <span class="info-label">Vettore:</span>
                {{ $movimentazione->vettore ?? '—' }}
            </div>
        </div>
    </div>

    <div>
        <h3 style="color: var(--bs-primary); margin-bottom: 10px; font-size: 14px;">
            📋 Dettagli Articoli ({{ $righe->count() }} item)
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
                @php
                    $pfGroups = [];
                    $pfOrder = [];
                    $righeArticoli = [];

                    foreach ($righe as $dettaglio) {
                        if ($dettaglio->prodottoFinito) {
                            $pfId = $dettaglio->prodottoFinito->id;
                            if (!isset($pfGroups[$pfId])) {
                                $pfGroups[$pfId] = [
                                    'pf' => $dettaglio->prodottoFinito,
                                    'quantita' => null,
                                ];
                                $pfOrder[] = $pfId;
                            }
                            if ($dettaglio->articolo_id === $dettaglio->prodottoFinito->articolo_risultante_id) {
                                $pfGroups[$pfId]['quantita'] = (int) $dettaglio->quantita;
                            }
                            continue;
                        }

                        $pfCode = null;
                        $pfDescrizione = null;
                        if (!empty($dettaglio->note)) {
                            if (preg_match('/Spostamento componente PF\s+([^\|\s]+)\s*\|\s*([^\\-]+)?/i', $dettaglio->note, $matches)) {
                                $pfCode = trim($matches[1]);
                                $pfDescrizione = isset($matches[2]) ? trim($matches[2]) : null;
                            }
                        }

                        if ($pfCode) {
                            if (!isset($pfGroups[$pfCode])) {
                                $pfGroups[$pfCode] = [
                                    'codice' => $pfCode,
                                    'descrizione' => $pfDescrizione,
                                    'componenti' => [],
                                    'quantita' => 0,
                                ];
                                $pfOrder[] = $pfCode;
                            }
                            $pfGroups[$pfCode]['componenti'][] = $dettaglio;
                            $pfGroups[$pfCode]['quantita'] = $pfGroups[$pfCode]['quantita'] ?? (int) $dettaglio->quantita;
                        } else {
                            $righeArticoli[] = $dettaglio;
                        }
                    }

                    $index = 1;
                @endphp

                @foreach($pfOrder as $pfKey)
                    @php($pfRow = $pfGroups[$pfKey])
                    <tr>
                        <td class="text-center">{{ $index++ }}</td>
                        <td class="text-center">
                            <span class="tipo-badge" style="background-color: var(--bs-warning-bg-subtle); color: var(--bs-warning-text-emphasis);">PF</span>
                        </td>
                        <td>
                            <strong class="text-primary">
                                {{ $pfRow['pf']->codice ?? $pfRow['codice'] ?? 'PF' }}
                            </strong>
                        </td>
                        <td>
                            {{ $pfRow['pf']->descrizione ?? $pfRow['descrizione'] ?? 'Prodotto finito' }}
                            <div style="margin-top: 4px; font-size: 9px; color: var(--bs-secondary);">
                                <div><strong>Componenti:</strong></div>
                                @if(!empty($pfRow['pf']))
                                    @foreach($pfRow['pf']->componentiArticoli as $comp)
                                        <div>
                                            {{ $comp->articolo->codice ?? 'N/D' }} -
                                            {{ $comp->articolo->descrizione ?? 'N/D' }}
                                            x{{ $comp->quantita }}
                                        </div>
                                    @endforeach
                                @else
                                    @foreach(($pfRow['componenti'] ?? []) as $comp)
                                        <div>
                                            {{ $comp->articolo->codice ?? 'N/D' }} -
                                            {{ $comp->articolo->descrizione ?? 'N/D' }}
                                            x{{ $comp->quantita }}
                                        </div>
                                    @endforeach
                                @endif
                            </div>
                        </td>
                        <td class="text-center"><strong>{{ $pfRow['quantita'] ?? 1 }}</strong></td>
                    </tr>
                @endforeach

                @foreach($righeArticoli as $dettaglio)
                    <tr>
                        <td class="text-center">{{ $index++ }}</td>
                        <td class="text-center">
                            <span class="tipo-badge" style="background-color: var(--bs-primary-bg-subtle); color: var(--bs-primary-text-emphasis);">ART</span>
                        </td>
                        <td><strong class="text-primary">{{ $dettaglio->articolo->codice ?? 'N/D' }}</strong></td>
                        <td>{{ $dettaglio->articolo->descrizione ?? 'N/D' }}</td>
                        <td class="text-center"><strong>{{ $dettaglio->quantita }}</strong></td>
            </tr>
                @endforeach
        </tbody>
    </table>
    </div>

    <div class="summary-box">
        <div class="summary-row">
            <span>Totale Articoli:</span>
            <strong>{{ $righe->count() }}</strong>
        </div>
    </div>

    <div class="note-box">
        <h4 style="color: var(--bs-primary); margin-bottom: 8px; font-size: 12px;">📝 Note</h4>
        <div style="font-size: 10px; line-height: 1.4;">
            {{ $movimentazione->note ?? '—' }}
        </div>
    </div>

    <div class="ddt-footer">
        <div class="ddt-footer-section">
            <div class="info-title">👤 Mittente</div>
            <div style="margin-bottom: 5px;">
                <strong>
                    {{ $sedeOrigine?->societa?->ragione_sociale ?? $mittente?->nome ?? 'N/A' }}
                </strong>
            </div>
            @if($sedeOrigine?->societa || $mittenteLuogo?->nome)
                <div style="font-size: 9px; margin-bottom: 5px;">
                    Sede: {{ $sedeOrigine?->nome ?? $mittenteLuogo?->nome }}
                </div>
            @endif
            @if($mittenteIndirizzo)
                <div style="font-size: 9px; margin-bottom: 5px;">{{ $mittenteIndirizzo }}</div>
            @endif
            @if($mittenteCitta)
                <div style="font-size: 9px; margin-bottom: 5px;">{{ $mittenteCitta }} {{ $mittenteCap ?? '' }}</div>
            @endif
            @if($mittenteTelefono)
                <div style="font-size: 9px; margin-bottom: 5px;">Tel: {{ $mittenteTelefono }}</div>
            @endif
            @if($mittenteEmail)
                <div style="font-size: 9px; margin-bottom: 5px;">Email: {{ $mittenteEmail }}</div>
            @endif
            @if($sedeOrigine?->sede_legale || $sedeOrigine?->partita_iva || $sedeOrigine?->codice_fiscale)
                <div style="font-size: 9px; margin-bottom: 5px;">
                    <strong>Sede legale:</strong> {{ $sedeOrigine->sede_legale ?? '—' }}
                </div>
                <div style="font-size: 9px; margin-bottom: 5px;">
                    {{ $sedeOrigine->sede_legale_indirizzo ?? '' }}
                    {{ $sedeOrigine->sede_legale_cap ?? '' }}
                    {{ $sedeOrigine->sede_legale_citta ?? '' }}
                    {{ $sedeOrigine->sede_legale_provincia ?? '' }}
                </div>
                <div style="font-size: 9px; margin-bottom: 5px;">
                    P.IVA: {{ $sedeOrigine->partita_iva ?? '—' }}
                    @if($sedeOrigine?->codice_fiscale)
                        | C.F.: {{ $sedeOrigine->codice_fiscale }}
                    @endif
    </div>
    @endif
            <div class="ddt-signature-box">
                <div style="font-size: 9px; color: var(--bs-secondary);">Firma e Timbro:</div>
            </div>
        </div>

        <div class="ddt-footer-section">
            <div class="info-title">📝 Destinatario</div>
            <div style="margin-bottom: 5px;">
                <strong>
                    {{ $sedeDestinazione?->societa?->ragione_sociale ?? $destinatario?->nome ?? 'N/A' }}
                </strong>
            </div>
            @if($sedeDestinazione?->societa || $destinatarioLuogo?->nome)
                <div style="font-size: 9px; margin-bottom: 5px;">
                    Sede: {{ $sedeDestinazione?->nome ?? $destinatarioLuogo?->nome }}
                </div>
            @endif
            @if($destinatarioIndirizzo)
                <div style="font-size: 9px; margin-bottom: 5px;">{{ $destinatarioIndirizzo }}</div>
            @endif
            @if($destinatarioCitta)
                <div style="font-size: 9px; margin-bottom: 5px;">{{ $destinatarioCitta }} {{ $destinatarioCap ?? '' }}</div>
            @endif
            @if($destinatarioTelefono)
                <div style="font-size: 9px; margin-bottom: 5px;">Tel: {{ $destinatarioTelefono }}</div>
            @endif
            @if($destinatarioEmail)
                <div style="font-size: 9px; margin-bottom: 5px;">Email: {{ $destinatarioEmail }}</div>
            @endif
            @if($sedeDestinazione?->sede_legale || $sedeDestinazione?->partita_iva || $sedeDestinazione?->codice_fiscale)
                <div style="font-size: 9px; margin-bottom: 5px;">
                    <strong>Sede legale:</strong> {{ $sedeDestinazione->sede_legale ?? '—' }}
                </div>
                <div style="font-size: 9px; margin-bottom: 5px;">
                    {{ $sedeDestinazione->sede_legale_indirizzo ?? '' }}
                    {{ $sedeDestinazione->sede_legale_cap ?? '' }}
                    {{ $sedeDestinazione->sede_legale_citta ?? '' }}
                    {{ $sedeDestinazione->sede_legale_provincia ?? '' }}
                </div>
                <div style="font-size: 9px; margin-bottom: 5px;">
                    P.IVA: {{ $sedeDestinazione->partita_iva ?? '—' }}
                    @if($sedeDestinazione?->codice_fiscale)
                        | C.F.: {{ $sedeDestinazione->codice_fiscale }}
                    @endif
                </div>
            @endif
            <div class="ddt-signature-box">
                <div style="font-size: 9px; color: var(--bs-secondary);">Firma per ricevuta:</div>
            </div>
        </div>
    </div>

    <div class="ddt-footer" style="margin-top: 8px;">
        <div class="ddt-footer-section">
            <div class="info-title">🕒 Creazione documento</div>
            <div class="info-row">
                <span class="info-label">Creato il:</span>
                {{ $movimentazione->created_at ? $movimentazione->created_at->format('d/m/Y H:i') : '—' }}
            </div>
        </div>
        <div class="ddt-footer-section">
            <div class="info-title">🚚 Ritiro corriere</div>
            <div class="info-row">
                <span class="info-label">Data ritiro:</span>
                ____________________
            </div>
            <div class="info-row">
                <span class="info-label">Ora ritiro:</span>
                ____________________
            </div>
            <div class="ddt-signature-box">
                <div style="font-size: 9px; color: var(--bs-secondary);">Timbro corriere:</div>
            </div>
        </div>
    </div>

    <div class="technical-info">
        <div>📄 Documento generato automaticamente da {{ config('app.name', 'Athena v2') }} il {{ now()->format('d/m/Y H:i') }}</div>
        <div>🔒 ID MOV: {{ $movimentazione->id }} | Tipo: Movimentazione Interna</div>
    </div>
</body>
</html>
