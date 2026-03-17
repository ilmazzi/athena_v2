<?php

namespace App\Http\Livewire;

use Livewire\Component;
use Livewire\WithFileUploads;
use App\Services\OcrService;
use App\Models\OcrDocument;
use App\Models\CaricoDettaglio;
use App\Models\Articolo;
use App\Models\Giacenza;
use App\Models\Fornitore;
use App\Models\Sede;
use App\Models\CategoriaMerceologica;
use App\Models\Stampante;
use App\Services\EtichettaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class CaricoDocumento extends Component
{
    use WithFileUploads;

    // Step workflow
    public $step = 1; // 1=Upload, 2=Validazione, 3=Completato

    // Upload
    public $pdf;
    public $tipoDocumento = 'ddt';

    // Dati documento estratti
    public $ocrDocumentId;
    public $numeroDocumento;
    public $dataDocumento;
    public $fornitoreId;
    public $sedeId;
    public $categoriaId;
    public $partitaIva;
    public $importoTotale;
    public $confidenceScore = 0;
    public $saveError = null;

    // Articoli
    public $articoli = [];

    // Liste dropdown
    public $fornitori = [];
    public $sedi = [];
    public $categorie = [];
    public $stampantiDisponibili = [];

    // Stampa etichette
    public $stampaEtichette = true;
    public $stampanteId = '';
    public $layoutEtichetta = 'standard';
    public $showConfirmModal = false;
    public $etichetteTotali = 0;
    public $codicePrezzoTipo = 'G';
    public $codicePrezzoSuffix = '';

    // Regole di validazione (Livewire 2)
    protected function rules(): array
    {
        $base = [
            'tipoDocumento' => 'required|in:ddt,fattura',
            'numeroDocumento' => 'required|string|max:50',
            'dataDocumento' => 'required|date',
            'sedeId' => 'required|exists:sedi,id',
            'categoriaId' => 'nullable|exists:categorie_merceologiche,id',
            'articoli.*.codice' => 'required|string|max:50',
            'articoli.*.quantita' => 'required|integer|min:1',
            'articoli.*.caratura' => 'nullable|string|max:50',
            'articoli.*.categoria_id' => 'required|exists:categorie_merceologiche,id',
            'articoli.*.prezzo_unitario' => 'nullable|numeric|min:0',
            'articoli.*.prezzo_totale' => 'nullable|numeric|min:0',
            'articoli.*.prezzo_etichetta' => 'nullable|string|max:50',
            'importoTotale' => 'nullable|numeric|min:0',
        ];

        if ($this->step === 1) {
            $base['pdf'] = 'required|file|mimes:pdf|max:10240';
        }

        return $base;
    }

    public function mount()
    {
        $this->fornitori = Fornitore::orderBy('ragione_sociale')->get();
        $this->sedi = Sede::orderBy('nome')->get();
        $categorieQuery = CategoriaMerceologica::orderBy('nome');
        $userSedeId = auth()->user()?->sede_id;
        if ($userSedeId) {
            $categorieQuery->where('sede_id', $userSedeId);
        }
        $this->categorie = $categorieQuery->get();
        $this->stampantiDisponibili = Stampante::where('attiva', true)
            ->orderBy('nome')
            ->get(['id', 'nome', 'modello']);
    }

    /**
     * Step 1: Processa PDF con OCR
     */
    public function processaPdf()
    {
        // Validazione Livewire 2
        $this->validate([
            'pdf' => 'required|mimes:pdf|max:10240',
            'tipoDocumento' => 'required|in:ddt,fattura',
        ]);
        
        try {
            // Processa con OCR
            $ocrService = app(OcrService::class);
            $ocrDocument = $ocrService->processPdf($this->pdf, $this->tipoDocumento);

            // Carica dati estratti
            $this->ocrDocumentId = $ocrDocument->id;
            $this->loadDatiDaOcr($ocrDocument);

            // Vai allo step 2
            $this->step = 2;

            $this->dispatch('swal:success', [
                'title' => 'OCR Completato!',
                'text' => 'Documento elaborato con successo. Controlla i dati estratti.',
            ]);

        } catch (\Exception $e) {
            $this->dispatch('swal:error', [
                'title' => 'Errore OCR',
                'text' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Carica dati da documento OCR
     */
    protected function loadDatiDaOcr(OcrDocument $doc)
    {
        $dati = $doc->ocr_structured_data;

        $this->fornitoreId = $doc->fornitore_id;
        $this->numeroDocumento = $dati['numero'] ?? '';
        $this->dataDocumento = $dati['data'] ?? now()->format('Y-m-d');
        $this->partitaIva = $dati['partita_iva'] ?? '';
        $this->importoTotale = $dati['importo_totale'] ?? null;
        $this->confidenceScore = $doc->confidence_score;
        $this->articoli = $dati['articoli'] ?? [];

        // Verifica esistenza articoli
        foreach ($this->articoli as $index => $articolo) {
            $articoloEsistente = Articolo::where('codice', $articolo['codice'] ?? '')->first();
            $this->articoli[$index]['articolo_id'] = $articoloEsistente?->id;
            $this->articoli[$index]['esiste'] = !is_null($articoloEsistente);
            $this->articoli[$index]['prezzo_unitario'] = $articolo['prezzo_unitario'] ?? null;
            $this->articoli[$index]['prezzo_totale'] = $articolo['prezzo_totale'] ?? null;
            $this->articoli[$index]['prezzo_etichetta'] = $articolo['prezzo_etichetta'] ?? '';
            $this->articoli[$index]['categoria_id'] = $articoloEsistente?->categoria_merceologica_id
                ?? $articolo['categoria_id']
                ?? $this->categoriaId;
        }
    }

    /**
     * Aggiungi articolo manualmente
     */
    public function aggiungiArticolo()
    {
        $this->articoli[] = [
            'codice' => '',
            'descrizione' => '',
            'quantita' => 1,
            'caratura' => '',
            'prezzo_unitario' => null,
            'prezzo_totale' => null,
            'prezzo_etichetta' => '',
            'numero_seriale' => '',
            'ean' => '',
            'articolo_id' => null,
            'esiste' => false,
            'categoria_id' => $this->categoriaId,
        ];
    }

    public function updatedCategoriaId()
    {
        if (empty($this->categoriaId) || empty($this->articoli)) {
            return;
        }

        foreach ($this->articoli as $index => $articolo) {
            $this->articoli[$index]['categoria_id'] = $this->categoriaId;
        }

        $this->dispatch('swal:success', [
            'title' => 'Magazzino aggiornato',
            'text' => 'Tutti gli articoli sono stati impostati su questo magazzino.',
        ]);
    }

    public function richiestaSalvaCarico()
    {
        $this->validate();

        if ($this->stampaEtichette) {
            $this->etichetteTotali = $this->calcolaEtichetteTotali();
            $this->showConfirmModal = true;
            return;
        }

        $this->salvaCarico();
    }

    public function confermaSalvaCarico()
    {
        $this->showConfirmModal = false;
        $this->salvaCarico();
    }

    public function annullaSalvaCarico()
    {
        $this->showConfirmModal = false;
    }

    public function applicaCodicePrezzoTutti()
    {
        if (empty($this->articoli)) {
            return;
        }

        foreach ($this->articoli as $index => $articolo) {
            $this->articoli[$index]['prezzo_etichetta'] = $this->buildCodicePrezzo(
                $articolo['prezzo_unitario'] ?? null,
                $this->codicePrezzoTipo,
                $this->codicePrezzoSuffix
            );
        }
    }

    public function applicaCodicePrezzoRiga($index)
    {
        if (!isset($this->articoli[$index])) {
            return;
        }

        $this->articoli[$index]['prezzo_etichetta'] = $this->buildCodicePrezzo(
            $this->articoli[$index]['prezzo_unitario'] ?? null,
            $this->codicePrezzoTipo,
            $this->codicePrezzoSuffix
        );
    }

    /**
     * Rimuovi articolo
     */
    public function rimuoviArticolo($index)
    {
        unset($this->articoli[$index]);
        $this->articoli = array_values($this->articoli);
    }

    /**
     * Step 2: Salva tutto
     */
    public function salvaCarico()
    {
        $this->saveError = null;
        // Validazione Livewire 2
        $this->validate();

        DB::beginTransaction();
        try {
            // 1. Crea DDT o Fattura con tutti i dati
            $documento = null;
            $numeroArticoli = 0;
            $quantitaTotale = 0;
            $anno = date('Y', strtotime($this->dataDocumento));
            
            // ⚠️ CONTROLLO DUPLICATI: Verifica se esiste già un documento con stesso numero, anno e fornitore
            if ($this->tipoDocumento === 'ddt') {
                $documentoEsistente = \App\Models\Ddt::where('numero', $this->numeroDocumento)
                    ->where('anno', $anno)
                    ->where('fornitore_id', $this->fornitoreId)
                    ->first();
                
                if ($documentoEsistente) {
                    throw new \Exception("⚠️ DUPLICATO: Esiste già un DDT n. {$this->numeroDocumento}/{$anno} per questo fornitore (ID: {$documentoEsistente->id}). Controlla prima di procedere.");
                }
                
                $ddtData = [
                    'numero' => $this->numeroDocumento,
                    'anno' => $anno,
                    'data_documento' => $this->dataDocumento,
                    'fornitore_id' => $this->fornitoreId,
                    'sede_id' => $this->sedeId,
                    'categoria_id' => $this->categoriaId,
                    'tipo_carico' => 'ocr',
                    'ocr_document_id' => $this->ocrDocumentId,
                    'stato' => 'caricato',
                    'data_carico' => now(),
                    'note' => 'Caricato tramite OCR',
                ];
                if (Schema::hasColumn('ddt', 'user_carico_id')) {
                    $ddtData['user_carico_id'] = auth()->id();
                }
                $documento = \App\Models\Ddt::create($ddtData);
            } else {
                $documentoEsistente = \App\Models\Fattura::where('numero', $this->numeroDocumento)
                    ->where('anno', $anno)
                    ->where('fornitore_id', $this->fornitoreId)
                    ->first();
                
                if ($documentoEsistente) {
                    throw new \Exception("⚠️ DUPLICATO: Esiste già una Fattura n. {$this->numeroDocumento}/{$anno} per questo fornitore (ID: {$documentoEsistente->id}). Controlla prima di procedere.");
                }
                
                $fatturaData = [
                    'numero' => $this->numeroDocumento,
                    'anno' => $anno,
                    'data_documento' => $this->dataDocumento,
                    'fornitore_id' => $this->fornitoreId,
                    'sede_id' => $this->sedeId,
                    'categoria_id' => $this->categoriaId,
                    'tipo_carico' => 'ocr',
                    'ocr_document_id' => $this->ocrDocumentId,
                    'partita_iva' => $this->partitaIva,
                    'totale' => $this->importoTotale,
                    'stato' => 'caricata',
                    'data_carico' => now(),
                    'note' => 'Caricata tramite OCR',
                ];
                if (Schema::hasColumn('fatture', 'user_carico_id')) {
                    $fatturaData['user_carico_id'] = auth()->id();
                }
                $documento = \App\Models\Fattura::create($fatturaData);
            }

            // 2. Processa articoli
            $articoliDaStampare = [];

            foreach ($this->articoli as $articolo) {
                $prezzoUnitario = $this->normalizePrice($articolo['prezzo_unitario'] ?? null);
                $prezzoTotale = $this->normalizePrice($articolo['prezzo_totale'] ?? null);
                if ($prezzoUnitario !== null && (!$prezzoTotale || $prezzoTotale <= 0)) {
                    $prezzoTotale = $prezzoUnitario * ($articolo['quantita'] ?? 1);
                }
                $articoloCategoriaId = $articolo['categoria_id'] ?? $this->categoriaId;

                // Crea o aggiorna articolo
                if (empty($articolo['articolo_id'])) {
                    // Genera codice progressivo per magazzino
                    $codiceService = app(\App\Services\CodiceService::class);
                    $codiceVO = $codiceService->prossimoCodiceDisponibile($articoloCategoriaId);
                    
                    // Prepara caratteristiche JSON con referenza fornitore
                    $caratteristiche = [
                        'referenza' => $articolo['codice'] ?? '', // Il codice OCR è la referenza
                        'marca' => null,
                        'oro' => null,
                        'pietre' => null,
                        'brill' => null,
                    ];
                    
                    $nuovoArticolo = Articolo::create([
                        'codice' => $codiceVO->toString(), // Es: "2-123"
                        'descrizione' => $articolo['descrizione'] ?? '',
                        'categoria_merceologica_id' => $articoloCategoriaId,
                        'sede_id' => $this->sedeId,
                        'fornitore_id' => $this->fornitoreId,
                        'prezzo_acquisto' => $this->tipoDocumento === 'fattura' ? $prezzoUnitario : null,
                        'prezzo_fornitore' => $prezzoUnitario, // DDT e fattura (Pomellato prezzo imposto su fattura)
                        'ean' => $articolo['ean'] ?? null,
                        'numero_seriale' => $articolo['numero_seriale'] ?? null,
                        'caratura' => $articolo['caratura'] ?? null,
                        'caratteristiche' => json_encode($caratteristiche),
                        'stato' => 'disponibile',
                        'stato_articolo' => 'disponibile',
                        'tipo_carico' => $this->tipoDocumento,
                        'numero_documento_carico' => $this->numeroDocumento,
                        'data_carico' => $this->dataDocumento,
                    ]);
                    $articoloId = $nuovoArticolo->id;
                } else {
                    $articoloId = $articolo['articolo_id'];
                    if (!empty($articolo['caratura'])) {
                        Articolo::where('id', $articoloId)
                            ->where(function ($q) {
                                $q->whereNull('caratura')->orWhere('caratura', '');
                            })
                            ->update(['caratura' => $articolo['caratura']]);
                    }
                    if ($prezzoUnitario !== null) {
                        Articolo::where('id', $articoloId)->update([
                            'prezzo_fornitore' => $prezzoUnitario,
                        ]);
                    }
                }

                // Crea dettaglio carico (punta direttamente a DDT o Fattura)
                CaricoDettaglio::create([
                    'ddt_id' => $this->tipoDocumento === 'ddt' ? $documento->id : null,
                    'fattura_id' => $this->tipoDocumento === 'fattura' ? $documento->id : null,
                    'articolo_id' => $articoloId,
                    'referenza_fornitore' => $articolo['codice'], // Codice OCR = referenza fornitore
                    'descrizione' => $articolo['descrizione'] ?? '',
                    'quantita' => $articolo['quantita'],
                    'numero_seriale' => $articolo['numero_seriale'] ?? null,
                    'ean' => $articolo['ean'] ?? null,
                    'prezzo_unitario' => $prezzoUnitario,
                    'prezzo_totale' => $prezzoTotale,
                    'verificato' => true,
                    'creato_nuovo' => empty($articolo['articolo_id']),
                ]);
                
                // Crea dettaglio DDT o Fattura (per compatibilità con sistema esistente)
                if ($this->tipoDocumento === 'ddt') {
                    \App\Models\DdtDettaglio::create([
                        'ddt_id' => $documento->id,
                        'articolo_id' => $articoloId,
                        'quantita' => $articolo['quantita'],
                        'descrizione' => $articolo['descrizione'] ?? '',
                    ]);
                } else {
                    \App\Models\FatturaDettaglio::create([
                        'fattura_id' => $documento->id,
                        'articolo_id' => $articoloId,
                        'quantita' => $articolo['quantita'],
                        'prezzo_unitario' => $prezzoUnitario,
                        'totale_riga' => $prezzoTotale,
                        'codice_articolo' => $articolo['codice'] ?? null,
                        'descrizione' => $articolo['descrizione'] ?? '',
                        'caricato' => true,
                    ]);
                }

                if ($this->tipoDocumento === 'fattura' && $prezzoUnitario !== null) {
                    Articolo::where('id', $articoloId)->update([
                        'prezzo_acquisto' => $prezzoUnitario,
                        'prezzo_fornitore' => $prezzoUnitario, // Prezzo imposto (es. Pomellato) per cartellino
                    ]);
                }

                // Aggiorna/Crea giacenza
                $giacenza = Giacenza::where('articolo_id', $articoloId)
                    ->where('sede_id', $this->sedeId)
                    ->first();

                if ($giacenza) {
                    // Incrementa sia lo storico che il residuo
                    $giacenza->increment('quantita', $articolo['quantita']);
                    $giacenza->increment('quantita_residua', $articolo['quantita']);
                } else {
                    Giacenza::create([
                        'articolo_id' => $articoloId,
                        'sede_id' => $this->sedeId,
                        'quantita' => $articolo['quantita'],
                        'quantita_residua' => $articolo['quantita'],
                        'quantita_iniziale' => $articolo['quantita'],
                    ]);
                }
                
                // Conta articoli e quantità
                $numeroArticoli++;
                $quantitaTotale += $articolo['quantita'];

                $articoliDaStampare[] = [
                    'articolo_id' => $articoloId,
                    'quantita' => (int) ($articolo['quantita'] ?? 1),
                    'prezzo_etichetta' => $articolo['prezzo_etichetta'] ?? '',
                ];
            }
            
            // Aggiorna il documento con i totali
            $documento->update([
                'numero_articoli' => $numeroArticoli,
                'quantita_totale' => $quantitaTotale,
            ]);

            // 3. Aggiorna OCR document
            if ($this->ocrDocumentId) {
                OcrDocument::find($this->ocrDocumentId)->update([
                    'status' => 'completed',
                    'validated_by' => auth()->id(),
                    'validated_at' => now(),
                ]);
            }

            DB::commit();

            $this->step = 3;

            $this->stampaEtichetteCarico($articoliDaStampare);

            $this->dispatch('swal:success', 
                title: 'Carico Completato!',
                text: "Documento {$this->numeroDocumento} caricato con successo.",
                timer: 3000
            );

        } catch (\Exception $e) {
            DB::rollBack();

            $this->saveError = $e->getMessage();
            
            $this->dispatch('swal:error',
                title: 'Errore Salvataggio',
                text: $e->getMessage()
            );
        }
    }

    /**
     * Ricomincia da capo
     */
    public function nuovoCarico()
    {
        $this->reset();
        $this->mount();
    }

    public function render()
    {
        return view('livewire.carico-documento', [
            'title' => 'Carico Documenti'
        ]);
    }

    protected function normalizePrice($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = str_replace(['.', ','], ['', '.'], (string) $value);
        if (!is_numeric($normalized)) {
            return null;
        }

        return (float) $normalized;
    }

    protected function stampaEtichetteCarico(array $articoliDaStampare): void
    {
        if (!$this->stampaEtichette) {
            return;
        }
        if (empty($articoliDaStampare)) {
            return;
        }

        $service = app(EtichettaService::class);
        $success = 0;
        $errors = 0;

        foreach ($articoliDaStampare as $item) {
            $articolo = Articolo::find($item['articolo_id']);
            if (!$articolo) {
                $errors++;
                continue;
            }

            $quantita = max(1, (int) ($item['quantita'] ?? 1));
            $prezzoEtichetta = trim((string) ($item['prezzo_etichetta'] ?? ''));
            if ($prezzoEtichetta === '') {
                // Non stampare se il prezzo etichetta non è compilato
                continue;
            }
            $formatoPrezzo = $this->guessFormatoPrezzo($prezzoEtichetta);

            $stampante = $this->stampanteId
                ? Stampante::find($this->stampanteId)
                : $service->getStampanteDefault($articolo);

            if (!$stampante) {
                $errors++;
                continue;
            }

            for ($i = 0; $i < $quantita; $i++) {
                try {
                    $zpl = $service->generaEtichettaZPLConPrezzo(
                        $articolo,
                        $prezzoEtichetta,
                        $formatoPrezzo,
                        $stampante->id,
                        $this->layoutEtichetta
                    );
                    $ok = $service->inviaAllaStampante($stampante->ip_address, $stampante->port, $zpl);

                    $ok ? $success++ : $errors++;
                } catch (\Exception $e) {
                    Log::warning('Errore stampa etichetta carico', [
                        'articolo_id' => $articolo->id,
                        'error' => $e->getMessage(),
                    ]);
                    $errors++;
                }
            }
        }

        if ($errors > 0) {
            session()->flash('warning', "Etichette stampate: {$success}, errori: {$errors}");
        } else {
            session()->flash('success', "Etichette stampate: {$success}");
        }
    }

    protected function guessFormatoPrezzo(string $prezzo): string
    {
        if ($prezzo === '') {
            return 'euro';
        }

        $numeric = preg_replace('/[^\d,.]/', '', $prezzo);
        $numeric = str_replace(',', '.', $numeric);

        return is_numeric($numeric) ? 'euro' : 'codificato';
    }

    protected function calcolaEtichetteTotali(): int
    {
        $totale = 0;
        foreach ($this->articoli as $articolo) {
            $prezzoEtichetta = trim((string) ($articolo['prezzo_etichetta'] ?? ''));
            if ($prezzoEtichetta === '') {
                continue;
            }
            $totale += max(1, (int) ($articolo['quantita'] ?? 1));
        }
        return $totale;
    }

    protected function buildCodicePrezzo($costoUnitario, string $tipo, string $suffix): string
    {
        $prezzo = $this->normalizePrice($costoUnitario);
        if ($prezzo === null) {
            return '';
        }
        $valore = rtrim(rtrim(number_format($prezzo, 2, '.', ''), '0'), '.');
        $tipo = strtoupper(trim($tipo));
        $suffix = trim((string) $suffix);

        return 'X' . $valore . ($tipo === 'P' ? 'P' : 'G') . $suffix;
    }
}

