<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * MovimentoDeposito - Tracking movimenti articoli nei conti deposito
 * 
 * Traccia ogni singolo movimento di articoli/PF nei conti deposito:
 * - Invio iniziale
 * - Vendite dalla sede destinataria
 * - Resi alla sede mittente
 * - Rimandi dopo reso
 */
class MovimentoDeposito extends Model
{
    protected $table = 'movimenti_deposito';
    
    protected $fillable = [
        'conto_deposito_id',
        'articolo_id',
        'prodotto_finito_id',
        'tipo_item',
        'tipo_movimento', 
        'quantita',
        'costo_unitario',
        'costo_totale',
        'data_movimento',
        'ddt_id',
        'fattura_id', // Fatture di ACQUISTO (legacy)
        'proforma_id', // Proforma deposito
        'note',
        'dettagli',
        'eseguito_da',
    ];
    
    protected $casts = [
        'quantita' => 'integer',
        'costo_unitario' => 'decimal:2',
        'costo_totale' => 'decimal:2',
        'data_movimento' => 'datetime',
        'dettagli' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
    
    // ==========================================
    // RELATIONSHIPS
    // ==========================================
    
    /**
     * Conto deposito di riferimento
     */
    public function contoDeposito(): BelongsTo
    {
        return $this->belongsTo(ContoDeposito::class, 'conto_deposito_id');
    }
    
    /**
     * Articolo coinvolto nel movimento
     */
    public function articolo(): BelongsTo
    {
        return $this->belongsTo(Articolo::class, 'articolo_id');
    }
    
    /**
     * Prodotto finito coinvolto nel movimento
     */
    public function prodottoFinito(): BelongsTo
    {
        return $this->belongsTo(ProdottoFinito::class, 'prodotto_finito_id');
    }
    
    /**
     * DDT associato al movimento
     */
    public function ddt(): BelongsTo
    {
        return $this->belongsTo(Ddt::class, 'ddt_id');
    }
    
    /**
     * Fattura di ACQUISTO associata al movimento (legacy, non usata per vendite)
     */
    public function fattura(): BelongsTo
    {
        return $this->belongsTo(Fattura::class, 'fattura_id');
    }
    
    /**
     * Proforma associata al movimento
     */
    public function proforma(): BelongsTo
    {
        return $this->belongsTo(ProformaDeposito::class, 'proforma_id');
    }
    
    /**
     * Utente che ha eseguito il movimento
     */
    public function eseguitoDa(): BelongsTo
    {
        return $this->belongsTo(User::class, 'eseguito_da');
    }
    
    // ==========================================
    // SCOPES
    // ==========================================
    
    public function scopeInvii($query)
    {
        return $query->where('tipo_movimento', 'invio');
    }
    
    public function scopeVendite($query)
    {
        return $query->where('tipo_movimento', 'vendita');
    }
    
    public function scopeResi($query)
    {
        return $query->where('tipo_movimento', 'reso');
    }
    
    public function scopeRimandi($query)
    {
        return $query->where('tipo_movimento', 'rimando');
    }
    
    public function scopePerArticolo($query, $articoloId)
    {
        return $query->where('articolo_id', $articoloId);
    }
    
    public function scopePerProdottoFinito($query, $prodottoFinitoId)
    {
        return $query->where('prodotto_finito_id', $prodottoFinitoId);
    }
    
    public function scopePerPeriodo($query, $dataInizio, $dataFine)
    {
        return $query->whereBetween('data_movimento', [$dataInizio, $dataFine]);
    }
    
    // ==========================================
    // BUSINESS LOGIC
    // ==========================================
    
    /**
     * Verifica se il movimento riguarda un articolo
     */
    public function isArticolo(): bool
    {
        return !is_null($this->articolo_id);
    }
    
    /**
     * Verifica se il movimento riguarda un prodotto finito
     */
    public function isProdottoFinito(): bool
    {
        return !is_null($this->prodotto_finito_id);
    }
    
    /**
     * Ottieni l'item coinvolto (articolo o PF)
     */
    public function getItem()
    {
        return $this->isArticolo() ? $this->articolo : $this->prodottoFinito;
    }
    
    /**
     * Ottieni il codice dell'item
     */
    public function getCodiceItem(): string
    {
        $item = $this->getItem();
        return $item ? $item->codice : 'N/A';
    }
    
    /**
     * Ottieni la descrizione dell'item
     */
    public function getDescrizioneItem(): string
    {
        $item = $this->getItem();
        return $item ? $item->descrizione : 'N/A';
    }
    
    /**
     * Verifica se è un movimento di entrata (invio/rimando)
     */
    public function isEntrata(): bool
    {
        return in_array($this->tipo_movimento, ['invio', 'rimando']);
    }
    
    /**
     * Verifica se è un movimento di uscita (vendita/reso)
     */
    public function isUscita(): bool
    {
        return in_array($this->tipo_movimento, ['vendita', 'reso']);
    }
    
    /**
     * Crea movimento di invio
     */
    public static function creaInvio(
        ContoDeposito $contoDeposito,
        $item,
        int $quantita,
        float $costoUnitario,
        ?Ddt $ddt = null,
        ?string $note = null
    ): self {
        $isArticolo = $item instanceof Articolo;
        
        return static::create([
            'conto_deposito_id' => $contoDeposito->id,
            'articolo_id' => $isArticolo ? $item->id : null,
            'prodotto_finito_id' => $isArticolo ? null : $item->id,
            'tipo_item' => $isArticolo ? 'articolo' : 'prodotto_finito',
            'tipo_movimento' => 'invio',
            'quantita' => $quantita,
            'costo_unitario' => $costoUnitario,
            'costo_totale' => $quantita * $costoUnitario,
            'data_movimento' => now(),
            'ddt_id' => $ddt?->id,
            'note' => $note,
            'eseguito_da' => auth()->id(),
        ]);
    }
    
    /**
     * Crea movimento di vendita
     */
    public static function creaVendita(
        ContoDeposito $contoDeposito,
        $item,
        int $quantita,
        float $costoUnitario,
        ?ProformaDeposito $proforma = null,
        ?string $note = null
    ): self {
        $isArticolo = $item instanceof Articolo;
        
        return static::create([
            'conto_deposito_id' => $contoDeposito->id,
            'articolo_id' => $isArticolo ? $item->id : null,
            'prodotto_finito_id' => $isArticolo ? null : $item->id,
            'tipo_item' => $isArticolo ? 'articolo' : 'prodotto_finito',
            'tipo_movimento' => 'vendita',
            'quantita' => $quantita,
            'costo_unitario' => $costoUnitario,
            'costo_totale' => $quantita * $costoUnitario,
            'data_movimento' => now(),
            'fattura_id' => null, // Non usiamo più fattura_id per vendite
            'proforma_id' => $proforma?->id,
            'note' => $note,
            'eseguito_da' => auth()->id(),
        ]);
    }
    
    /**
     * Crea movimento di reso
     */
    public static function creaReso(
        ContoDeposito $contoDeposito,
        $item,
        int $quantita,
        float $costoUnitario,
        ?Ddt $ddt = null,
        ?string $note = null
    ): self {
        $isArticolo = $item instanceof Articolo;
        
        return static::create([
            'conto_deposito_id' => $contoDeposito->id,
            'articolo_id' => $isArticolo ? $item->id : null,
            'prodotto_finito_id' => $isArticolo ? null : $item->id,
            'tipo_item' => $isArticolo ? 'articolo' : 'prodotto_finito',
            'tipo_movimento' => 'reso',
            'quantita' => $quantita,
            'costo_unitario' => $costoUnitario,
            'costo_totale' => $quantita * $costoUnitario,
            'data_movimento' => now(),
            'ddt_id' => $ddt?->id,
            'note' => $note,
            'eseguito_da' => auth()->id(),
        ]);
    }
    
    /**
     * Crea movimento di rimando
     */
    public static function creaRimando(
        ContoDeposito $contoDeposito,
        $item,
        int $quantita,
        float $costoUnitario,
        ?Ddt $ddt = null,
        ?string $note = null
    ): self {
        $isArticolo = $item instanceof Articolo;
        
        return static::create([
            'conto_deposito_id' => $contoDeposito->id,
            'articolo_id' => $isArticolo ? $item->id : null,
            'prodotto_finito_id' => $isArticolo ? null : $item->id,
            'tipo_item' => $isArticolo ? 'articolo' : 'prodotto_finito',
            'tipo_movimento' => 'rimando',
            'quantita' => $quantita,
            'costo_unitario' => $costoUnitario,
            'costo_totale' => $quantita * $costoUnitario,
            'data_movimento' => now(),
            'ddt_id' => $ddt?->id,
            'note' => $note,
            'eseguito_da' => auth()->id(),
        ]);
    }
    
    // ==========================================
    // MUTATORS & ACCESSORS
    // ==========================================
    
    /**
     * Calcola automaticamente il costo totale
     */
    public function setCostoUnitarioAttribute($value)
    {
        $this->attributes['costo_unitario'] = $value;
        if (isset($this->attributes['quantita'])) {
            $this->attributes['costo_totale'] = $value * $this->attributes['quantita'];
        }
    }
    
    /**
     * Calcola automaticamente il costo totale
     */
    public function setQuantitaAttribute($value)
    {
        $this->attributes['quantita'] = $value;
        if (isset($this->attributes['costo_unitario'])) {
            $this->attributes['costo_totale'] = $value * $this->attributes['costo_unitario'];
        }
    }
    
    /**
     * Tipo movimento con colore per UI
     */
    public function getTipoMovimentoColorAttribute(): string
    {
        return match($this->tipo_movimento) {
            'invio' => 'primary',
            'vendita' => 'success',
            'reso' => 'warning',
            'rimando' => 'info',
            default => 'secondary'
        };
    }
    
    /**
     * Tipo movimento leggibile
     */
    public function getTipoMovimentoLabelAttribute(): string
    {
        return match($this->tipo_movimento) {
            'invio' => 'Invio',
            'vendita' => 'Vendita',
            'reso' => 'Reso',
            'rimando' => 'Rimando',
            default => 'Sconosciuto'
        };
    }
    
    /**
     * Icona per tipo movimento
     */
    public function getTipoMovimentoIconAttribute(): string
    {
        return match($this->tipo_movimento) {
            'invio' => 'solar:export-bold',
            'vendita' => 'solar:cart-check-bold',
            'reso' => 'solar:import-bold',
            'rimando' => 'solar:refresh-bold',
            default => 'solar:question-circle-bold'
        };
    }
    
    /**
     * Costo totale formattato
     */
    public function getCostoTotaleFormattedAttribute(): string
    {
        return '€' . number_format($this->costo_totale, 2, ',', '.');
    }
}