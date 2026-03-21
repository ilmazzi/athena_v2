<?php

namespace App\Models;

use App\Models\ValueObjects\CodiceArticolo;
use App\Models\ValueObjects\PrezzoAcquisto;
use App\Models\ValueObjects\StatoArticolo;
use App\Models\Sede;
use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\FiltersBySede;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Articolo - Aggregate Root del dominio Magazzino
 * 
 * Business Rules (CRITICHE - Cliente compliance):
 * - Codice univoco per magazzino (id_magazzino-carico)
 * - Relazione 1:1 obbligatoria con Giacenza
 * - ❌ NO prezzo_vendita nel DB (solo su etichette ZPL!)
 * - ❌ NO data_scarico nel DB (solo stato ENUM!)
 * - ✅ Prezzo acquisto immutabile post-carico
 * - ✅ Identificazione univoca: (magazzino_id, carico)
 */
class Articolo extends Model
{
    use SoftDeletes, FiltersBySede;

    protected static function booted(): void
    {
        if (!app()->runningInConsole()) {
            static::addGlobalScope('user_sede', function ($query) {
                $sedeId = auth()->user()?->sede_id;
                if ($sedeId) {
                    $table = $query->getModel()->getTable();
                    $query->where(function($q) use ($table, $sedeId){
                        $q->where($table . '.sede_id', $sedeId)
                          ->orWhereExists(function($sub) use ($sedeId, $table){
                              $sub->selectRaw(1)
                                  ->from('giacenze_sedi')
                                  ->whereColumn('giacenze_sedi.articolo_id', $table.'.id')
                                  ->where('giacenze_sedi.sede_id', $sedeId)
                                  ->where('giacenze_sedi.quantita_residua', '>', 0);
                          });
                    });
                }
            });
        }
    }
    
    protected $table = 'articoli';
    
    protected $fillable = [
        'codice',
        'codice_base',
        'descrizione',
        'descrizione_estesa',
        'categoria_merceologica_id',
        'magazzino_logico',
        'sede_id',
        'prodotto_finito_id',
        'articolo_padre_id',
        'split_index',
        'assemblato_il',
        'assemblato_da',
        'peso_lordo',
        'peso_netto',
        'titolo',
        'caratura',
        'materiale',
        'colore',
        'prezzo_acquisto',  // ✅ Salvato in DB
        'prezzo_fornitore', // ✅ Prezzo vendita concordato (non costo)
        'stato',            // ✅ ENUM vecchio (manteniamo per retrocompatibilità)
        'stato_articolo',   // ✅ ENUM nuovo (disponibile, in_prodotto_finito, scaricato, scaricato_in_pf)
        'scarico_id',       // ✅ Link a tabella scarichi (opzionale, per tracciabilità interna)
        'tipo_carico',
        'numero_documento_carico',
        'data_carico',
        'in_vetrina',
        'inventariato',
        'visibile_catalogo',
        'note',
        'foto_principale',
        'foto_aggiuntive',
        'caratteristiche',
        'ean',
        'numero_seriale',
        'modello',
        'ultimo_testo_vetrina',
        'conto_deposito_corrente_id',
        'quantita_in_deposito',
    ];
    
    protected $casts = [
        'peso_lordo' => 'decimal:2',
        'peso_netto' => 'decimal:2',
        'titolo' => 'string',
        'caratura' => 'string',
        'prezzo_acquisto' => 'decimal:2',
        'prezzo_fornitore' => 'decimal:2',
        'data_carico' => 'date',
        'assemblato_il' => 'datetime',
        'in_vetrina' => 'boolean',
        'inventariato' => 'boolean',
        'visibile_catalogo' => 'boolean',
        'split_index' => 'integer',
        'magazzino_logico' => 'integer',
        'foto_aggiuntive' => 'array',
        'caratteristiche' => 'array',
        'modello' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];
    
    // ==========================================
    // RELATIONSHIPS
    // ==========================================
    
    public function categoriaMerceologica(): BelongsTo
    {
        return $this->belongsTo(CategoriaMerceologica::class, 'categoria_merceologica_id');
    }
    
    /**
     * Alias per compatibilità frontend (chiamato ancora "magazzino")
     */
    public function magazzino(): BelongsTo
    {
        return $this->categoriaMerceologica();
    }
    
    /**
     * Sede fisica corrente dell'articolo
     */
    public function categoria(): BelongsTo
    {
        return $this->belongsTo(CategoriaMerceologica::class, 'categoria_merceologica_id');
    }
    
    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class, 'sede_id');
    }
    
    
    /**
     * Prodotto finito (se questo articolo È un prodotto finito assemblato)
     */
    public function prodottoFinito(): BelongsTo
    {
        return $this->belongsTo(ProdottoFinito::class, 'prodotto_finito_id');
    }
    
    /**
     * Utente che ha assemblato (se prodotto finito)
     */
    public function assemblatoDa(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assemblato_da');
    }
    
    /**
     * Componenti utilizzati (se questo articolo è stato usato come componente)
     */
    public function componentiUtilizzato(): HasMany
    {
        return $this->hasMany(ComponenteProdotto::class, 'articolo_id');
    }

    public function articoloPadre(): BelongsTo
    {
        return $this->belongsTo(self::class, 'articolo_padre_id');
    }

    public function articoliFigli(): HasMany
    {
        return $this->hasMany(self::class, 'articolo_padre_id');
    }
    
    /**
     * Relazione 1:1 OBBLIGATORIA con Giacenza
     * 
     * Business Rule:
     * - Ogni articolo DEVE avere UNA giacenza
     * - Un articolo fisico può essere in UNA SOLA sede
     * - La giacenza traccia: quantita (originale), quantita_residua (disponibile)
     */
    public function giacenza(): HasOne
    {
        return $this->hasOne(Giacenza::class, 'articolo_id');
    }
    
    /**
     * Relazione con giacenze (plurale per compatibilità)
     * 
     * Business Rule:
     * - Ogni articolo DEVE avere UNA giacenza
     * - Un articolo fisico può essere in UNA SOLA sede
     * - La giacenza traccia: quantita (originale), quantita_residua (disponibile)
     */
    public function giacenze(): HasMany
    {
        return $this->hasMany(Giacenza::class, 'articolo_id');
    }

    /**
     * Scansioni inventario associate all'articolo
     */
    public function scansioni(): HasMany
    {
        return $this->hasMany(InventarioScansione::class, 'articolo_id');
    }

    public function giacenzePerSede(): HasMany
    {
        return $this->hasMany(GiacenzaSede::class, 'articolo_id');
    }

    /**
     * Relazione con articoli in vetrina (pivot table)
     */
    public function articoliVetrina(): HasMany
    {
        return $this->hasMany(ArticoloVetrina::class, 'articolo_id');
    }

    /**
     * Conto deposito corrente (se l'articolo è in deposito)
     */
    public function contoDepositoCorrente(): BelongsTo
    {
        return $this->belongsTo(ContoDeposito::class, 'conto_deposito_corrente_id');
    }

    /**
     * Tutti i movimenti deposito dell'articolo
     */
    public function movimentiDeposito(): HasMany
    {
        return $this->hasMany(MovimentoDeposito::class, 'articolo_id');
    }

    // ==========================================
    // BUSINESS LOGIC - STATI ARTICOLI
    // ==========================================

    /**
     * Calcola lo stato dinamico dell'articolo basato su condizioni multiple
     */
    public function getStatoArticoloDinamico(): string
    {
        // 1. Prima priorità: se è in vetrina
        if ($this->in_vetrina) {
            return 'in_vetrina';
        }
        
        // 2. Seconda priorità: se è in conto deposito
        $qtaInDeposito = $this->quantita_in_deposito ?? 0;
        if ($qtaInDeposito > 0) {
            $qtaResidua = $this->giacenza->quantita_residua ?? 0;
            
            // Se TUTTA la quantità è in deposito
            if ($qtaInDeposito >= $qtaResidua) {
                return 'in_conto_deposito';
            } else {
                // Quantità parziale in deposito
                return 'parzialmente_in_deposito';
            }
        }
        
        // 3. Basato sulla giacenza
        $qtaResidua = $this->giacenza->quantita_residua ?? 0;
        if ($qtaResidua > 0) {
            return 'giacente';
        } else {
            return 'scaricato';
        }
    }

    /**
     * Verifica se l'articolo è in conto deposito
     */
    public function isInContoDeposito(): bool
    {
        return ($this->quantita_in_deposito ?? 0) > 0 && !is_null($this->conto_deposito_corrente_id);
    }

    /**
     * Verifica se l'articolo è parzialmente in conto deposito
     */
    public function isParzialmenteInDeposito(): bool
    {
        $qtaInDeposito = $this->quantita_in_deposito ?? 0;
        $qtaResidua = $this->giacenza->quantita_residua ?? 0;
        
        return $qtaInDeposito > 0 && $qtaInDeposito < $qtaResidua;
    }

    /**
     * Verifica se l'articolo è completamente in conto deposito
     */
    public function isCompletamenteInDeposito(): bool
    {
        $qtaInDeposito = $this->quantita_in_deposito ?? 0;
        $qtaResidua = $this->giacenza->quantita_residua ?? 0;
        
        return $qtaInDeposito > 0 && $qtaInDeposito >= $qtaResidua;
    }

    /**
     * Ottieni la quantità disponibile (non in deposito e non in vetrina)
     */
    public function getQuantitaDisponibile(): int
    {
        $qtaResidua = $this->giacenza->quantita_residua ?? 0;
        $qtaInDeposito = $this->quantita_in_deposito ?? 0;
        $qtaInVetrina = $this->in_vetrina ? 1 : 0; // Assumiamo 1 se in vetrina
        
        return max(0, $qtaResidua - $qtaInDeposito - $qtaInVetrina);
    }
    
    /**
     * Calcola quantità disponibile per movimentazioni interne
     * 
     * Per le movimentazioni interne:
     * - Articoli in vetrina POSSONO essere movimentati (con warning)
     * - Articoli in deposito NON possono essere movimentati
     */
    public function getQuantitaDisponibilePerMovimentazione(): int
    {
        $qtaResidua = $this->giacenza->quantita_residua ?? 0;
        $qtaInDeposito = $this->quantita_in_deposito ?? 0;
        
        // Per movimentazioni interne, gli articoli in vetrina sono movimentabili
        // (a differenza delle vendite dove potrebbero essere bloccati)
        return max(0, $qtaResidua - $qtaInDeposito);
    }

    /**
     * Aggiorna la quantità in deposito
     */
    public function aggiornaQuantitaInDeposito(): void
    {
        $qtaTotaleInDeposito = $this->movimentiDeposito()
            ->whereHas('contoDeposito', function($query) {
                $query->where('stato', 'attivo');
            })
            ->where(function($query) {
                $query->where('tipo_movimento', 'invio')
                      ->orWhere('tipo_movimento', 'rimando');
            })
            ->sum('quantita');

        $qtaVendutaInDeposito = $this->movimentiDeposito()
            ->whereHas('contoDeposito', function($query) {
                $query->where('stato', 'attivo');
            })
            ->where('tipo_movimento', 'vendita')
            ->sum('quantita');

        $qtaResaInDeposito = $this->movimentiDeposito()
            ->whereHas('contoDeposito', function($query) {
                $query->where('stato', 'attivo');
            })
            ->where('tipo_movimento', 'reso')
            ->sum('quantita');

        $qtaAttualeInDeposito = $qtaTotaleInDeposito - $qtaVendutaInDeposito - $qtaResaInDeposito;

        $this->update([
            'quantita_in_deposito' => max(0, $qtaAttualeInDeposito),
            'conto_deposito_corrente_id' => $qtaAttualeInDeposito > 0 ? 
                $this->movimentiDeposito()
                    ->whereHas('contoDeposito', function($query) {
                        $query->where('stato', 'attivo');
                    })
                    ->latest('data_movimento')
                    ->value('conto_deposito_id') : null
        ]);
    }
    
    /**
     * Relazione: articolo usato come componente in prodotti finiti
     */
    public function componentiUtilizzatoIn(): HasMany
    {
        return $this->hasMany(\App\Models\ComponenteProdotto::class, 'articolo_id');
    }
    
    /**
     * Relazione con scansioni inventario
     */
    public function inventarioScansioni(): HasMany
    {
        return $this->hasMany(InventarioScansione::class, 'articolo_id');
    }
    
    /**
     * Alias per compatibilità
     */
    public function inventarioDettaglio(): HasMany
    {
        return $this->inventarioScansioni();
    }
    
    /**
     * Relazione con dettagli movimentazioni dell'articolo
     */
    public function movimentazioniDettagli(): HasMany
    {
        return $this->hasMany(MovimentazioneDettaglio::class, 'articolo_id');
    }
    
    /**
     * Relazione con movimentazioni dell'articolo (tramite dettagli)
     */
    public function movimentazioni()
    {
        return $this->hasManyThrough(
            Movimentazione::class,
            MovimentazioneDettaglio::class,
            'articolo_id',           // Foreign key su movimentazioni_dettagli
            'id',                    // Foreign key su movimentazioni
            'id',                    // Local key su articoli
            'movimentazione_id'      // Local key su movimentazioni_dettagli
        );
    }
    
    /**
     * Relazione con dettagli carico (nuovo sistema)
     */
    public function caricoDettagli(): HasMany
    {
        return $this->hasMany(CaricoDettaglio::class, 'articolo_id');
    }
    
    /**
     * Accessor per ottenere il primo carico (compatibilità vecchio/nuovo sistema)
     */
    public function getUltimoCaricoAttribute()
    {
        // Prima prova dal nuovo sistema (carico_dettagli)
        $dettaglio = $this->caricoDettagli()->with('carico')->first();
        if ($dettaglio && $dettaglio->carico) {
            return $dettaglio->carico;
        }
        
        // Fallback: dati legacy nella tabella articoli
        return null;
    }
    
    /**
     * Accessor per data carico (compatibilità vecchio/nuovo sistema)
     */
    public function getDataCaricoEffettivaAttribute()
    {
        // Prima prova dal nuovo sistema
        if ($this->ultimoCarico) {
            return $this->ultimoCarico->data_documento;
        }
        
        // Fallback: campo legacy
        return $this->data_carico;
    }
    
    /**
     * Accessor per numero documento carico (compatibilità vecchio/nuovo sistema)
     */
    public function getNumeroDocumentoCaricoEffettivoAttribute()
    {
        // Prima prova dal nuovo sistema
        if ($this->ultimoCarico) {
            return $this->ultimoCarico->numero_documento;
        }
        
        // Fallback: campo legacy
        return $this->numero_documento_carico;
    }
    
    /**
     * Relazione con DDT (tramite dettaglio)
     */
    public function ddtDettaglio(): HasMany
    {
        return $this->hasMany(DdtDettaglio::class, 'articolo_id');
    }
    
    /**
     * Relazione con Fatture (tramite dettaglio)
     */
    public function fatturaDettaglio(): HasMany
    {
        return $this->hasMany(FatturaDettaglio::class, 'articolo_id');
    }
    
    /**
     * Accessor per ottenere il DDT di carico (il primo)
     */
    public function getDdtCaricoAttribute()
    {
        return $this->ddtDettaglio()
            ->with('ddt.fornitore')
            ->first()?->ddt;
    }
    
    /**
     * Accessor per ottenere la Fattura di carico (la prima)
     */
    public function getFatturaCaricoAttribute()
    {
        return $this->fatturaDettaglio()
            ->with('fattura.fornitore')
            ->first()?->fattura;
    }
    
    // ==========================================
    // VALUE OBJECTS
    // ==========================================
    
    public function getCodiceVO(): ValueObjects\CodiceArticolo
    {
        return CodiceArticolo::fromString($this->codice_base ?: $this->codice);
    }
    
    public function getPrezzoAcquistoVO(): PrezzoAcquisto
    {
        return new PrezzoAcquisto($this->prezzo_acquisto);
    }
    
    public function getStatoVO(): StatoArticolo
    {
        return new StatoArticolo($this->stato);
    }
    
    // ==========================================
    // BUSINESS LOGIC
    // ==========================================
    
    /**
     * Verifica se l'articolo è disponibile per la vendita
     */
    public function isDisponibile(): bool
    {
        return $this->stato === StatoArticolo::DISPONIBILE 
            && $this->giacenza 
            && $this->giacenza->quantita > 0;
    }
    
    /**
     * Verifica se l'articolo è scaricato (venduto/trasferito)
     * 
     * ⚠️ IMPORTANTE: Non usiamo data_scarico!
     * Lo deduciamo da giacenza->quantita === 0
     */
    public function isScaricato(): bool
    {
        return $this->giacenza && $this->giacenza->quantita === 0;
    }
    
    /**
     * Verifica se l'articolo è in vetrina
     */
    public function isInVetrina(): bool
    {
        return $this->in_vetrina === true;
    }
    
    /**
     * Calcola prezzo vendita dinamicamente
     * 
     * ⚠️ CRITICO - COMPLIANCE CLIENTE:
     * - NON salvare MAI il risultato in DB!
     * - Questo metodo è SOLO per calcolo runtime
     * - Usare SOLO per stampare su etichette ZPL
     * - Il prezzo vendita vero passa come parametro alla stampa
     * 
     * @param float|null $percentualeRicarico Default 30%
     * @return float
     */
    public function calcolaPrezzoVenditaSuggerito(?float $percentualeRicarico = 30.0): float
    {
        return $this->getPrezzoAcquistoVO()->calcolaPrezzoVendita($percentualeRicarico);
    }
    
    /**
     * Ottieni descrizione completa per etichetta
     */
    public function getDescrizionePerEtichetta(): string
    {
        $parts = [$this->descrizione];
        
        if ($this->materiale) {
            $parts[] = $this->materiale;
        }
        
        if ($this->titolo) {
            $parts[] = $this->titolo . ' kt';
        }
        
        if ($this->caratura) {
            $parts[] = $this->caratura . ' ct';
        }
        
        return implode(' - ', array_filter($parts));
    }
    
    /**
     * Ottieni peso formattato per etichetta
     */
    public function getPesoFormattato(): string
    {
        if ($this->peso_netto) {
            return number_format($this->peso_netto, 2, ',', '.') . ' g';
        }
        if ($this->peso_lordo) {
            return number_format($this->peso_lordo, 2, ',', '.') . ' g (lordo)';
        }
        return '';
    }
    
    /**
     * Ottieni codice per etichetta (con radice magazzino)
     */
    public function getCodicePerEtichetta(): string
    {
        if ($this->articolo_padre_id && $this->split_index) {
            return $this->codice_visuale;
        }
        $vo = $this->getCodiceVO();
        if (method_exists($vo, 'toEtichetta')) {
            return $vo->toEtichetta();
        }
        if (method_exists($vo, 'toString')) {
            return $vo->toString();
        }

        return $this->codice ?? '';
    }
    
    /**
     * Verifica se l'articolo è usato in un prodotto finito completato
     */
    public function isInProdottoFinito(): bool
    {
        return $this->componentiUtilizzatoIn()
            ->whereHas('prodottoFinito', function($q) {
                $q->where('stato', 'completato');
            })
            ->exists();
    }
    
    /**
     * Ottieni il prodotto finito in cui è usato questo articolo
     */
    public function getProdottoFinitoAttribute()
    {
        return $this->componentiUtilizzatoIn()
            ->with('prodottoFinito')
            ->whereHas('prodottoFinito', function($q) {
                $q->where('stato', 'completato');
            })
            ->first()?->prodottoFinito;
    }
    
    // ==========================================
    // SCOPES
    // ==========================================
    
    public function scopeDisponibili($query)
    {
        return $query->where('stato', StatoArticolo::DISPONIBILE)
                     ->whereHas('giacenza', function($q) {
                         $q->where('quantita', '>', 0);
                     });
    }
    
    public function scopeInMagazzino($query, int $magazzinoId)
    {
        return $query->where('magazzino_id', $magazzinoId);
    }
    
    public function scopeInVetrina($query)
    {
        return $query->where('in_vetrina', true);
    }
    
    public function scopeScaricati($query)
    {
        return $query->whereHas('giacenza', function($q) {
            $q->where('quantita', 0);
        });
    }
    
    
    public function scopeConMateriale($query, string $materiale)
    {
        return $query->where('materiale', 'like', "%{$materiale}%");
    }
    
    public function scopeCaricatiNelPeriodo($query, \DateTime $da, \DateTime $a)
    {
        return $query->whereBetween('data_carico', [$da, $a]);
    }
    
    // ==========================================
    // ACCESSORS (convenienza per UI)
    // ==========================================
    
    public function getCodiceEtichettaAttribute(): string
    {
        return $this->getCodicePerEtichetta();
    }

    public function getCodiceBaseAttribute($value): string
    {
        if (!empty($value)) {
            return $value;
        }
        return $this->attributes['codice'] ?? '';
    }

    public function getCodiceVisualeAttribute(): string
    {
        if ($this->articolo_padre_id && $this->split_index) {
            return $this->codice_base . '-' . $this->split_index;
        }

        return $this->attributes['codice'] ?? '';
    }
    
    public function getPrezzoAcquistoFormattatoAttribute(): string
    {
        return $this->getPrezzoAcquistoVO()->format();
    }
    
    public function getStatoLabelAttribute(): string
    {
        return $this->getStatoVO()->getLabel();
    }
    
    public function getStatoBadgeClassAttribute(): string
    {
        return $this->getStatoVO()->getBadgeClass();
    }
}
