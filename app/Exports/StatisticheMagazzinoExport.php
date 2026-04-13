<?php

namespace App\Exports;

use App\Models\Articolo;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class StatisticheMagazzinoExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths
{
    protected $filtri;
    protected $statistiche;
    
    public function __construct($filtri = [], $statistiche = [])
    {
        $this->filtri = $filtri;
        $this->statistiche = $statistiche;
    }

    public function collection()
    {
        $query = Articolo::with([
            'giacenza',
            'categoriaMerceologica',
            'sede',
            'fatturaDettaglio.fattura.fornitore',
            'ddtDettaglio.ddt.fornitore'
        ])
        ->whereHas('giacenza', function ($q) {
            if ($this->filtri['soloGiacenti'] ?? true) {
                $q->where('quantita_residua', '>', 0);
            }
        });

        if (!empty($this->filtri['sedeId'])) {
            $query->whereHas('giacenza', function ($q) {
                $q->where('sede_id', $this->filtri['sedeId']);
            });
        }

        $categoriaId = $this->filtri['categoriaId'] ?? null;
        $filtroContoDeposito = $this->filtri['filtroContoDeposito'] ?? 'tutti';

        if (!empty($categoriaId)) {
            $prefix = $categoriaId . '-%';

            if ($filtroContoDeposito === 'solo_conto_deposito') {
                $query->where('codice', 'like', $prefix)
                    ->whereNotNull('conto_deposito_corrente_id')
                    ->where('quantita_in_deposito', '>', 0);
            } else {
                $query->where(function ($q) use ($categoriaId, $filtroContoDeposito, $prefix) {
                    $q->where('categoria_merceologica_id', $categoriaId);

                    if ($filtroContoDeposito === 'tutti') {
                        $q->orWhere(function ($cdQ) use ($prefix) {
                            $cdQ->where('codice', 'like', $prefix)
                                ->whereNotNull('conto_deposito_corrente_id')
                                ->where('quantita_in_deposito', '>', 0);
                        });
                    }
                });

                if ($filtroContoDeposito === 'solo_reale') {
                    $query->where(function ($q) {
                        $q->whereNull('conto_deposito_corrente_id')
                            ->orWhere('quantita_in_deposito', '<=', 0);
                    });
                }
            }
        } elseif ($filtroContoDeposito === 'solo_reale') {
            $query->where(function ($q) {
                $q->whereNull('conto_deposito_corrente_id')
                    ->orWhere('quantita_in_deposito', '<=', 0);
            });
        } elseif ($filtroContoDeposito === 'solo_conto_deposito') {
            $query->whereNotNull('conto_deposito_corrente_id')
                ->where('quantita_in_deposito', '>', 0);
        }

        if (!empty($this->filtri['fornitoreId'])) {
            $query->whereNull('prodotto_finito_id')
                ->where(function ($q) {
                $q->whereHas('fatturaDettaglio.fattura', function ($subQ) {
                    $subQ->where('fornitore_id', $this->filtri['fornitoreId']);
                })->orWhereHas('ddtDettaglio.ddt', function ($subQ) {
                    $subQ->where('fornitore_id', $this->filtri['fornitoreId']);
                });
            });
        }

        if (!empty($this->filtri['search'])) {
            $query->where(function ($q) {
                $q->where('codice', 'like', '%' . $this->filtri['search'] . '%')
                  ->orWhere('descrizione', 'like', '%' . $this->filtri['search'] . '%');
            });
        }

        if ($this->filtri['soloSenzaCosto'] ?? false) {
            $query->where(function ($q) {
                $q->whereNull('prezzo_acquisto')
                  ->orWhere('prezzo_acquisto', 0);
            });
        }

        if (!empty($this->filtri['dataDocumentoCaricoPrimaDi'])) {
            $dataLimite = Carbon::parse($this->filtri['dataDocumentoCaricoPrimaDi'])->format('Y-m-d');

            $query->where(function ($q) use ($dataLimite) {
                $q->whereHas('fatturaDettaglio.fattura', function ($subQ) use ($dataLimite) {
                    $subQ->whereDate('data_documento', '<', $dataLimite);
                })->orWhereHas('ddtDettaglio.ddt', function ($subQ) use ($dataLimite) {
                    $subQ->whereDate('data_documento', '<', $dataLimite);
                })->orWhere(function ($subQ) use ($dataLimite) {
                    $subQ->whereDate('data_carico', '<', $dataLimite)
                        ->whereHas('ddtDettaglio.ddt', function ($ddtQ) {
                            $ddtQ->where('numero', 'like', 'LEGACY-%');
                        });
                });
            });
        }

        return $query->orderBy('codice')->get();
    }

    public function headings(): array
    {
        return [
            'Codice',
            'Descrizione',
            'Sede',
            'Categoria',
            'Fornitore',
            'Quantità',
            'Costo Unit.',
            'Valore Totale',
            'Data Carico',
        ];
    }

    public function map($articolo): array
    {
        $qta = $articolo->giacenza->quantita_residua ?? 0;
        $costo = $articolo->prezzo_acquisto ?? 0;
        $valore = $qta * $costo;
        $isProduzioneInterna = !empty($articolo->prodotto_finito_id) || $articolo->tipo_carico_effettivo === 'produzione_interna';
        $fornitoreLabel = $isProduzioneInterna
            ? 'Produzione interna'
            : ($articolo->fornitore?->ragione_sociale ?? 'N/A');
        $dataRiferimento = $isProduzioneInterna
            ? ($articolo->assemblato_il ?? $articolo->created_at ?? $articolo->data_carico_effettiva)
            : $articolo->data_carico_effettiva;

        return [
            $articolo->codice,
            $articolo->descrizione,
            $articolo->giacenza->sede->nome ?? 'N/A',
            $articolo->categoriaMerceologica->nome ?? 'N/A',
            $fornitoreLabel,
            $qta,
            $costo > 0 ? number_format($costo, 2, ',', '.') : '-',
            $valore > 0 ? number_format($valore, 2, ',', '.') : '-',
            $dataRiferimento?->format('d/m/Y') ?? '-',
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 20,  // Codice
            'B' => 40,  // Descrizione
            'C' => 15,  // Sede
            'D' => 20,  // Categoria
            'E' => 30,  // Fornitore
            'F' => 10,  // Quantità
            'G' => 12,  // Costo Unit.
            'H' => 15,  // Valore Totale
            'I' => 12,  // Data Carico
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'size' => 12],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E3F2FD']
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
        ];
    }
}
