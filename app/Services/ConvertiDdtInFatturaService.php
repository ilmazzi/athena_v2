<?php

namespace App\Services;

use App\Models\Articolo;
use App\Models\ArticoloStoricoCosto;
use App\Models\CaricoDettaglio;
use App\Models\Ddt;
use App\Models\DdtDettaglio;
use App\Models\Fattura;
use App\Models\FatturaDettaglio;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ConvertiDdtInFatturaService
{
    public function convert(int $ddtId, array $payload): Fattura
    {
        return DB::transaction(function () use ($ddtId, $payload) {
            /** @var Ddt $ddt */
            $ddt = Ddt::query()
                ->with(['caricoDettagli.articolo', 'dettagli.articolo'])
                ->lockForUpdate()
                ->findOrFail($ddtId);

            if ($ddt->is_fatturato) {
                throw new \RuntimeException('Questo DDT risulta già fatturato.');
            }

            $rows = collect($payload['righe'] ?? [])
                ->map(fn ($row) => $this->normalizeRow($row))
                ->filter(fn ($row) => !empty($row['articolo_id']))
                ->values();

            if ($rows->isEmpty()) {
                throw new \RuntimeException('Non ci sono righe valide da convertire in fattura.');
            }

            $imponibile = $this->normalizeMoney($payload['imponibile'] ?? null);
            $iva = $this->normalizeMoney($payload['iva'] ?? null);
            $totale = $this->normalizeMoney($payload['totale'] ?? null);

            $righeTotale = $rows->sum(fn ($row) => $row['totale_riga']);
            if ($imponibile === null) {
                $imponibile = $righeTotale;
            }
            if ($iva === null) {
                $iva = max(0, round(($totale ?? $righeTotale) - $imponibile, 2));
            }
            if ($totale === null) {
                $totale = round($imponibile + $iva, 2);
            }

            $fattura = Fattura::create([
                'numero' => (string) $payload['numero'],
                'anno' => (int) date('Y', strtotime((string) $payload['data_documento'])),
                'data_documento' => $payload['data_documento'],
                'fornitore_id' => $payload['fornitore_id'] ?: $ddt->fornitore_id,
                'sede_id' => $ddt->sede_id,
                'categoria_id' => $ddt->categoria_id,
                'magazzino_logico' => $ddt->magazzino_logico,
                'stato' => 'caricata',
                'data_carico' => $ddt->data_carico ?? now(),
                'totale' => $totale,
                'imponibile' => $imponibile,
                'iva' => $iva,
                'partita_iva' => $payload['partita_iva'] ?? null,
                'note' => $payload['note'] ?? null,
                'tipo_carico' => 'manuale',
                'ocr_document_id' => $ddt->ocr_document_id,
                'quantita_totale' => (int) $rows->sum('quantita'),
                'numero_articoli' => (int) $rows->count(),
                'ddt_origine_id' => $ddt->id,
            ]);

            foreach ($rows as $row) {
                /** @var Articolo|null $articolo */
                $articolo = Articolo::query()
                    ->withoutGlobalScopes()
                    ->find($row['articolo_id']);

                if (!$articolo) {
                    throw new \RuntimeException("Articolo {$row['articolo_id']} non trovato.");
                }

                FatturaDettaglio::updateOrCreate(
                    [
                        'fattura_id' => $fattura->id,
                        'articolo_id' => $articolo->id,
                    ],
                    [
                        'quantita' => $row['quantita'],
                        'prezzo_unitario' => $row['prezzo_unitario'],
                        'totale_riga' => $row['totale_riga'],
                        'codice_articolo' => $articolo->codice,
                        'descrizione' => $row['descrizione'] ?: $articolo->descrizione,
                        'caricato' => true,
                    ]
                );

                $caricoDettaglio = null;
                if (!empty($row['carico_dettaglio_id'])) {
                    $caricoDettaglio = CaricoDettaglio::query()->find($row['carico_dettaglio_id']);
                }

                if (!$caricoDettaglio) {
                    $caricoDettaglio = CaricoDettaglio::query()
                        ->where('ddt_id', $ddt->id)
                        ->where('articolo_id', $articolo->id)
                        ->first();
                }

                if ($caricoDettaglio) {
                    $caricoDettaglio->update([
                        'fattura_id' => $fattura->id,
                        'descrizione' => $row['descrizione'] ?: $caricoDettaglio->descrizione,
                        'quantita' => $row['quantita'],
                        'prezzo_unitario' => $row['prezzo_unitario'],
                        'prezzo_totale' => $row['totale_riga'],
                    ]);
                }

                DdtDettaglio::query()
                    ->where('ddt_id', $ddt->id)
                    ->where('articolo_id', $articolo->id)
                    ->update([
                        'quantita' => $row['quantita'],
                        'caricato' => true,
                    ]);

                $costoPrecedente = $articolo->prezzo_acquisto;
                $costoNuovo = $row['prezzo_unitario'];

                if ((float) $costoPrecedente !== (float) $costoNuovo) {
                    ArticoloStoricoCosto::create([
                        'articolo_id' => $articolo->id,
                        'costo_precedente' => $costoPrecedente,
                        'costo_nuovo' => $costoNuovo,
                        'fattura_id' => $fattura->id,
                        'user_id' => Auth::id(),
                        'note' => 'Costo aggiornato da conversione DDT ' . $ddt->numero . ' -> Fattura ' . $fattura->numero,
                    ]);
                }

                $articolo->update([
                    'prezzo_acquisto' => $costoNuovo,
                ]);
            }

            $ddt->update([
                'is_fatturato' => true,
                'fatturato_at' => now(),
                'fatturato_da' => Auth::id(),
                'fattura_id' => $fattura->id,
            ]);

            return $fattura->fresh();
        });
    }

    private function normalizeRow(array $row): array
    {
        $quantita = max(1, (int) ($row['quantita'] ?? 1));
        $prezzoUnitario = $this->normalizeMoney($row['prezzo_unitario'] ?? null) ?? 0.0;
        $totaleRiga = $this->normalizeMoney($row['totale_riga'] ?? null);
        if ($totaleRiga === null) {
            $totaleRiga = round($quantita * $prezzoUnitario, 2);
        }

        return [
            'carico_dettaglio_id' => !empty($row['carico_dettaglio_id']) ? (int) $row['carico_dettaglio_id'] : null,
            'articolo_id' => (int) ($row['articolo_id'] ?? 0),
            'descrizione' => trim((string) ($row['descrizione'] ?? '')),
            'quantita' => $quantita,
            'prezzo_unitario' => $prezzoUnitario,
            'totale_riga' => $totaleRiga,
        ];
    }

    private function normalizeMoney($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return round((float) $value, 2);
        }

        $raw = trim((string) $value);
        $raw = preg_replace('/[^\d,.\-]/', '', $raw);
        if ($raw === '') {
            return null;
        }

        if (preg_match('/^\d{1,3}(?:\.\d{3})*(?:,\d+)?$/', $raw) || preg_match('/^\d+(?:,\d+)?$/', $raw)) {
            $raw = str_replace('.', '', $raw);
            $raw = str_replace(',', '.', $raw);
        } elseif (preg_match('/^\d{1,3}(?:,\d{3})*(?:\.\d+)?$/', $raw) || preg_match('/^\d+(?:\.\d+)?$/', $raw)) {
            $raw = str_replace(',', '', $raw);
        }

        return is_numeric($raw) ? round((float) $raw, 2) : null;
    }
}
