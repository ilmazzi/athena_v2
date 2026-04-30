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
use App\Services\MagazzinoLogicoService;
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
    public $importoTotaleEstratto;
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
        $priceRule = function (string $attribute, $value, $fail): void {
            if ($value === null || $value === '') {
                return;
            }

            if ($this->normalizePrice($value) === null) {
                $fail('Il campo deve contenere un importo valido.');
            }
        };

        $base = [
            'tipoDocumento' => 'required|in:ddt,fattura',
            'numeroDocumento' => 'required|string|max:50',
            'dataDocumento' => 'required|date',
            'sedeId' => 'required|exists:sedi,id',
            'categoriaId' => 'nullable|integer|min:1',
            'articoli.*.codice' => 'required|string|max:50',
            'articoli.*.quantita' => 'required|integer|min:1',
            'articoli.*.caratura' => 'nullable|string|max:50',
            'articoli.*.categoria_id' => 'required|integer|min:1',
            'articoli.*.prezzo_unitario' => ['nullable', $priceRule],
            'articoli.*.prezzo_totale' => ['nullable', $priceRule],
            'articoli.*.prezzo_fornitore' => ['nullable', $priceRule],
            'articoli.*.prezzo_etichetta' => 'nullable|string|max:50',
            'importoTotale' => ['nullable', $priceRule],
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
        $userSedeId = auth()->user()?->sede_id;
        if ($userSedeId && collect($this->sedi)->contains('id', (int) $userSedeId)) {
            $this->sedeId = (string) $userSedeId;
        }
        $this->refreshCategorieDisponibili();
        $this->stampantiDisponibili = Stampante::where('attiva', true)
            ->orderBy('nome')
            ->get(['id', 'nome', 'modello']);
    }

    public function updatedSedeId(): void
    {
        $this->refreshCategorieDisponibili();

        if ($this->categoriaId && !collect($this->categorie)->contains('id', (int) $this->categoriaId)) {
            $this->categoriaId = '';
        }

        if (empty($this->articoli)) {
            return;
        }

        foreach ($this->articoli as $index => $articolo) {
            $categoriaRiga = (int) ($articolo['categoria_id'] ?? 0);
            if ($categoriaRiga > 0 && !collect($this->categorie)->contains('id', $categoriaRiga)) {
                $this->articoli[$index]['categoria_id'] = $this->categoriaId ?: null;
            }
        }
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
        $this->importoTotaleEstratto = $dati['importo_totale'] ?? null;
        $this->importoTotale = $this->getImportoTotaleCalcolatoProperty() ?? $this->normalizePrice($this->importoTotaleEstratto);
        $this->confidenceScore = $doc->confidence_score;
        $this->articoli = $dati['articoli'] ?? [];

        foreach ($this->articoli as $index => $articolo) {
            $prezzoEtichettaEstratto = trim((string) ($articolo['prezzo_etichetta'] ?? ''));

            $this->articoli[$index]['articolo_id'] = null;
            $this->articoli[$index]['esiste'] = false;
            $this->articoli[$index]['ordine_inserimento'] = $articolo['ordine_inserimento'] ?? ($index + 1);
            $this->articoli[$index]['prezzo_unitario'] = $articolo['prezzo_unitario'] ?? null;
            $this->articoli[$index]['prezzo_totale'] = $articolo['prezzo_totale'] ?? null;
            $this->articoli[$index]['prezzo_fornitore'] = $articolo['prezzo_fornitore'] ?? null;
            $this->articoli[$index]['prezzo_etichetta'] = $prezzoEtichettaEstratto;
            $categoriaEstratta = $articolo['categoria_id'] ?? null;
            $this->articoli[$index]['categoria_id'] = $categoriaEstratta
                ? $this->resolveMagazzinoLogicoForCategoria($categoriaEstratta)
                : $this->categoriaId;
            $this->articoli[$index]['numero_seriale'] = $this->normalizeSerial($articolo['numero_seriale'] ?? null);
            $this->articoli[$index]['ean'] = trim((string) ($articolo['ean'] ?? ''));
        }
        foreach (array_keys($this->articoli) as $index) {
            $this->syncTotaleRiga($index);
        }
        $this->importoTotale = $this->getImportoTotaleCalcolatoProperty() ?? $this->normalizePrice($this->importoTotaleEstratto);
    }

    /**
     * Aggiungi articolo manualmente
     */
    public function aggiungiArticolo()
    {
        array_unshift($this->articoli, [
            'codice' => '',
            'descrizione' => '',
            'quantita' => 1,
            'caratura' => '',
            'prezzo_unitario' => null,
            'prezzo_totale' => null,
            'prezzo_fornitore' => null,
            'prezzo_etichetta' => '',
            'numero_seriale' => '',
            'ean' => '',
            'articolo_id' => null,
            'esiste' => false,
            'categoria_id' => $this->categoriaId,
            'ordine_inserimento' => $this->nextOrdineInserimento(),
        ]);
    }


    public function updatedArticoli($value, $name): void
    {
        $segments = explode('.', (string) $name);
        $index = isset($segments[0]) ? (int) $segments[0] : null;
        $field = $segments[1] ?? null;

        if ($index === null || !isset($this->articoli[$index])) {
            return;
        }

        if (in_array($field, ['quantita', 'prezzo_unitario'], true)) {
            $this->syncTotaleRiga($index);
            $this->importoTotale = $this->getImportoTotaleCalcolatoProperty();
        }
    }

    public function calcolaTotaleRiga(array $articolo): ?float
    {
        $quantita = max(1, (int) ($articolo['quantita'] ?? 1));
        $prezzoUnitario = $this->normalizePrice($articolo['prezzo_unitario'] ?? null);
        if ($prezzoUnitario !== null) {
            return round($prezzoUnitario * $quantita, 2);
        }

        return $this->normalizePrice($articolo['prezzo_totale'] ?? null);
    }

    protected function syncTotaleRiga(int $index): void
    {
        if (!isset($this->articoli[$index])) {
            return;
        }

        $totale = $this->calcolaTotaleRiga($this->articoli[$index]);
        $this->articoli[$index]['prezzo_totale'] = $totale !== null
            ? number_format($totale, 2, ',', '')
            : null;
    }

    public function updatedCategoriaId()
    {
        if (!empty($this->categoriaId) && !collect($this->categorie)->contains('id', (int) $this->categoriaId)) {
            $this->categoriaId = '';
            return;
        }

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
        $this->prepareForSaveValidation();
        $this->validate();

        if ($this->stampaEtichette) {
            if (!$this->validatePrezziEtichetteStampabili()) {
                $this->dispatch('swal:error', [
                    'title' => 'Prezzi etichette mancanti',
                    'text' => 'Compila il prezzo etichetta per le righe senza prezzo di listino prima di stampare.',
                ]);
                return;
            }

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
        $this->prepareForSaveValidation();
        // Validazione Livewire 2
        $this->validate();

        DB::beginTransaction();
        try {
            // 1. Crea DDT o Fattura con tutti i dati
            $documento = null;
            $numeroArticoli = 0;
            $quantitaTotale = 0;
            $anno = date('Y', strtotime($this->dataDocumento));
            $magazzinoLogicoDocumento = $this->resolveMagazzinoLogicoForCategoria($this->categoriaId);
            $categoriaDocumentoId = $this->resolveCategoriaLocaleId($this->sedeId, $this->categoriaId);

            if (!$categoriaDocumentoId) {
                throw new \Exception('Il magazzino selezionato non è disponibile per la sede indicata.');
            }
            
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
                    'categoria_id' => $categoriaDocumentoId,
                    'magazzino_logico' => $magazzinoLogicoDocumento,
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
                    'categoria_id' => $categoriaDocumentoId,
                    'magazzino_logico' => $magazzinoLogicoDocumento,
                    'tipo_carico' => 'ocr',
                    'ocr_document_id' => $this->ocrDocumentId,
                    'partita_iva' => $this->partitaIva,
                    'totale' => $this->getImportoTotaleCalcolatoProperty(),
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
            $this->assertSerialsAreUniqueForCurrentLoad();

            foreach ($this->getArticoliInOrdineInserimento() as $articolo) {
                $prezzoUnitario = $this->normalizePrice($articolo['prezzo_unitario'] ?? null);
                $prezzoTotale = $this->normalizePrice($articolo['prezzo_totale'] ?? null);
                $prezzoFornitore = $this->normalizePrice($articolo['prezzo_fornitore'] ?? null);
                $referenzaFornitore = trim((string) ($articolo['codice'] ?? ''));
                $numeroSeriale = $this->normalizeSerial($articolo['numero_seriale'] ?? null);
                $ean = trim((string) ($articolo['ean'] ?? '')) ?: null;
                if ($prezzoUnitario !== null && (!$prezzoTotale || $prezzoTotale <= 0)) {
                    $prezzoTotale = $prezzoUnitario * ($articolo['quantita'] ?? 1);
                }
                $magazzinoLogico = (int) ($articolo['categoria_id'] ?? $this->categoriaId);
                $articoloCategoriaId = $this->resolveCategoriaLocaleId($this->sedeId, $magazzinoLogico);

                if (!$articoloCategoriaId) {
                    throw new \Exception("Magazzino {$magazzinoLogico} non disponibile per la sede selezionata.");
                }

                $this->assertSerialIsAvailable($numeroSeriale);

                $codiceService = app(\App\Services\CodiceService::class);
                $codiceVO = $codiceService->prossimoCodiceDisponibile($articoloCategoriaId);

                $nuovoArticolo = Articolo::create([
                    'codice' => $codiceVO->toString(),
                    'descrizione' => $articolo['descrizione'] ?? '',
                    'categoria_merceologica_id' => $articoloCategoriaId,
                    'magazzino_logico' => $magazzinoLogico,
                    'sede_id' => $this->sedeId,
                    'prezzo_acquisto' => $this->tipoDocumento === 'fattura' ? $prezzoUnitario : null,
                    'prezzo_fornitore' => $prezzoFornitore,
                    'ean' => $ean,
                    'numero_seriale' => $numeroSeriale,
                    'caratura' => $articolo['caratura'] ?? null,
                    'caratteristiche' => [
                        'referenza' => $referenzaFornitore,
                        'marca' => null,
                        'oro' => null,
                        'pietre' => null,
                        'brill' => null,
                    ],
                    'stato' => 'disponibile',
                    'stato_articolo' => 'disponibile',
                ]);
                $articoloId = $nuovoArticolo->id;

                CaricoDettaglio::create([
                    'ddt_id' => $this->tipoDocumento === 'ddt' ? $documento->id : null,
                    'fattura_id' => $this->tipoDocumento === 'fattura' ? $documento->id : null,
                    'articolo_id' => $articoloId,
                    'referenza_fornitore' => $referenzaFornitore,
                    'descrizione' => $articolo['descrizione'] ?? '',
                    'quantita' => $articolo['quantita'],
                    'numero_seriale' => $numeroSeriale,
                    'ean' => $ean,
                    'prezzo_unitario' => $prezzoUnitario,
                    'prezzo_totale' => $prezzoTotale,
                    'verificato' => true,
                    'creato_nuovo' => true,
                ]);

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

                Giacenza::create([
                    'articolo_id' => $articoloId,
                    'categoria_merceologica_id' => $articoloCategoriaId,
                    'magazzino_logico' => $magazzinoLogico,
                    'sede_id' => $this->sedeId,
                    'quantita' => $articolo['quantita'],
                    'quantita_residua' => $articolo['quantita'],
                    'quantita_iniziale' => $articolo['quantita'],
                ]);

                $numeroArticoli++;
                $quantitaTotale += $articolo['quantita'];

                $articoliDaStampare[] = [
                    'articolo_id' => $articoloId,
                    'quantita' => (int) ($articolo['quantita'] ?? 1),
                    'prezzo_fornitore' => $articolo['prezzo_fornitore'] ?? null,
                    'prezzo_etichetta' => $articolo['prezzo_etichetta'] ?? null,
                ];

                continue;

                // Crea o aggiorna articolo
                if (empty($articolo['articolo_id'])) {
                    // Genera codice progressivo per magazzino
                    $codiceService = app(\App\Services\CodiceService::class);
                    $codiceVO = $codiceService->prossimoCodiceDisponibile($articoloCategoriaId);
                    
                    // Prepara caratteristiche JSON con referenza fornitore
                    $caratteristiche = [
                        'referenza' => $referenzaFornitore, // Il codice OCR è la referenza
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
                        'prezzo_acquisto' => $this->tipoDocumento === 'fattura' ? $prezzoUnitario : null,
                        'prezzo_fornitore' => $prezzoFornitore,
                        'ean' => $articolo['ean'] ?? null,
                        'numero_seriale' => $articolo['numero_seriale'] ?? null,
                        'caratura' => $articolo['caratura'] ?? null,
                        'caratteristiche' => json_encode($caratteristiche),
                        'stato' => 'disponibile',
                        'stato_articolo' => 'disponibile',
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
                    $updatesArticolo = [];
                    if ($prezzoFornitore !== null) {
                        $updatesArticolo['prezzo_fornitore'] = $prezzoFornitore;
                    }

                    if ($referenzaFornitore !== '') {
                        $articoloEsistente = Articolo::find($articoloId);
                        $caratteristiche = $articoloEsistente?->caratteristiche;
                        if (is_string($caratteristiche)) {
                            $decoded = json_decode($caratteristiche, true);
                            $caratteristiche = is_array($decoded) ? $decoded : [];
                        } elseif (!is_array($caratteristiche)) {
                            $caratteristiche = [];
                        }
                        $caratteristiche['referenza'] = $referenzaFornitore;
                        $updatesArticolo['caratteristiche'] = $caratteristiche;
                    }

                    if (!empty($updatesArticolo)) {
                        Articolo::where('id', $articoloId)->update($updatesArticolo);
                    }
                }

                // Crea dettaglio carico (punta direttamente a DDT o Fattura)
                CaricoDettaglio::create([
                    'ddt_id' => $this->tipoDocumento === 'ddt' ? $documento->id : null,
                    'fattura_id' => $this->tipoDocumento === 'fattura' ? $documento->id : null,
                    'articolo_id' => $articoloId,
                    'referenza_fornitore' => $referenzaFornitore, // Codice OCR = referenza fornitore
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
                    $updatesPrezzo = [
                        'prezzo_acquisto' => $prezzoUnitario,
                    ];
                    if ($prezzoFornitore !== null) {
                        $updatesPrezzo['prezzo_fornitore'] = $prezzoFornitore;
                    }

                    Articolo::where('id', $articoloId)->update($updatesPrezzo);
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
                    'prezzo_fornitore' => $articolo['prezzo_fornitore'] ?? null,
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
                    'status' => 'validated',
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


    public function getImportoTotaleCalcolatoProperty(): ?float
    {
        $totale = 0.0;
        $haValori = false;

        foreach ($this->articoli as $articolo) {
            $quantita = max(1, (int) ($articolo['quantita'] ?? 1));
            $prezzoUnitario = $this->normalizePrice($articolo['prezzo_unitario'] ?? null);
            $prezzoTotale = $this->normalizePrice($articolo['prezzo_totale'] ?? null);

            if ($prezzoUnitario !== null) {
                $totale += $prezzoUnitario * $quantita;
                $haValori = true;
                continue;
            }

            if ($prezzoTotale !== null) {
                $totale += $prezzoTotale;
                $haValori = true;
            }
        }

        return $haValori ? round($totale, 2) : null;
    }

    public function getImportoTotaleScostamentoProperty(): ?float
    {
        $estratto = $this->normalizePrice($this->importoTotaleEstratto);
        $calcolato = $this->getImportoTotaleCalcolatoProperty();

        if ($estratto === null || $calcolato === null) {
            return null;
        }

        return round($calcolato - $estratto, 2);
    }

    protected function prepareForSaveValidation(): void
    {
        foreach (array_keys($this->articoli) as $index) {
            $this->syncTotaleRiga($index);
        }

        $this->importoTotale = $this->getImportoTotaleCalcolatoProperty();
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

        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $raw = trim((string) $value);
        $raw = str_replace(["\xc2\xa0", ' '], '', $raw);
        $raw = preg_replace('/[^\d,.\-]/u', '', $raw);

        if ($raw === '') {
            return null;
        }

        $lastComma = strrpos($raw, ',');
        $lastDot = strrpos($raw, '.');

        if ($lastComma !== false && $lastDot !== false) {
            if ($lastComma > $lastDot) {
                $normalized = str_replace('.', '', $raw);
                $normalized = str_replace(',', '.', $normalized);
            } else {
                $normalized = str_replace(',', '', $raw);
            }
        } elseif ($lastComma !== false) {
            $decimals = strlen($raw) - $lastComma - 1;
            if ($decimals > 0 && $decimals <= 2) {
                $normalized = str_replace('.', '', $raw);
                $normalized = str_replace(',', '.', $normalized);
            } else {
                $normalized = str_replace(',', '', $raw);
            }
        } elseif ($lastDot !== false) {
            $decimals = strlen($raw) - $lastDot - 1;
            if ($decimals > 0 && $decimals <= 2) {
                $normalized = str_replace(',', '', $raw);
            } else {
                $normalized = str_replace('.', '', $raw);
            }
        } else {
            $normalized = $raw;
        }

        return is_numeric($normalized) ? (float) $normalized : null;
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
            $prezzoEtichetta = $this->resolvePrezzoEtichettaPerStampa($item);
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

    protected function resolvePrezzoEtichettaPerStampa(array $item): string
    {
        $prezzoListino = $this->normalizePrice($item['prezzo_fornitore'] ?? null);

        if ($prezzoListino !== null) {
            return number_format($prezzoListino, 2, ',', '');
        }

        $prezzoManuale = trim((string) ($item['prezzo_etichetta'] ?? ''));
        if ($prezzoManuale !== '') {
            return $prezzoManuale;
        }

        return '';
    }

    public function prezzoEtichettaPreview(array $item): string
    {
        return $this->resolvePrezzoEtichettaPerStampa($item);
    }

    protected function validatePrezziEtichetteStampabili(): bool
    {
        $valid = true;

        foreach ($this->articoli as $index => $articolo) {
            $prezzoListino = $this->normalizePrice($articolo['prezzo_fornitore'] ?? null);
            $prezzoManuale = trim((string) ($articolo['prezzo_etichetta'] ?? ''));

            if ($prezzoListino !== null || $prezzoManuale !== '') {
                continue;
            }

            $this->addError(
                'articoli.' . $index . '.prezzo_etichetta',
                'Inserisci un prezzo etichetta per la stampa oppure compila il prezzo di listino.'
            );
            $valid = false;
        }

        return $valid;
    }

    protected function guessFormatoPrezzo(string $prezzo): string
    {
        if ($prezzo === '') {
            return 'euro';
        }

        if (preg_match('/[A-Z]/i', $prezzo)) {
            return 'codificato';
        }

        $numeric = preg_replace('/[^\d,.]/', '', $prezzo);
        $numeric = str_replace(',', '.', $numeric);

        return is_numeric($numeric) ? 'euro' : 'codificato';
    }

    protected function calcolaEtichetteTotali(): int
    {
        $totale = 0;
        foreach ($this->articoli as $articolo) {
            $totale += max(1, (int) ($articolo['quantita'] ?? 1));
        }
        return $totale;
    }

    protected function getArticoliInOrdineInserimento(): array
    {
        $articoli = $this->articoli;

        usort($articoli, function (array $a, array $b): int {
            return ((int) ($a['ordine_inserimento'] ?? 0)) <=> ((int) ($b['ordine_inserimento'] ?? 0));
        });

        return $articoli;
    }

    protected function nextOrdineInserimento(): int
    {
        $max = 0;

        foreach ($this->articoli as $articolo) {
            $max = max($max, (int) ($articolo['ordine_inserimento'] ?? 0));
        }

        return $max + 1;
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

    protected function normalizeSerial($value): ?string
    {
        $serial = strtoupper(trim((string) $value));

        return $serial !== '' ? $serial : null;
    }

    protected function assertSerialsAreUniqueForCurrentLoad(): void
    {
        $serials = [];

        foreach ($this->articoli as $index => $articolo) {
            $serial = $this->normalizeSerial($articolo['numero_seriale'] ?? null);
            if ($serial === null) {
                continue;
            }

            if (isset($serials[$serial])) {
                $firstRow = $serials[$serial] + 1;
                $currentRow = $index + 1;
                throw new \Exception("Seriale duplicato nel documento: {$serial} presente alle righe {$firstRow} e {$currentRow}.");
            }

            $serials[$serial] = $index;
        }
    }

    protected function assertSerialIsAvailable(?string $serial): void
    {
        if ($serial === null) {
            return;
        }

        $existingArticle = Articolo::withoutGlobalScopes()
            ->withTrashed()
            ->whereRaw('UPPER(TRIM(numero_seriale)) = ?', [$serial])
            ->first();

        if (!$existingArticle) {
            return;
        }

        throw new \Exception("Seriale già presente in magazzino: {$serial} (articolo {$existingArticle->codice}).");
    }

    private function refreshCategorieDisponibili(): void
    {
        if (empty($this->sedeId)) {
            $this->categorie = collect();
            return;
        }

        $service = app(MagazzinoLogicoService::class);
        $sedeMagazziniId = $service->resolveSedeMagazziniIdForCarico((int) $this->sedeId);

        if (!$sedeMagazziniId) {
            $this->categorie = collect();
            return;
        }

        $categorie = CategoriaMerceologica::query()
            ->where('attivo', true)
            ->where('sede_id', $sedeMagazziniId)
            ->orderBy('nome')
            ->get();

        $this->categorie = $categorie
            ->map(function (CategoriaMerceologica $categoria) use ($service) {
                $magazzinoLogico = $service->resolveFromCategoria($categoria);
                if (!$magazzinoLogico) {
                    return null;
                }

                return [
                    'id' => $magazzinoLogico,
                    'label' => $service->getLabelForCategoria($categoria),
                    'categoria_locale_id' => $categoria->id,
                    'categoria_locale_codice' => $categoria->codice,
                    'categoria_locale_nome' => $categoria->nome,
                ];
            })
            ->filter()
            ->unique('id')
            ->sortBy('id')
            ->values();
    }

    private function resolveMagazzinoLogicoForCategoria($categoriaId): ?int
    {
        if (empty($categoriaId)) {
            return null;
        }

        $categoriaId = (int) $categoriaId;

        if (collect($this->categorie)->contains('id', $categoriaId)) {
            return $categoriaId;
        }

        return app(MagazzinoLogicoService::class)->resolveFromCategoriaId($categoriaId);
    }

    private function resolveCategoriaLocaleId($sedeId, $magazzinoLogico): ?int
    {
        if (empty($sedeId) || empty($magazzinoLogico)) {
            return null;
        }

        $service = app(MagazzinoLogicoService::class);
        $sedeMagazziniId = $service->resolveSedeMagazziniIdForCarico((int) $sedeId);

        if (!$sedeMagazziniId) {
            return null;
        }

        return $service->findCategoriaIdForSede($sedeMagazziniId, (int) $magazzinoLogico);
    }
}

