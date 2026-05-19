<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DiagnosticaExport implements
    FromCollection,
    WithHeadings,
    ShouldAutoSize,
    WithStyles,
    WithColumnFormatting
{
    public function __construct(
        private readonly Collection $rows,
        private readonly array $colonneSelezionate,
        private readonly array $tutteLeColonne,
    ) {}

    public function headings(): array
    {
        $headers = [];
        foreach ($this->colonneSelezionate as $col) {
            $headers[] = $this->tutteLeColonne[$col] ?? $col;
        }
        // Aggiungi sempre colonna anomalie
        $headers[] = 'Anomalie rilevate';
        return $headers;
    }

    public function collection(): Collection
    {
        return $this->rows->map(function ($row) {
            $caratteristiche = $row->caratteristiche ? json_decode($row->caratteristiche, true) : [];
            $qtaRes  = (int) ($row->quantita_residua ?? 0);
            $deleted = !empty($row->deleted_at);

            $valori = [];
            foreach ($this->colonneSelezionate as $col) {
                $valori[] = match ($col) {
                    'codice'           => $row->codice,
                    'descrizione'      => $row->descrizione,
                    'mag'              => (int) explode('-', $row->codice)[0],
                    'categoria'        => ($row->categoria_codice ?? '') . ' ' . ($row->categoria_nome ?? ''),
                    'sede'             => $row->sede_nome ?? '',
                    'qta'              => (int) ($row->quantita ?? 0),
                    'qta_residua'      => $qtaRes,
                    'stato_articolo'   => $row->stato_articolo ?? '',
                    'stato'            => $row->stato ?? '',
                    'materiale'        => $row->materiale ?? '',
                    'colore'           => $row->colore ?? '',
                    'titolo'           => $row->titolo ?? '',
                    'caratura'         => $row->caratura ?? '',
                    'numero_seriale'   => $row->numero_seriale ?? '',
                    'ean'              => $row->ean ?? '',
                    'modello'          => $row->modello ?? '',
                    'fornitore'        => $row->fornitore_nome ?? '',
                    'referenza_doc'    => $row->referenza_doc ?? '',
                    'prezzo_carico'    => $row->prezzo_carico !== null ? (float) $row->prezzo_carico : '',
                    'num_documento'    => $row->num_documento ?? '',
                    'data_documento'   => $row->data_documento ?? '',
                    'referenza'        => $caratteristiche['referenza'] ?? '',
                    'prezzo_acquisto'  => (float) ($row->prezzo_acquisto ?? 0),
                    'prezzo_fornitore' => (float) ($row->prezzo_fornitore ?? 0),
                    'costo_unitario'   => (float) ($row->costo_unitario ?? 0),
                    'tipo_carico'      => $row->tipo_carico ?? '',
                    'data_carico'      => $row->data_carico ?? '',
                    'scaffale'         => $row->scaffale ?? '',
                    'posizione'        => $row->posizione ?? '',
                    'magazzino_logico' => $row->magazzino_logico ?? '',
                    'deleted_at'       => $row->deleted_at ?? '',
                    'created_at'       => $row->created_at ?? '',
                    'note'             => $row->note ?? '',
                    default            => '',
                };
            }

            // Colonna anomalie
            $anomalie = [];
            if ($deleted && $qtaRes > 0) {
                $anomalie[] = 'ELIMINATO CON QTA>0';
            }
            if (!$deleted && $qtaRes <= 0) {
                $anomalie[] = 'Qta=0 non eliminato';
            }
            if ($qtaRes > 1) {
                $anomalie[] = 'Qta>1';
            }
            if (is_null($row->quantita ?? null)) {
                $anomalie[] = 'Senza giacenza';
            }
            if (empty($row->fornitore_nome)) {
                $anomalie[] = 'No fornitore';
            }
            if (empty($caratteristiche['referenza'])) {
                $anomalie[] = 'No referenza';
            }
            if (empty($row->numero_seriale)) {
                $anomalie[] = 'No seriale';
            }
            // Mismatch categoria/mag
            $magCodice = (int) explode('-', $row->codice)[0];
            $catNum = (int) preg_replace('/[^0-9]/', '', $row->categoria_codice ?? '');
            if ($catNum > 0 && $magCodice !== $catNum) {
                $anomalie[] = 'CAT/MAG mismatch';
            }

            $valori[] = implode('; ', $anomalie);
            return $valori;
        });
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1F3864']],
            ],
        ];
    }

    public function columnFormats(): array
    {
        return [];
    }
}
