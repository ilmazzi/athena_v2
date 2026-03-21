<?php

namespace App\Services;

use App\Models\ContoDeposito;
use App\Models\MovimentoDeposito;
use App\Models\Articolo;
use App\Models\ProdottoFinito;
use App\Models\GiacenzaSede;
use App\Models\DdtDeposito;
use App\Models\DdtDepositoDettaglio;
use App\Models\Fattura; // Fatture di acquisto (legacy)
use App\Models\ProformaDeposito;
use App\Models\ArticoloVetrina;
use App\Services\NotificaService;
use App\Services\ArticoloSplitService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * ContoDepositoService - Gestione business logic conti deposito
 * 
 * Centralizza tutta la logica per:
 * - Creazione e gestione depositi
 * - Invio articoli/PF in deposito
 * - Registrazione vendite
 * - Gestione resi e rinnovi
 */
class ContoDepositoService
{
    /**
     * Crea un nuovo conto deposito
     */
    public function creaContoDeposito(
        int $sedeMittenteId,
        int $sedeDestinatariaId,
        array $articoli = [],
        array $prodottiFiniti = [],
        ?string $note = null
    ): ContoDeposito {
        // Validazione
        if ($sedeMittenteId === $sedeDestinatariaId) {
            throw new \InvalidArgumentException('La sede mittente deve essere diversa dalla destinataria');
        }

        if (empty($articoli) && empty($prodottiFiniti)) {
            throw new \InvalidArgumentException('Deve essere specificato almeno un articolo o prodotto finito');
        }

        $contoDeposito = DB::transaction(function () use ($sedeMittenteId, $sedeDestinatariaId, $articoli, $prodottiFiniti, $note) {
            // Crea il conto deposito
            $contoDeposito = ContoDeposito::create([
                'codice' => ContoDeposito::generaCodice(),
                'sede_mittente_id' => $sedeMittenteId,
                'sede_destinataria_id' => $sedeDestinatariaId,
                'data_invio' => now()->toDateString(),
                'data_scadenza' => now()->addYear()->toDateString(),
                'stato' => 'attivo',
                'note' => $note,
                'creato_da' => Auth::id(),
            ]);

            // Processa articoli
            foreach ($articoli as $articoloData) {
                $this->inviaArticoloInDeposito(
                    $contoDeposito,
                    $articoloData['articolo_id'],
                    $articoloData['quantita'],
                    $articoloData['costo_unitario'] ?? null
                );
            }

            // Processa prodotti finiti
            foreach ($prodottiFiniti as $pfData) {
                $this->inviaProdottoFinitoInDeposito(
                    $contoDeposito,
                    $pfData['prodotto_finito_id'],
                    $pfData['costo_unitario'] ?? null
                );
            }

            // Aggiorna statistiche
            $contoDeposito->aggiornaStatistiche();

            return $contoDeposito;
        });

        try {
            $notificaService = new NotificaService();
            $notificaService->notificaNuovoDeposito($contoDeposito);
        } catch (\Throwable $e) {
            Log::warning("Errore invio notifica nuovo deposito {$contoDeposito->codice}: " . $e->getMessage());
        }

        return $contoDeposito;
    }

    /**
     * Invia un articolo in conto deposito
     */
    public function inviaArticoloInDeposito(
        ContoDeposito $contoDeposito,
        int $articoloId,
        int $quantita,
        ?float $costoUnitario = null
    ): MovimentoDeposito {
        $articolo = Articolo::with('giacenza')->findOrFail($articoloId);

        // Validazioni
        // Per il conto deposito consideriamo disponibile anche se in vetrina
        $qtaDisponibile = $articolo->getQuantitaDisponibilePerMovimentazione();
        if ($quantita > $qtaDisponibile) {
            throw new \InvalidArgumentException(
                "Quantità richiesta ({$quantita}) superiore alla disponibile ({$qtaDisponibile}) per {$articolo->codice}"
            );
        }

        $costoUnitario = $costoUnitario ?? $articolo->prezzo_acquisto ?? 0;

        return DB::transaction(function () use ($contoDeposito, $articolo, $quantita, $costoUnitario) {
            $qtaDisponibile = $articolo->giacenza?->quantita_residua ?? $articolo->giacenza?->quantita ?? 0;
            if ($quantita < $qtaDisponibile) {
                $articolo = app(ArticoloSplitService::class)->splitArticolo($articolo, $quantita);
            }

            // Se l'articolo è in vetrina, lo rimuoviamo automaticamente
            if ($articolo->in_vetrina) {
                $articolo->update([
                    'in_vetrina' => false,
                    'stato' => 'disponibile',
                ]);

                $articoliVetrina = ArticoloVetrina::where('articolo_id', $articolo->id)
                    ->whereNull('data_rimozione')
                    ->get();

                foreach ($articoliVetrina as $articoloVetrina) {
                    $dataInserimento = $articoloVetrina->data_inserimento
                        ? Carbon::parse($articoloVetrina->data_inserimento)
                        : null;
                    $giorniEsposizione = $dataInserimento ? $dataInserimento->diffInDays(now()) : null;

                    $articoloVetrina->update([
                        'data_rimozione' => now()->toDateString(),
                        'giorni_esposizione' => $giorniEsposizione,
                    ]);
                }
            }

            // Se deposito inter-società, muovi quantità tra giacenze_sedi
            // SALVA il magazzino originale nei dettagli del movimento
            $magazzinoOriginaleId = $articolo->categoria_merceologica_id;
            $magazzinoOriginaleLogico = $articolo->magazzino_logico
                ?? app(MagazzinoLogicoService::class)->resolveFromCategoriaId($magazzinoOriginaleId);
            
            if ($contoDeposito->isInterSocieta()) {
                $societaDestinataria = $contoDeposito->getSocietaDestinataria();
                if ($societaDestinataria) {
                    $magazzinoCD = $societaDestinataria->getMagazzinoContoDeposito();
                    if ($magazzinoCD) {
                        // Aggiorna giacenze per sede
                        $mittenteId = $contoDeposito->sede_mittente_id;
                        $destId = $contoDeposito->sede_destinataria_id;
                        $from = GiacenzaSede::firstOrCreate([
                            'articolo_id' => $articolo->id,
                            'sede_id' => $mittenteId,
                        ]);
                        $to = GiacenzaSede::firstOrCreate([
                            'articolo_id' => $articolo->id,
                            'sede_id' => $destId,
                        ]);
                        $from->increment('quantita', 0); // ensure exists
                        $from->decrement('quantita_residua', $quantita);
                        $to->increment('quantita', $quantita);
                        $to->increment('quantita_residua', $quantita);
                        // Associa magazzino CD (per visualizzazione depositi)
                        $articolo->update([
                            'categoria_merceologica_id' => $magazzinoCD->id,
                            'magazzino_logico' => $magazzinoOriginaleLogico,
                        ]);
                    }
                }
            }
            
            // Crea movimento con dettagli magazzino originale
            $movimento = MovimentoDeposito::creaInvio(
                $contoDeposito,
                $articolo,
                $quantita,
                $costoUnitario,
                null, // DDT verrà associato successivamente
                "Invio in conto deposito {$contoDeposito->codice}"
            );
            
            // Salva magazzino originale nei dettagli del movimento (per reso)
            if (isset($magazzinoOriginaleId)) {
                $movimento->update([
                    'dettagli' => array_merge($movimento->dettagli ?? [], [
                        'magazzino_originale_id' => $magazzinoOriginaleId,
                        'magazzino_originale_logico' => $magazzinoOriginaleLogico,
                    ])
                ]);
            }

            // Aggiorna articolo
            $articolo->aggiornaQuantitaInDeposito();

            return $movimento;
        });
    }

    /**
     * Invia un prodotto finito in conto deposito
     */
    public function inviaProdottoFinitoInDeposito(
        ContoDeposito $contoDeposito,
        int $prodottoFinitoId,
        ?float $costoUnitario = null
    ): MovimentoDeposito {
        $prodottoFinito = ProdottoFinito::findOrFail($prodottoFinitoId);

        // Validazioni
        if (!$prodottoFinito->isDisponibilePerDeposito()) {
            throw new \InvalidArgumentException("Il prodotto finito {$prodottoFinito->codice} non è disponibile per il deposito");
        }

        $costoUnitario = $costoUnitario ?? $prodottoFinito->costo_totale ?? 0;

        return DB::transaction(function () use ($contoDeposito, $prodottoFinito, $costoUnitario) {
            // Se deposito inter-società, PF rimane nella sede originale ma viene associato
            // al magazzino CD della società destinataria (per visualizzazione)
            // NOTA: I PF potrebbero non avere categoria_merceologica_id, verificare struttura
            
            // Crea movimento
            $movimento = MovimentoDeposito::creaInvio(
                $contoDeposito,
                $prodottoFinito,
                1, // I PF sono sempre quantità 1
                $costoUnitario,
                null, // DDT verrà associato successivamente
                "Invio PF in conto deposito {$contoDeposito->codice}"
            );

            // Aggiorna prodotto finito
            $prodottoFinito->aggiornaStatoDeposito();

            return $movimento;
        });
    }

    /**
     * Rimuove un articolo dal conto deposito PRIMA della creazione DDT invio.
     */
    public function rimuoviArticoloDaDepositoPrimaDdt(ContoDeposito $contoDeposito, int $articoloId): void
    {
        if ($contoDeposito->ddt_invio_id && $contoDeposito->ddtInvio) {
            throw new \Exception('Impossibile rimuovere: il DDT di invio è già stato generato.');
        }

        DB::transaction(function () use ($contoDeposito, $articoloId) {
            $articolo = Articolo::findOrFail($articoloId);

            $movimentiInvio = $contoDeposito->movimenti()
                ->where('articolo_id', $articoloId)
                ->where('tipo_movimento', 'invio')
                ->whereNull('ddt_id')
                ->get();

            if ($movimentiInvio->isEmpty()) {
                throw new \Exception("Articolo {$articolo->codice} non presente nel deposito (invio non trovato).");
            }

            $quantita = (int) $movimentiInvio->sum('quantita');

            if ($contoDeposito->isInterSocieta() && $quantita > 0) {
                $magazzinoOriginaleId = $movimentiInvio->first()->dettagli['magazzino_originale_id'] ?? null;
                $magazzinoOriginaleLogico = $movimentiInvio->first()->dettagli['magazzino_originale_logico'] ?? null;
                if ($magazzinoOriginaleId) {
                    $articolo->categoria_merceologica_id = $magazzinoOriginaleId;
                }
                if ($magazzinoOriginaleLogico) {
                    $articolo->magazzino_logico = (int) $magazzinoOriginaleLogico;
                }

                $mittenteId = $contoDeposito->sede_mittente_id;
                $destId = $contoDeposito->sede_destinataria_id;
                $from = GiacenzaSede::firstOrCreate(['articolo_id' => $articolo->id, 'sede_id' => $destId]);
                $to = GiacenzaSede::firstOrCreate(['articolo_id' => $articolo->id, 'sede_id' => $mittenteId]);
                $from->decrement('quantita_residua', $quantita);
                $to->increment('quantita_residua', $quantita);
            }

            $movimentiInvio->each->delete();
            $articolo->save();
            $articolo->aggiornaQuantitaInDeposito();
        });

        $contoDeposito->aggiornaStatistiche();
    }

    /**
     * Rimuove un prodotto finito dal conto deposito PRIMA della creazione DDT invio.
     */
    public function rimuoviProdottoFinitoDaDepositoPrimaDdt(ContoDeposito $contoDeposito, int $prodottoFinitoId): void
    {
        if ($contoDeposito->ddt_invio_id && $contoDeposito->ddtInvio) {
            throw new \Exception('Impossibile rimuovere: il DDT di invio è già stato generato.');
        }

        DB::transaction(function () use ($contoDeposito, $prodottoFinitoId) {
            $prodottoFinito = ProdottoFinito::findOrFail($prodottoFinitoId);

            $movimentiInvio = $contoDeposito->movimenti()
                ->where('prodotto_finito_id', $prodottoFinitoId)
                ->where('tipo_movimento', 'invio')
                ->whereNull('ddt_id')
                ->get();

            if ($movimentiInvio->isEmpty()) {
                throw new \Exception("Prodotto finito {$prodottoFinito->codice} non presente nel deposito (invio non trovato).");
            }

            $movimentiInvio->each->delete();
            $prodottoFinito->aggiornaStatoDeposito();
        });

        $contoDeposito->aggiornaStatistiche();
    }

    /**
     * Registra una vendita dal conto deposito
     */
    public function registraVendita(
        ContoDeposito $contoDeposito,
        $item, // Articolo o ProdottoFinito
        int $quantita,
        ?ProformaDeposito $proforma = null
    ): MovimentoDeposito {
        $isArticolo = $item instanceof Articolo;
        $costoUnitario = $isArticolo ? $item->prezzo_acquisto : $item->costo_totale;

        return DB::transaction(function () use ($contoDeposito, $item, $quantita, $costoUnitario, $proforma, $isArticolo) {
            // Crea movimento vendita
            $movimento = MovimentoDeposito::creaVendita(
                $contoDeposito,
                $item,
                $quantita,
                $costoUnitario,
                $proforma,
                "Vendita da conto deposito {$contoDeposito->codice}"
            );

            // Aggiorna item
            if ($isArticolo) {
                $item->aggiornaQuantitaInDeposito();
                
                // Se venduto tutto, aggiorna giacenza
                if ($quantita >= $item->quantita_in_deposito) {
                    $item->giacenza->update([
                        'quantita_residua' => max(0, $item->giacenza->quantita_residua - $quantita)
                    ]);
                }
            } else {
                // Vendita ProdottoFinito - scaricare componenti (consumo a vendita)
                Log::info("🏆 Vendita PF ID {$item->id}: scarico componenti...");
                
                // Carica componenti con articoli
                $item->load(['componentiArticoli.articolo', 'articoloRisultante.giacenza']);
                
                // Scarica ogni componente dal deposito
                foreach ($item->componentiArticoli as $componente) {
                    $articoloComponente = $componente->articolo;
                    $quantitaDaScaricare = $componente->quantita * $quantita; // quantità componente x quantità PF venduti
                    
                    Log::info("📦 Scarico articolo {$articoloComponente->codice}: {$quantitaDaScaricare} unità");
                    
                    // Registra movimento di scarico per il componente
                    MovimentoDeposito::creaVendita(
                        $contoDeposito,
                        $articoloComponente,
                        $quantitaDaScaricare,
                        $componente->costo_unitario,
                        $proforma,
                        "Scarico componente da vendita PF {$item->codice}"
                    );
                    
                    // Aggiorna quantità in deposito del componente
                    $articoloComponente->aggiornaQuantitaInDeposito();
                    
                    // Aggiorna giacenza se necessario
                    if ($articoloComponente->giacenza) {
                        $articoloComponente->giacenza->update([
                            'quantita_residua' => max(0, $articoloComponente->giacenza->quantita_residua - $quantitaDaScaricare)
                        ]);
                    }
                }
                
                // Scarica anche la giacenza del PF (articolo risultante) se presente
                if ($item->articoloRisultante && $item->articoloRisultante->giacenza) {
                    $item->articoloRisultante->giacenza->update([
                        'quantita_residua' => max(0, $item->articoloRisultante->giacenza->quantita_residua - $quantita)
                    ]);
                }
                
                // Marca il PF come venduto
                $item->update(['stato' => 'venduto']);
                $item->aggiornaStatoDeposito();
                
                Log::info("✅ PF {$item->codice} venduto e componenti scaricati");
            }

            // Aggiorna statistiche deposito
            $contoDeposito->aggiornaStatistiche();
            
            // Invia notifica vendita solo se deposito inter-società
            if ($contoDeposito->isInterSocieta()) {
                try {
                    $notificaService = new NotificaService();
                    $notificaService->notificaVendita($contoDeposito, $movimento);
                } catch (\Exception $e) {
                Log::warning("Errore creazione notifica vendita: " . $e->getMessage());
                    // Non bloccare il processo per errori di notifica
                }
            }

            return $movimento;
        });
    }

    /**
     * Gestisce il reso automatico alla scadenza
     */
    public function gestisciResoScadenza(ContoDeposito $contoDeposito): Collection
    {
        if (!$contoDeposito->isScaduto()) {
            throw new \InvalidArgumentException('Il deposito non è ancora scaduto');
        }

        return DB::transaction(function () use ($contoDeposito) {
            $movimentiReso = collect();

            // Reso articoli rimanenti
            $articoliRimanenti = $this->getArticoliRimanentiInDeposito($contoDeposito);
            foreach ($articoliRimanenti as $articoloData) {
                $movimento = MovimentoDeposito::create([
                    'conto_deposito_id' => $contoDeposito->id,
                    'articolo_id' => $articoloData['articolo']->id,
                    'tipo_movimento' => 'reso',
                    'quantita' => $articoloData['quantita'],
                    'costo_unitario' => $articoloData['costo_unitario'],
                    'costo_totale' => $articoloData['quantita'] * $articoloData['costo_unitario'],
                    'data_movimento' => now()->toDateString(),
                    'note' => "Reso automatico scadenza {$contoDeposito->codice}",
                ]);

                $articoloData['articolo']->aggiornaQuantitaInDeposito();
                $movimentiReso->push($movimento);
            }

            // Reso prodotti finiti rimanenti
            $prodottiFinitiRimanenti = $this->getProdottiFinitiRimanentiInDeposito($contoDeposito);
            foreach ($prodottiFinitiRimanenti as $pfData) {
                $movimento = MovimentoDeposito::create([
                    'conto_deposito_id' => $contoDeposito->id,
                    'prodotto_finito_id' => $pfData['prodotto_finito']->id,
                    'tipo_movimento' => 'reso',
                    'quantita' => 1,
                    'costo_unitario' => $pfData['costo_unitario'],
                    'costo_totale' => $pfData['costo_unitario'],
                    'data_movimento' => now()->toDateString(),
                    'note' => "Reso automatico scadenza {$contoDeposito->codice}",
                ]);

                $pfData['prodotto_finito']->update(['in_conto_deposito' => false]);
                $movimentiReso->push($movimento);
            }

            // Aggiorna stato deposito
            $contoDeposito->update(['stato' => 'chiuso']);
            $contoDeposito->aggiornaStatistiche();

            return $movimentiReso;
        });
    }

    /**
     * Gestisce il reso manuale di articoli specifici (prima della scadenza)
     * 
     * @param ContoDeposito $contoDeposito
     * @param array $articoli Array di ['articolo_id' => id, 'quantita' => qta]
     * @param array $prodottiFiniti Array di ['prodotto_finito_id' => id]
     * @return Collection Movimenti reso creati
     */
    public function gestisciResoManuale(ContoDeposito $contoDeposito, array $articoli = [], array $prodottiFiniti = []): Collection
    {
        if (empty($articoli) && empty($prodottiFiniti)) {
            throw new \InvalidArgumentException('Seleziona almeno un articolo o prodotto finito da restituire');
        }

        return DB::transaction(function () use ($contoDeposito, $articoli, $prodottiFiniti) {
            $movimentiReso = collect();

            // Reso articoli selezionati
            foreach ($articoli as $articoloData) {
                $articolo = Articolo::findOrFail($articoloData['articolo_id']);
                
                // Verifica che l'articolo sia in deposito
                if ($articolo->conto_deposito_corrente_id !== $contoDeposito->id) {
                    throw new \Exception("L'articolo {$articolo->codice} non è in questo deposito");
                }

                // Verifica quantità disponibile
                $quantitaDisponibile = $articolo->quantita_in_deposito ?? 0;
                $quantitaDaRestituire = $articoloData['quantita'] ?? $quantitaDisponibile;
                
                if ($quantitaDaRestituire > $quantitaDisponibile) {
                    throw new \Exception("Quantità richiesta ({$quantitaDaRestituire}) superiore a quella disponibile ({$quantitaDisponibile}) per {$articolo->codice}");
                }

                // Calcola costo unitario (dal movimento di invio o costo totale articolo)
                $movimentoInvio = $contoDeposito->movimenti()
                    ->where('articolo_id', $articolo->id)
                    ->where('tipo_movimento', 'invio')
                    ->first();
                
                $costoUnitario = $movimentoInvio ? $movimentoInvio->costo_unitario : ($articolo->costo_totale ?? 0);

                // Crea movimento reso
                $movimento = MovimentoDeposito::create([
                    'conto_deposito_id' => $contoDeposito->id,
                    'articolo_id' => $articolo->id,
                    'tipo_movimento' => 'reso',
                    'quantita' => $quantitaDaRestituire,
                    'costo_unitario' => $costoUnitario,
                    'costo_totale' => $quantitaDaRestituire * $costoUnitario,
                    'data_movimento' => now()->toDateString(),
                    'note' => "Reso manuale articolo {$articolo->codice}",
                    'eseguito_da' => Auth::id(),
                ]);

                // Aggiorna quantità in deposito dell'articolo
                $articolo->quantita_in_deposito = max(0, $quantitaDisponibile - $quantitaDaRestituire);
                
                // Se quantità = 0, rimuovi il deposito corrente
                if ($articolo->quantita_in_deposito == 0) {
                    $articolo->conto_deposito_corrente_id = null;
                }
                
                // Ripristina magazzino originale se deposito inter-società (sempre, anche se reso parziale)
                if ($contoDeposito->isInterSocieta()) {
                    // Recupera magazzino originale dal movimento di invio
                    $movimentoInvio = $contoDeposito->movimenti()
                        ->where('articolo_id', $articolo->id)
                        ->where('tipo_movimento', 'invio')
                        ->first();
                    
                    $magazzinoOriginaleId = null;
                    
                    if ($movimentoInvio && isset($movimentoInvio->dettagli['magazzino_originale_id'])) {
                        // Usa magazzino salvato nei dettagli
                        $magazzinoOriginaleId = $movimentoInvio->dettagli['magazzino_originale_id'];
                        $articolo->magazzino_logico = $movimentoInvio->dettagli['magazzino_originale_logico']
                            ?? $articolo->magazzino_logico;
                    } else {
                        // Fallback: trova magazzino nella sede mittente (primo disponibile)
                        $sedeMittente = $contoDeposito->sedeMittente;
                        if ($sedeMittente) {
                            // Cerca magazzini attivi nella sede mittente (escludi CD)
                            $magazzinoOriginale = $sedeMittente->categorieMerceologiche()
                                ->where('attivo', true)
                                ->where('codice', 'NOT LIKE', 'CD-%') // Escludi magazzini CD
                                ->orderBy('id')
                                ->first();
                            
                            if ($magazzinoOriginale) {
                                $magazzinoOriginaleId = $magazzinoOriginale->id;
                            }
                        }
                    }
                    
                    // Se trovato, ripristina il magazzino originale
                    if ($magazzinoOriginaleId) {
                        $articolo->categoria_merceologica_id = $magazzinoOriginaleId;
                        // Move quantities back per sede
                        $mittenteId = $contoDeposito->sede_mittente_id;
                        $destId = $contoDeposito->sede_destinataria_id;
                        $from = GiacenzaSede::firstOrCreate(['articolo_id'=>$articolo->id,'sede_id'=>$destId]);
                        $to = GiacenzaSede::firstOrCreate(['articolo_id'=>$articolo->id,'sede_id'=>$mittenteId]);
                        $from->decrement('quantita_residua', $quantitaDaRestituire);
                        $to->increment('quantita_residua', $quantitaDaRestituire);
                    }
                }
                
                $articolo->save();
                $movimentiReso->push($movimento);
                
                // Invia notifica reso solo se deposito inter-società
                if ($contoDeposito->isInterSocieta()) {
                    try {
                        $notificaService = new NotificaService();
                        $notificaService->notificaReso($contoDeposito, $movimento);
                    } catch (\Exception $e) {
                        Log::warning("Errore creazione notifica reso: " . $e->getMessage());
                        // Non bloccare il processo per errori di notifica
                    }
                }
            }

            // Reso prodotti finiti selezionati
            foreach ($prodottiFiniti as $pfData) {
                $prodottoFinito = ProdottoFinito::findOrFail($pfData['prodotto_finito_id']);
                
                // Verifica che il PF sia in deposito
                if (!$prodottoFinito->in_conto_deposito || $prodottoFinito->conto_deposito_corrente_id !== $contoDeposito->id) {
                    throw new \Exception("Il prodotto finito {$prodottoFinito->codice} non è in questo deposito");
                }

                // Calcola costo (dal movimento di invio o costo totale PF)
                $movimentoInvio = $contoDeposito->movimenti()
                    ->where('prodotto_finito_id', $prodottoFinito->id)
                    ->where('tipo_movimento', 'invio')
                    ->first();
                
                $costoUnitario = $movimentoInvio ? $movimentoInvio->costo_unitario : ($prodottoFinito->costo_totale ?? 0);

                // Crea movimento reso
                $movimento = MovimentoDeposito::create([
                    'conto_deposito_id' => $contoDeposito->id,
                    'prodotto_finito_id' => $prodottoFinito->id,
                    'tipo_movimento' => 'reso',
                    'quantita' => 1,
                    'costo_unitario' => $costoUnitario,
                    'costo_totale' => $costoUnitario,
                    'data_movimento' => now()->toDateString(),
                    'note' => "Reso manuale prodotto finito {$prodottoFinito->codice}",
                    'eseguito_da' => Auth::id(),
                ]);

                // Rimuovi PF dal deposito
                $prodottoFinito->update([
                    'in_conto_deposito' => false,
                    'conto_deposito_corrente_id' => null
                ]);
                
                $movimentiReso->push($movimento);
                
                // Invia notifica reso solo se deposito inter-società
                if ($contoDeposito->isInterSocieta()) {
                    try {
                        $notificaService = new NotificaService();
                        $notificaService->notificaReso($contoDeposito, $movimento);
                    } catch (\Exception $e) {
                        Log::warning("Errore creazione notifica reso PF: " . $e->getMessage());
                    }
                }
            }

            // Aggiorna statistiche deposito
            $contoDeposito->aggiornaStatistiche();
            
            // Calcola articoli rimanenti REALI (inclusi quelli ancora in deposito)
            $articoliRimanentiRealCount = $this->getArticoliRimanentiInDeposito($contoDeposito)->sum('quantita');
            $pfRimanentiCount = $this->getProdottiFinitiRimanentiInDeposito($contoDeposito)->count();
            $totaleRimanenti = $articoliRimanentiRealCount + $pfRimanentiCount;
            
            // Aggiorna stato: chiudi SOLO se non ci sono più articoli/PF rimanenti
            if ($totaleRimanenti == 0) {
                $contoDeposito->update(['stato' => 'chiuso']);
            } else {
                // Se ci sono ancora articoli rimanenti, mantieni stato attivo/parziale/scaduto
                // NON chiudere il deposito finché ci sono articoli da restituire o vendere
                if ($contoDeposito->stato === 'attivo') {
                    // Se ha vendite o resi, diventa parziale, ma rimane gestibile
                    if ($contoDeposito->articoli_venduti > 0 || $contoDeposito->articoli_rientrati > 0) {
                        $contoDeposito->update(['stato' => 'parziale']);
                    }
                    // Se è scaduto, mantieni stato scaduto ma gestibile
                } elseif ($contoDeposito->isScaduto() && $contoDeposito->stato !== 'scaduto') {
                    $contoDeposito->update(['stato' => 'scaduto']);
                }
                // Se già parziale o scaduto, mantieni lo stato (rimane gestibile)
            }

            return $movimentiReso;
        });
    }

    /**
     * Crea un nuovo deposito identico (rimando dopo reso)
     */
    public function creaRimandoDopoReso(ContoDeposito $depositoOriginale): ContoDeposito
    {
        if (!$depositoOriginale->isChiuso()) {
            throw new \InvalidArgumentException('Il deposito deve essere chiuso per poter essere rimandato');
        }

        return DB::transaction(function () use ($depositoOriginale) {
            // Crea nuovo deposito
            $nuovoDeposito = ContoDeposito::create([
                'codice' => ContoDeposito::generaCodice(),
                'sede_mittente_id' => $depositoOriginale->sede_mittente_id,
                'sede_destinataria_id' => $depositoOriginale->sede_destinataria_id,
                'data_invio' => now()->toDateString(),
                'data_scadenza' => now()->addYear()->toDateString(),
                'stato' => 'attivo',
                'deposito_precedente_id' => $depositoOriginale->id,
                'note' => "Rimando del deposito {$depositoOriginale->codice}",
                'creato_da' => Auth::id(),
            ]);

            // Ricrea gli stessi movimenti del deposito originale
            $movimentiOriginali = $depositoOriginale->movimentiReso;
            foreach ($movimentiOriginali as $movimentoOriginale) {
                $item = $movimentoOriginale->getItem();
                
                MovimentoDeposito::creaRimando(
                    $nuovoDeposito,
                    $item,
                    $movimentoOriginale->quantita,
                    $movimentoOriginale->costo_unitario,
                    null, // DDT verrà associato successivamente
                    "Rimando da deposito {$depositoOriginale->codice}"
                );

                // Aggiorna stato item
                if ($item instanceof Articolo) {
                    $item->aggiornaQuantitaInDeposito();
                } else {
                    $item->aggiornaStatoDeposito();
                }
            }

            // Aggiorna statistiche
            $nuovoDeposito->aggiornaStatistiche();

            return $nuovoDeposito;
        });
    }

    /**
     * Ottieni articoli rimanenti in un deposito
     */
    public function getArticoliRimanentiInDeposito(ContoDeposito $contoDeposito): Collection
    {
        $movimentiInvio = $contoDeposito->movimenti()
            ->where('tipo_movimento', 'invio')
            ->whereNotNull('articolo_id')
            ->get()
            ->groupBy('articolo_id');

        $movimentiVendita = $contoDeposito->movimenti()
            ->where('tipo_movimento', 'vendita')
            ->whereNotNull('articolo_id')
            ->get()
            ->groupBy('articolo_id');

        // IMPORTANTE: Sottrai anche i movimenti di RESO
        $movimentiReso = $contoDeposito->movimenti()
            ->where('tipo_movimento', 'reso')
            ->whereNotNull('articolo_id')
            ->get()
            ->groupBy('articolo_id');

        $articoliRimanenti = collect();

        foreach ($movimentiInvio as $articoloId => $movimenti) {
            $qtaInviata = $movimenti->sum('quantita');
            $qtaVenduta = $movimentiVendita->get($articoloId, collect())->sum('quantita');
            $qtaResa = $movimentiReso->get($articoloId, collect())->sum('quantita');
            // Calcola rimanenti sottraendo vendite E resi
            $qtaRimanente = $qtaInviata - $qtaVenduta - $qtaResa;

            if ($qtaRimanente > 0) {
                // Bypass global scope sede: il destinatario deve poter vedere gli articoli del deposito
                $articolo = Articolo::withoutGlobalScopes()->find($articoloId);
                if ($articolo) {
                    $articoliRimanenti->push([
                        'articolo' => $articolo,
                        'quantita' => $qtaRimanente,
                        'costo_unitario' => $movimenti->first()->costo_unitario
                    ]);
                }
            }
        }

        return $articoliRimanenti;
    }

    /**
     * Ottieni prodotti finiti rimanenti in un deposito
     */
    public function getProdottiFinitiRimanentiInDeposito(ContoDeposito $contoDeposito): Collection
    {
        $movimentiInvio = $contoDeposito->movimenti()
            ->where('tipo_movimento', 'invio')
            ->whereNotNull('prodotto_finito_id')
            ->get();

        $movimentiVendita = $contoDeposito->movimenti()
            ->where('tipo_movimento', 'vendita')
            ->whereNotNull('prodotto_finito_id')
            ->get()
            ->pluck('prodotto_finito_id')
            ->toArray();

        // IMPORTANTE: Escludi anche i PF già resi
        $movimentiReso = $contoDeposito->movimenti()
            ->where('tipo_movimento', 'reso')
            ->whereNotNull('prodotto_finito_id')
            ->get()
            ->pluck('prodotto_finito_id')
            ->toArray();

        $prodottiFinitiRimanenti = collect();

        foreach ($movimentiInvio as $movimento) {
            $pfId = $movimento->prodotto_finito_id;
            // Escludi se venduto O se già reso
            if (!in_array($pfId, $movimentiVendita) && !in_array($pfId, $movimentiReso)) {
                $prodottoFinito = ProdottoFinito::with([
                        'componentiArticoli.articolo' => function($q){ $q->withoutGlobalScopes(); },
                        'componentiArticoli.articolo.categoriaMerceologica'
                    ])
                    ->find($pfId);
                
                if ($prodottoFinito) {
                    $prodottiFinitiRimanenti->push([
                        'prodotto_finito' => $prodottoFinito,
                        'costo_unitario' => $movimento->costo_unitario,
                        'componenti' => $prodottoFinito->componentiArticoli->map(function ($componente) {
                            return [
                                'articolo' => $componente->articolo,
                                'quantita' => $componente->quantita,
                                'costo_unitario' => $componente->costo_unitario,
                                'costo_totale' => $componente->costo_totale,
                                'stato' => $componente->stato,
                            ];
                        })
                    ]);
                }
            }
        }

        return $prodottiFinitiRimanenti;
    }

    /**
     * Genera DDT di invio per il deposito
     * 
     * @param ContoDeposito $contoDeposito
     * @return DdtDeposito
     */
    public function generaDdtInvio(ContoDeposito $contoDeposito, array $datiDdt = []): DdtDeposito
    {
        return DB::transaction(function () use ($contoDeposito) {
            // Usa data_invio se disponibile, altrimenti oggi
            $dataDocumento = $contoDeposito->data_invio ?? now()->toDateString();
            $anno = $contoDeposito->data_invio ? $contoDeposito->data_invio->year : now()->year;

            // Genera numero DDT progressivo automatico
            $numeroDdt = DdtDeposito::generaNumeroDdt();

            // Crea DDT Deposito
            $note = !empty($datiDdt['note'])
                ? trim($datiDdt['note'])
                : "DDT invio conto deposito {$contoDeposito->codice}";

            $configurazione = array_filter([
                'trasporto_mezzo' => $datiDdt['trasporto_mezzo'] ?? null,
                'aspetto_beni' => $datiDdt['aspetto_beni'] ?? null,
            ]);

            $ddtDeposito = DdtDeposito::create([
                'numero' => $numeroDdt,
                'data_documento' => $dataDocumento,
                'anno' => $anno,
                'conto_deposito_id' => $contoDeposito->id,
                'tipo' => 'invio',
                'sede_mittente_id' => $contoDeposito->sede_mittente_id,
                'sede_destinataria_id' => $contoDeposito->sede_destinataria_id,
                'stato' => 'creato',
                'causale' => $datiDdt['causale'] ?? 'Conto deposito',
                'numero_colli' => $datiDdt['numero_colli'] ?? null,
                'corriere' => $datiDdt['corriere'] ?? null,
                'numero_tracking' => $datiDdt['numero_tracking'] ?? null,
                'valore_dichiarato' => $contoDeposito->valore_totale_invio ?? 0,
                'articoli_totali' => $contoDeposito->articoli_inviati ?? 0,
                'note' => $note,
                'configurazione' => !empty($configurazione) ? $configurazione : null,
                'creato_da' => Auth::id(),
            ]);


            $articoliTotali = 0;
            $valoreTotale = 0;

            // Articoli attualmente nel deposito
            $articoliRimanenti = $this->getArticoliRimanentiInDeposito($contoDeposito);
            foreach ($articoliRimanenti as $articoloData) {
                $articolo = $articoloData['articolo'];
                $quantita = $articoloData['quantita'];
                $costoUnitario = $articoloData['costo_unitario'];
                $costoTotale = $quantita * $costoUnitario;

                DdtDepositoDettaglio::create([
                    'ddt_deposito_id' => $ddtDeposito->id,
                    'articolo_id' => $articolo->id,
                    'codice_item' => $articolo->codice,
                    'descrizione' => $articolo->descrizione,
                    'quantita' => $quantita,
                    'valore_unitario' => $costoUnitario,
                    'valore_totale' => $costoTotale,
                ]);

                $articoliTotali += $quantita;
                $valoreTotale += $costoTotale;
            }

            // Prodotti finiti attualmente nel deposito
            $pfRimanenti = $this->getProdottiFinitiRimanentiInDeposito($contoDeposito);
            foreach ($pfRimanenti as $pfData) {
                $pf = $pfData['prodotto_finito'];
                $costoUnitario = $pfData['costo_unitario'];

                DdtDepositoDettaglio::create([
                    'ddt_deposito_id' => $ddtDeposito->id,
                    'prodotto_finito_id' => $pf->id,
                    'codice_item' => $pf->codice,
                    'descrizione' => $pf->descrizione,
                    'quantita' => 1,
                    'valore_unitario' => $costoUnitario,
                    'valore_totale' => $costoUnitario,
                ]);

                $articoliTotali += 1;
                $valoreTotale += $costoUnitario;
            }

            $ddtDeposito->update([
                'articoli_totali' => $articoliTotali,
                'valore_dichiarato' => $valoreTotale,
            ]);

            // Aggiorna deposito con DDT
            $contoDeposito->update(['ddt_invio_id' => $ddtDeposito->id]);

            return $ddtDeposito;
        });
    }

    /**
     * Genera DDT di reso per il deposito
     * 
     * @param ContoDeposito $contoDeposito
     * @return DdtDeposito
     */
    public function generaDdtReso(ContoDeposito $contoDeposito, array $datiDdt = []): DdtDeposito
    {
        return DB::transaction(function () use ($contoDeposito) {
            // Genera numero DDT progressivo automatico
            $numeroDdt = DdtDeposito::generaNumeroDdt();

            // Crea DDT Deposito per reso
            $note = !empty($datiDdt['note'])
                ? trim($datiDdt['note'])
                : "DDT reso conto deposito {$contoDeposito->codice}";

            $configurazione = array_filter([
                'trasporto_mezzo' => $datiDdt['trasporto_mezzo'] ?? null,
                'aspetto_beni' => $datiDdt['aspetto_beni'] ?? null,
            ]);

            $ddtDeposito = DdtDeposito::create([
                'numero' => $numeroDdt,
                'data_documento' => now()->toDateString(),
                'anno' => now()->year,
                'conto_deposito_id' => $contoDeposito->id,
                'tipo' => 'reso',
                'sede_mittente_id' => $contoDeposito->sede_destinataria_id, // Ora il destinatario diventa mittente
                'sede_destinataria_id' => $contoDeposito->sede_mittente_id, // E il mittente diventa destinatario
                'stato' => 'creato',
                'causale' => $datiDdt['causale'] ?? 'Reso conto deposito',
                'numero_colli' => $datiDdt['numero_colli'] ?? null,
                'corriere' => $datiDdt['corriere'] ?? null,
                'numero_tracking' => $datiDdt['numero_tracking'] ?? null,
                'note' => $note,
                'configurazione' => !empty($configurazione) ? $configurazione : null,
                'creato_da' => Auth::id(),
            ]);

            $articoliTotali = 0;
            $valoreTotale = 0;

            // Ottieni movimenti reso NON ANCORA inclusi in un DDT
            // Verifica quali movimenti sono già in un DDT di reso esistente
            $ddtResiEsistenti = $contoDeposito->ddtResi()->with('dettagli')->get();
            $movimentiGiaInDdt = collect();
            
            foreach ($ddtResiEsistenti as $ddtReso) {
                foreach ($ddtReso->dettagli as $dettaglio) {
                    $movimentiGiaInDdt->push([
                        'articolo_id' => $dettaglio->articolo_id,
                        'prodotto_finito_id' => $dettaglio->prodotto_finito_id,
                        'quantita' => $dettaglio->quantita,
                    ]);
                }
            }
            
            // Ottieni TUTTI i movimenti reso e filtra quelli già in DDT
            $tuttiMovimentiReso = $contoDeposito->movimenti()
                ->where('tipo_movimento', 'reso')
                ->with(['articolo', 'prodottoFinito'])
                ->get();

            // Filtra movimenti che NON sono già in un DDT
            $movimentiReso = $tuttiMovimentiReso->filter(function ($movimento) use ($movimentiGiaInDdt) {
                foreach ($movimentiGiaInDdt as $giaInDdt) {
                    // Se corrisponde esattamente (stesso articolo/PF e stessa quantità), è già incluso
                    if ($giaInDdt['articolo_id'] == $movimento->articolo_id && 
                        $giaInDdt['prodotto_finito_id'] == $movimento->prodotto_finito_id &&
                        $giaInDdt['quantita'] == $movimento->quantita) {
                        return false; // Escludi questo movimento
                    }
                }
                return true; // Include questo movimento
            });

            if ($movimentiReso->isNotEmpty()) {
                // Usa solo i movimenti reso NON ancora inclusi in un DDT
                foreach ($movimentiReso as $movimento) {
                    $item = $movimento->getItem();
                    
                    DdtDepositoDettaglio::create([
                        'ddt_deposito_id' => $ddtDeposito->id,
                        'articolo_id' => $movimento->articolo_id,
                        'prodotto_finito_id' => $movimento->prodotto_finito_id,
                        'codice_item' => $item->codice,
                        'descrizione' => $item->descrizione,
                        'quantita' => $movimento->quantita,
                        'valore_unitario' => $movimento->costo_unitario,
                        'valore_totale' => $movimento->costo_totale,
                    ]);
                    
                    $articoliTotali += $movimento->quantita;
                    $valoreTotale += $movimento->costo_totale;
                }
            } else {
                // Se non ci sono movimenti reso, usa gli articoli rimanenti (per reso automatico completo)
                // Aggiungi dettagli per articoli rimanenti
                $articoliRimanenti = $this->getArticoliRimanentiInDeposito($contoDeposito);
                foreach ($articoliRimanenti as $articoloData) {
                    $valoreTotaleRiga = $articoloData['quantita'] * $articoloData['costo_unitario'];
                    
                    DdtDepositoDettaglio::create([
                        'ddt_deposito_id' => $ddtDeposito->id,
                        'articolo_id' => $articoloData['articolo']->id,
                        'codice_item' => $articoloData['articolo']->codice,
                        'descrizione' => $articoloData['articolo']->descrizione,
                        'quantita' => $articoloData['quantita'],
                        'valore_unitario' => $articoloData['costo_unitario'],
                        'valore_totale' => $valoreTotaleRiga,
                    ]);
                    
                    $articoliTotali += $articoloData['quantita'];
                    $valoreTotale += $valoreTotaleRiga;
                }

                // Aggiungi dettagli per PF rimanenti
                $pfRimanenti = $this->getProdottiFinitiRimanentiInDeposito($contoDeposito);
                foreach ($pfRimanenti as $pfData) {
                    DdtDepositoDettaglio::create([
                        'ddt_deposito_id' => $ddtDeposito->id,
                        'prodotto_finito_id' => $pfData['prodotto_finito']->id,
                        'codice_item' => $pfData['prodotto_finito']->codice,
                        'descrizione' => $pfData['prodotto_finito']->descrizione,
                        'quantita' => 1,
                        'valore_unitario' => $pfData['costo_unitario'],
                        'valore_totale' => $pfData['costo_unitario'],
                    ]);
                    
                    $articoliTotali += 1;
                    $valoreTotale += $pfData['costo_unitario'];
                }
            }

            // Aggiorna totali nel DDT
            $ddtDeposito->update([
                'articoli_totali' => $articoliTotali,
                'valore_dichiarato' => $valoreTotale,
            ]);

            // Aggiorna deposito con DDT reso (SOLO se è il primo, altrimenti mantiene il primo)
            // Usiamo ddt_reso_id come riferimento al primo DDT per compatibilità
            // Ma tutti i DDT sono accessibili tramite ddtResi()
            if (!$contoDeposito->ddt_reso_id) {
                $contoDeposito->update(['ddt_reso_id' => $ddtDeposito->id]);
            }
            
            // Aggiorna tutti i movimenti reso con il riferimento al DDT
            // Nota: ddt_id punta a Ddt, ma DdtDeposito è separato
            // Per ora non aggiorniamo ddt_id, ma useremo un approccio alternativo
            // I movimenti saranno collegati al DDT attraverso ddtDeposito->movimenti()
            
            return $ddtDeposito;
        });
    }

    /**
     * Calcola dati quantitativi utili al rinnovo (inviati, venduti, resi, rimanenti)
     */
    protected function calcolaDatiRinnovo(ContoDeposito $contoDeposito): array
    {
        $movimentiInvioArticoli = $contoDeposito->movimenti()
            ->where('tipo_movimento', 'invio')
            ->whereNotNull('articolo_id')
            ->get()
            ->groupBy('articolo_id');

        $movimentiVenditaArticoli = $contoDeposito->movimenti()
            ->where('tipo_movimento', 'vendita')
            ->whereNotNull('articolo_id')
            ->get()
            ->groupBy('articolo_id');

        $movimentiResoArticoli = $contoDeposito->movimenti()
            ->where('tipo_movimento', 'reso')
            ->whereNotNull('articolo_id')
            ->get()
            ->groupBy('articolo_id');

        $datiArticoli = [];

        foreach ($movimentiInvioArticoli as $articoloId => $movimenti) {
            $articolo = Articolo::withoutGlobalScopes()->find($articoloId);
            if (!$articolo) {
                continue;
            }

            $quantitaInviata = $movimenti->sum('quantita');
            $quantitaVenduta = $movimentiVenditaArticoli->get($articoloId, collect())->sum('quantita');
            $quantitaResa = $movimentiResoArticoli->get($articoloId, collect())->sum('quantita');
            $quantitaDaRinnovare = max(0, $quantitaInviata - $quantitaVenduta);
            if ($quantitaDaRinnovare <= 0) {
                continue; // tutto venduto
            }

            $quantitaAncoraInDeposito = max(0, $quantitaDaRinnovare - $quantitaResa);
            $costoUnitario = $movimenti->first()->costo_unitario ?? 0;

            $datiArticoli[] = [
                'articolo' => $articolo,
                'costo_unitario' => $costoUnitario,
                'quantita_inviata' => $quantitaInviata,
                'quantita_venduta' => $quantitaVenduta,
                'quantita_resa' => $quantitaResa,
                'quantita_ancora_in_deposito' => $quantitaAncoraInDeposito,
                'quantita_da_rinnovare' => $quantitaDaRinnovare,
            ];
        }

        $movimentiInvioPf = $contoDeposito->movimenti()
            ->where('tipo_movimento', 'invio')
            ->whereNotNull('prodotto_finito_id')
            ->get()
            ->groupBy('prodotto_finito_id');

        $movimentiVenditaPf = $contoDeposito->movimenti()
            ->where('tipo_movimento', 'vendita')
            ->whereNotNull('prodotto_finito_id')
            ->get()
            ->groupBy('prodotto_finito_id');

        $movimentiResoPf = $contoDeposito->movimenti()
            ->where('tipo_movimento', 'reso')
            ->whereNotNull('prodotto_finito_id')
            ->get()
            ->groupBy('prodotto_finito_id');

        $datiPf = [];

        foreach ($movimentiInvioPf as $pfId => $movimenti) {
            $pf = ProdottoFinito::find($pfId);
            if (!$pf) {
                continue;
            }

            $quantitaInviata = $movimenti->sum('quantita'); // normalmente 1
            $quantitaVenduta = $movimentiVenditaPf->get($pfId, collect())->sum('quantita');
            $quantitaResa = $movimentiResoPf->get($pfId, collect())->sum('quantita');
            $quantitaDaRinnovare = max(0, $quantitaInviata - $quantitaVenduta);
            if ($quantitaDaRinnovare <= 0) {
                continue;
            }

            $quantitaAncoraInDeposito = max(0, $quantitaDaRinnovare - $quantitaResa);
            $costoUnitario = $movimenti->first()->costo_unitario ?? 0;

            $datiPf[] = [
                'prodotto_finito' => $pf,
                'costo_unitario' => $costoUnitario,
                'quantita_inviata' => $quantitaInviata,
                'quantita_venduta' => $quantitaVenduta,
                'quantita_resa' => $quantitaResa,
                'quantita_ancora_in_deposito' => $quantitaAncoraInDeposito,
                'quantita_da_rinnovare' => $quantitaDaRinnovare,
            ];
        }

        return [
            'articoli' => $datiArticoli,
            'prodotti_finiti' => $datiPf,
        ];
    }

    /**
     * Rinnova un conto deposito:
     * - crea DDT di reso con tutti gli articoli rimasti (non venduti/non resi)
     * - crea nuovo conto deposito (1 anno) con stesso mittente/destinataria
     * - genera DDT di invio per il nuovo deposito e crea movimenti di invio
     * - numerazione DDT separata per sede mittente
     */
    public function rinnovaDeposito(ContoDeposito $contoDeposito, string $modalita = 'rimanenti'): ContoDeposito
    {
        $modalita = strtolower($modalita);
        if (!in_array($modalita, ['rimanenti', 'tutti'])) {
            $modalita = 'rimanenti';
        }

        return DB::transaction(function () use ($contoDeposito, $modalita) {
            $dati = $this->calcolaDatiRinnovo($contoDeposito);

            $articoliRimanenti = array_values(array_filter($dati['articoli'], function ($item) {
                return $item['quantita_ancora_in_deposito'] > 0;
            }));

            $pfRimanenti = array_values(array_filter($dati['prodotti_finiti'], function ($item) {
                return $item['quantita_ancora_in_deposito'] > 0;
            }));

            if ($modalita === 'rimanenti' && empty($articoliRimanenti) && empty($pfRimanenti)) {
                throw new \RuntimeException('Nessun articolo rimasto nel deposito da rinnovare.');
            }

            $articoliDaInviare = ($modalita === 'tutti')
                ? array_values(array_filter($dati['articoli'], function ($item) {
                    return $item['quantita_da_rinnovare'] > 0;
                }))
                : $articoliRimanenti;

            $pfDaInviare = ($modalita === 'tutti')
                ? array_values(array_filter($dati['prodotti_finiti'], function ($item) {
                    return $item['quantita_da_rinnovare'] > 0;
                }))
                : $pfRimanenti;

            if (empty($articoliDaInviare) && empty($pfDaInviare)) {
                throw new \RuntimeException('Nessun articolo disponibile per il rinnovo.');
            }

            // 1) Crea DDT di reso per i soli articoli ancora presenti fisicamente
            $ddtReso = null;
            if (!empty($articoliRimanenti) || !empty($pfRimanenti)) {
                $numeroReso = DdtDeposito::generaNumeroPerSedeFormatted($contoDeposito->sede_destinataria_id);
                $ddtReso = DdtDeposito::create([
                    'numero' => $numeroReso,
                    'data_documento' => now()->toDateString(),
                    'anno' => now()->year,
                    'conto_deposito_id' => $contoDeposito->id,
                    'tipo' => 'reso',
                    'sede_mittente_id' => $contoDeposito->sede_destinataria_id,
                    'sede_destinataria_id' => $contoDeposito->sede_mittente_id,
                    'stato' => 'creato',
                    'causale' => 'Reso per rinnovo conto deposito',
                    'creato_da' => Auth::id(),
                ]);

                $articoliTotReso = 0;
                $valoreTotReso = 0;

                foreach ($articoliRimanenti as $item) {
                    $quantitaReso = $item['quantita_ancora_in_deposito'];
                    if ($quantitaReso <= 0) {
                        continue;
                    }

                    MovimentoDeposito::creaReso(
                        $contoDeposito,
                        $item['articolo'],
                        $quantitaReso,
                        $item['costo_unitario'],
                        null,
                        'Reso per rinnovo'
                    );

                    DdtDepositoDettaglio::create([
                        'ddt_deposito_id' => $ddtReso->id,
                        'articolo_id' => $item['articolo']->id,
                        'codice_item' => $item['articolo']->codice,
                        'descrizione' => $item['articolo']->descrizione,
                        'quantita' => $quantitaReso,
                        'valore_unitario' => $item['costo_unitario'],
                        'valore_totale' => $item['costo_unitario'] * $quantitaReso,
                    ]);

                    $articoliTotReso += $quantitaReso;
                    $valoreTotReso += $item['costo_unitario'] * $quantitaReso;
                }

                foreach ($pfRimanenti as $item) {
                    if ($item['quantita_ancora_in_deposito'] <= 0) {
                        continue;
                    }

                    MovimentoDeposito::creaReso(
                        $contoDeposito,
                        $item['prodotto_finito'],
                        $item['quantita_ancora_in_deposito'],
                        $item['costo_unitario'],
                        null,
                        'Reso per rinnovo'
                    );

                    DdtDepositoDettaglio::create([
                        'ddt_deposito_id' => $ddtReso->id,
                        'prodotto_finito_id' => $item['prodotto_finito']->id,
                        'codice_item' => $item['prodotto_finito']->codice,
                        'descrizione' => $item['prodotto_finito']->descrizione,
                        'quantita' => $item['quantita_ancora_in_deposito'],
                        'valore_unitario' => $item['costo_unitario'],
                        'valore_totale' => $item['costo_unitario'] * $item['quantita_ancora_in_deposito'],
                    ]);

                    $articoliTotReso += $item['quantita_ancora_in_deposito'];
                    $valoreTotReso += $item['costo_unitario'] * $item['quantita_ancora_in_deposito'];
                }

                $ddtReso->update([
                    'articoli_totali' => $articoliTotReso,
                    'valore_dichiarato' => $valoreTotReso,
                ]);

                if (!$contoDeposito->ddt_reso_id) {
                    $contoDeposito->update(['ddt_reso_id' => $ddtReso->id]);
                }
            }

            // 2) Crea nuovo conto deposito (1 anno)
            $nuovo = ContoDeposito::create([
                'codice' => $this->generaCodiceDeposito(),
                'sede_mittente_id' => $contoDeposito->sede_mittente_id,
                'sede_destinataria_id' => $contoDeposito->sede_destinataria_id,
                'data_invio' => now()->toDateString(),
                'data_scadenza' => now()->addYear()->toDateString(),
                'stato' => 'attivo',
                'deposito_precedente_id' => $contoDeposito->id,
                'creato_da' => Auth::id(),
            ]);

            // 3) Crea DDT invio per nuovo deposito
            $numeroInvio = DdtDeposito::generaNumeroPerSedeFormatted($contoDeposito->sede_mittente_id);
            $ddtInvio = DdtDeposito::create([
                'numero' => $numeroInvio,
                'data_documento' => now()->toDateString(),
                'anno' => now()->year,
                'conto_deposito_id' => $nuovo->id,
                'tipo' => 'invio',
                'sede_mittente_id' => $contoDeposito->sede_mittente_id,
                'sede_destinataria_id' => $contoDeposito->sede_destinataria_id,
                'stato' => 'creato',
                'causale' => 'Invio per rinnovo conto deposito',
                'creato_da' => Auth::id(),
            ]);

            $articoliTotInvio = 0;
            $valoreTotInvio = 0;

            foreach ($articoliDaInviare as $item) {
                $quantita = $modalita === 'tutti'
                    ? $item['quantita_da_rinnovare']
                    : $item['quantita_ancora_in_deposito'];

                if ($quantita <= 0) {
                    continue;
                }

                MovimentoDeposito::creaInvio(
                    $nuovo,
                    $item['articolo'],
                    $quantita,
                    $item['costo_unitario'],
                    null,
                    'Rinnovo deposito'
                );

                DdtDepositoDettaglio::create([
                    'ddt_deposito_id' => $ddtInvio->id,
                    'articolo_id' => $item['articolo']->id,
                    'codice_item' => $item['articolo']->codice,
                    'descrizione' => $item['articolo']->descrizione,
                    'quantita' => $quantita,
                    'valore_unitario' => $item['costo_unitario'],
                    'valore_totale' => $item['costo_unitario'] * $quantita,
                ]);

                $articoliTotInvio += $quantita;
                $valoreTotInvio += $item['costo_unitario'] * $quantita;
            }

            foreach ($pfDaInviare as $item) {
                $quantita = $modalita === 'tutti'
                    ? $item['quantita_da_rinnovare']
                    : $item['quantita_ancora_in_deposito'];

                if ($quantita <= 0) {
                    continue;
                }

                MovimentoDeposito::creaInvio(
                    $nuovo,
                    $item['prodotto_finito'],
                    $quantita,
                    $item['costo_unitario'],
                    null,
                    'Rinnovo deposito'
                );

                DdtDepositoDettaglio::create([
                    'ddt_deposito_id' => $ddtInvio->id,
                    'prodotto_finito_id' => $item['prodotto_finito']->id,
                    'codice_item' => $item['prodotto_finito']->codice,
                    'descrizione' => $item['prodotto_finito']->descrizione,
                    'quantita' => $quantita,
                    'valore_unitario' => $item['costo_unitario'],
                    'valore_totale' => $item['costo_unitario'] * $quantita,
                ]);

                $articoliTotInvio += $quantita;
                $valoreTotInvio += $item['costo_unitario'] * $quantita;
            }

            $ddtInvio->update([
                'articoli_totali' => $articoliTotInvio,
                'valore_dichiarato' => $valoreTotInvio,
            ]);

            // 4) Aggiorna stati
            $nuovo->aggiornaStatistiche();
            $contoDeposito->update(['stato' => 'chiuso', 'chiuso_da' => Auth::id()]);

            return $nuovo;
        });
    }

    /**
     * Genera un codice per il nuovo deposito (semplice progressivo temporaneo)
     */
    private function generaCodiceDeposito(): string
    {
        $seq = (int) (DB::table('conti_deposito')->max('id')) + 1;
        return 'CD-' . now()->year . '-' . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);
    }

    // Metodo generaNumeroDdt() rimosso - ora è nel modello DdtDeposito

    /**
     * Ottieni statistiche depositi per dashboard
     */
    public function getStatisticheDepositi(): array
    {
        $depositiAttivi = ContoDeposito::attivi()->count();
        $depositiInScadenza = ContoDeposito::inScadenza(30)->count();
        $depositiScaduti = ContoDeposito::scaduti()->count();
        $valoreTotale = ContoDeposito::attivi()->sum('valore_totale_invio');

        $proformeDaFatturare = ProformaDeposito::where('stato', ProformaDeposito::STATO_DA_FATTURARE)->count();
        $valoreDaFatturare = ProformaDeposito::where('stato', ProformaDeposito::STATO_DA_FATTURARE)->sum('totale');
        $proformeFatturate = ProformaDeposito::where('stato', ProformaDeposito::STATO_FATTURATA)->count();
        $fattureConPdf = ProformaDeposito::whereNotNull('fattura_pdf_path')->count();

        $depositiSenzaDdtReso = ContoDeposito::where('stato', '!=', 'chiuso')
            ->doesntHave('ddtResi')
            ->count();

        $articoliResidui = Articolo::whereNotNull('conto_deposito_corrente_id')->sum('quantita_in_deposito');
        $pfResidui = ProdottoFinito::where('in_conto_deposito', true)->count();

        return [
            'depositi_attivi' => $depositiAttivi,
            'depositi_in_scadenza' => $depositiInScadenza,
            'depositi_scaduti' => $depositiScaduti,
            'valore_totale_depositi' => $valoreTotale,
            'articoli_in_deposito' => MovimentoDeposito::whereHas('contoDeposito', function($query) {
                $query->where('stato', 'attivo');
            })->where('tipo_movimento', 'invio')->sum('quantita'),
            'proforme_da_fatturare' => $proformeDaFatturare,
            'valore_da_fatturare' => $valoreDaFatturare,
            'proforme_fatturate' => $proformeFatturate,
            'fatture_pdf' => $fattureConPdf,
            'depositi_senza_ddt_reso' => $depositiSenzaDdtReso,
            'giacenze_residue' => (int) $articoliResidui + (int) $pfResidui,
        ];
    }
}
