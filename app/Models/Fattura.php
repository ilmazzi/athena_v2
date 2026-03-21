<?php

namespace App\Models;

use App\Models\CategoriaMerceologica;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Fattura - Aggregate Root
 * 
 * Testata Fattura per carico merce
 */
class Fattura extends Model
{
    use SoftDeletes;
    
    protected $table = 'fatture';
    
    protected $fillable = [
        'numero',
        'anno',
        'data_documento',
        'fornitore_id',
        'magazzino_destinazione_id',
        'magazzino_id', // Legacy
        'stato',
        'data_carico',
        'allegato_path', // Legacy
        'totale', // Campo corretto per totale fattura
        'imponibile', // Totale senza IVA
        'iva',
        'note',
        'tipo_carico',
        'ocr_document_id',
        'sede_id',
        'categoria_id',
        'magazzino_logico',
        'partita_iva',
        'quantita_totale',
        'numero_articoli',
    ];
    
    protected $casts = [
        'data_documento' => 'date',
        'data_carico' => 'datetime',
        'anno' => 'integer',
        'magazzino_logico' => 'integer',
        'totale' => 'decimal:2',
        'imponibile' => 'decimal:2',
        'iva' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];
    
    // ==========================================
    // RELATIONSHIPS
    // ==========================================
    
    public function fornitore(): BelongsTo
    {
        return $this->belongsTo(Fornitore::class, 'fornitore_id');
    }
    
    public function magazzino(): BelongsTo
    {
        return $this->belongsTo(Magazzino::class, 'magazzino_id');
    }
    
    public function dettagli(): HasMany
    {
        return $this->hasMany(FatturaDettaglio::class, 'fattura_id');
    }
    
    public function userCarico(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'user_carico_id');
    }
    
    public function ocrDocument(): BelongsTo
    {
        return $this->belongsTo(OcrDocument::class, 'ocr_document_id');
    }
    
    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class, 'sede_id');
    }
    
    public function categoria(): BelongsTo
    {
        return $this->belongsTo(CategoriaMerceologica::class, 'categoria_id');
    }
    
    public function caricoDettagli(): HasMany
    {
        return $this->hasMany(CaricoDettaglio::class, 'fattura_id');
    }
    
    // ==========================================
    // SCOPES
    // ==========================================
    
    public function scopeCaricate($query)
    {
        return $query->where('stato', 'caricata');
    }
    
    public function scopeInAttesa($query)
    {
        return $query->where('stato', 'in_attesa');
    }
    
    public function scopeAnno($query, int $anno)
    {
        return $query->where('anno', $anno);
    }
    
    // ==========================================
    // BUSINESS LOGIC
    // ==========================================
    
    public function isCaricata(): bool
    {
        return $this->stato === 'caricata';
    }
    
    public function isInAttesa(): bool
    {
        return $this->stato === 'in_attesa';
    }
    
    /**
     * Calcola totale articoli
     */
    public function getTotaleArticoli(): int
    {
        return $this->dettagli()->sum('quantita');
    }
    
    /**
     * Calcola valore totale Fattura (da articoli)
     */
    public function getValoreTotale(): float
    {
        return $this->dettagli()
            ->with('articolo')
            ->get()
            ->sum(function ($dettaglio) {
                return $dettaglio->articolo->prezzo_acquisto * $dettaglio->quantita;
            });
    }
}
