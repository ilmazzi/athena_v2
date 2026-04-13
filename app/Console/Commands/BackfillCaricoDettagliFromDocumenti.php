<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillCaricoDettagliFromDocumenti extends Command
{
    protected $signature = 'backfill:carico-dettagli-from-documenti
                            {--dry-run : Simula senza scrivere}
                            {--only= : Limita a ddt oppure fattura}';

    protected $description = 'Crea righe carico_dettagli mancanti a partire da ddt_dettagli/fatture_dettagli per articoli con match univoco';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $only = trim((string) $this->option('only'));

        $runDdt = $only === '' || $only === 'ddt';
        $runFattura = $only === '' || $only === 'fattura';

        if (!$runDdt && !$runFattura) {
            $this->error("Valore --only non valido. Usa 'ddt' oppure 'fattura'.");
            return self::FAILURE;
        }

        $stats = [
            'ddt_candidates' => 0,
            'ddt_inserted' => 0,
            'fattura_candidates' => 0,
            'fattura_inserted' => 0,
            'ambiguous_skipped' => 0,
        ];

        $ambiguousDdt = DB::table('ddt_dettagli')
            ->select('articolo_id', DB::raw('COUNT(*) as righe'))
            ->groupBy('articolo_id')
            ->havingRaw('COUNT(*) > 1')
            ->count();

        $ambiguousFattura = DB::table('fatture_dettagli')
            ->select('articolo_id', DB::raw('COUNT(*) as righe'))
            ->groupBy('articolo_id')
            ->havingRaw('COUNT(*) > 1')
            ->count();

        $stats['ambiguous_skipped'] = $ambiguousDdt + $ambiguousFattura;

        if ($runDdt) {
            $stats['ddt_candidates'] = $this->countDdtCandidates();
        }

        if ($runFattura) {
            $stats['fattura_candidates'] = $this->countFatturaCandidates();
        }

        if (!$dryRun) {
            DB::beginTransaction();
        }

        try {
            if ($runDdt) {
                $stats['ddt_inserted'] = $this->insertDdtRows($dryRun);
            }

            if ($runFattura) {
                $stats['fattura_inserted'] = $this->insertFatturaRows($dryRun);
            }

            if ($dryRun) {
                $this->warn('Dry-run: nessuna modifica salvata.');
            } else {
                DB::commit();
            }
        } catch (\Throwable $e) {
            if (!$dryRun) {
                DB::rollBack();
            }

            throw $e;
        }

        $this->table(
            ['Metric', 'Value'],
            [
                ['ddt_candidates', $stats['ddt_candidates']],
                ['ddt_inserted', $stats['ddt_inserted']],
                ['fattura_candidates', $stats['fattura_candidates']],
                ['fattura_inserted', $stats['fattura_inserted']],
                ['ambiguous_skipped', $stats['ambiguous_skipped']],
            ]
        );

        return self::SUCCESS;
    }

    private function countDdtCandidates(): int
    {
        return DB::table('ddt_dettagli as d')
            ->join('articoli as a', 'a.id', '=', 'd.articolo_id')
            ->leftJoin('carico_dettagli as cd', function ($join) {
                $join->on('cd.articolo_id', '=', 'd.articolo_id')
                    ->on('cd.ddt_id', '=', 'd.ddt_id');
            })
            ->whereNull('a.deleted_at')
            ->whereNull('a.prodotto_finito_id')
            ->where(function ($q) {
                $q->whereNull('a.tipo_carico')
                    ->orWhere('a.tipo_carico', '!=', 'produzione_interna');
            })
            ->whereNull('cd.id')
            ->whereIn('d.articolo_id', function ($q) {
                $q->select('articolo_id')
                    ->from('ddt_dettagli')
                    ->groupBy('articolo_id')
                    ->havingRaw('COUNT(*) = 1');
            })
            ->count();
    }

    private function countFatturaCandidates(): int
    {
        return DB::table('fatture_dettagli as f')
            ->join('articoli as a', 'a.id', '=', 'f.articolo_id')
            ->leftJoin('carico_dettagli as cd', function ($join) {
                $join->on('cd.articolo_id', '=', 'f.articolo_id')
                    ->on('cd.fattura_id', '=', 'f.fattura_id');
            })
            ->whereNull('a.deleted_at')
            ->whereNull('a.prodotto_finito_id')
            ->where(function ($q) {
                $q->whereNull('a.tipo_carico')
                    ->orWhere('a.tipo_carico', '!=', 'produzione_interna');
            })
            ->whereNull('cd.id')
            ->whereIn('f.articolo_id', function ($q) {
                $q->select('articolo_id')
                    ->from('fatture_dettagli')
                    ->groupBy('articolo_id')
                    ->havingRaw('COUNT(*) = 1');
            })
            ->count();
    }

    private function insertDdtRows(bool $dryRun): int
    {
        $inserted = 0;

        DB::table('ddt_dettagli as d')
            ->join('articoli as a', 'a.id', '=', 'd.articolo_id')
            ->leftJoin('carico_dettagli as cd', function ($join) {
                $join->on('cd.articolo_id', '=', 'd.articolo_id')
                    ->on('cd.ddt_id', '=', 'd.ddt_id');
            })
            ->whereNull('a.deleted_at')
            ->whereNull('a.prodotto_finito_id')
            ->where(function ($q) {
                $q->whereNull('a.tipo_carico')
                    ->orWhere('a.tipo_carico', '!=', 'produzione_interna');
            })
            ->whereNull('cd.id')
            ->whereIn('d.articolo_id', function ($q) {
                $q->select('articolo_id')
                    ->from('ddt_dettagli')
                    ->groupBy('articolo_id')
                    ->havingRaw('COUNT(*) = 1');
            })
            ->selectRaw("
                d.ddt_id,
                d.articolo_id,
                COALESCE(JSON_UNQUOTE(JSON_EXTRACT(a.caratteristiche, '$.referenza')), '') as referenza_fornitore,
                COALESCE(NULLIF(d.descrizione, ''), a.descrizione, '') as descrizione,
                COALESCE(d.quantita, 1) as quantita,
                a.numero_seriale,
                a.ean,
                COALESCE(d.prezzo_unitario, a.prezzo_acquisto) as prezzo_unitario,
                CASE
                    WHEN COALESCE(d.prezzo_unitario, a.prezzo_acquisto) IS NULL THEN NULL
                    ELSE COALESCE(d.quantita, 1) * COALESCE(d.prezzo_unitario, a.prezzo_acquisto)
                END as prezzo_totale
            ")
            ->orderBy('d.id')
            ->chunk(1000, function ($rows) use (&$inserted, $dryRun) {
                $payload = [];

                foreach ($rows as $row) {
                    $payload[] = [
                        'ddt_id' => $row->ddt_id,
                        'fattura_id' => null,
                        'articolo_id' => $row->articolo_id,
                        'referenza_fornitore' => $this->normalizeNullableString($row->referenza_fornitore),
                        'descrizione' => $this->normalizeNullableString($row->descrizione) ?? '',
                        'quantita' => (int) ($row->quantita ?? 1),
                        'numero_seriale' => $this->normalizeNullableString($row->numero_seriale),
                        'ean' => $this->normalizeNullableString($row->ean),
                        'prezzo_unitario' => $row->prezzo_unitario,
                        'prezzo_totale' => $row->prezzo_totale,
                        'verificato' => true,
                        'creato_nuovo' => false,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                $inserted += count($payload);

                if (!$dryRun && !empty($payload)) {
                    DB::table('carico_dettagli')->insert($payload);
                }
            });

        return $inserted;
    }

    private function insertFatturaRows(bool $dryRun): int
    {
        $inserted = 0;

        DB::table('fatture_dettagli as f')
            ->join('articoli as a', 'a.id', '=', 'f.articolo_id')
            ->leftJoin('carico_dettagli as cd', function ($join) {
                $join->on('cd.articolo_id', '=', 'f.articolo_id')
                    ->on('cd.fattura_id', '=', 'f.fattura_id');
            })
            ->whereNull('a.deleted_at')
            ->whereNull('a.prodotto_finito_id')
            ->where(function ($q) {
                $q->whereNull('a.tipo_carico')
                    ->orWhere('a.tipo_carico', '!=', 'produzione_interna');
            })
            ->whereNull('cd.id')
            ->whereIn('f.articolo_id', function ($q) {
                $q->select('articolo_id')
                    ->from('fatture_dettagli')
                    ->groupBy('articolo_id')
                    ->havingRaw('COUNT(*) = 1');
            })
            ->selectRaw("
                f.fattura_id,
                f.articolo_id,
                COALESCE(JSON_UNQUOTE(JSON_EXTRACT(a.caratteristiche, '$.referenza')), '') as referenza_fornitore,
                COALESCE(NULLIF(f.descrizione, ''), a.descrizione, '') as descrizione,
                COALESCE(f.quantita, 1) as quantita,
                a.numero_seriale,
                a.ean,
                COALESCE(f.prezzo_unitario, a.prezzo_acquisto) as prezzo_unitario,
                COALESCE(f.totale_riga, COALESCE(f.quantita, 1) * COALESCE(f.prezzo_unitario, a.prezzo_acquisto)) as prezzo_totale
            ")
            ->orderBy('f.id')
            ->chunk(1000, function ($rows) use (&$inserted, $dryRun) {
                $payload = [];

                foreach ($rows as $row) {
                    $payload[] = [
                        'ddt_id' => null,
                        'fattura_id' => $row->fattura_id,
                        'articolo_id' => $row->articolo_id,
                        'referenza_fornitore' => $this->normalizeNullableString($row->referenza_fornitore),
                        'descrizione' => $this->normalizeNullableString($row->descrizione) ?? '',
                        'quantita' => (int) ($row->quantita ?? 1),
                        'numero_seriale' => $this->normalizeNullableString($row->numero_seriale),
                        'ean' => $this->normalizeNullableString($row->ean),
                        'prezzo_unitario' => $row->prezzo_unitario,
                        'prezzo_totale' => $row->prezzo_totale,
                        'verificato' => true,
                        'creato_nuovo' => false,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                $inserted += count($payload);

                if (!$dryRun && !empty($payload)) {
                    DB::table('carico_dettagli')->insert($payload);
                }
            });

        return $inserted;
    }

    private function normalizeNullableString($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed !== '' ? $trimmed : null;
    }
}
