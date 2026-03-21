<?php

namespace App\Models;

use App\Models\CategoriaMerceologica;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * DDT (Documento Di Trasporto) - Aggregate Root
 * 
 * Testata DDT per carico merce
 */
class Ddt extends Model
{
    use SoftDeletes;
    
    protected $table = 'ddt';
    
    protected $fillable = [
        'numero',
        'anno',
        'data_documento',
        'tipo_documento', // Nuovo campo aggiunto
        'fornitore_id',
        'magazzino_id',
        'stato',
        'data_carico',
        'allegato_path',
        'note',
        'user_carico_id',
        'caricato_da', // Campo per tracking utente
        'tipo_carico',
        'ocr_document_id',
        'sede_id',
        'categoria_id',
        'magazzino_logico',
        'quantita_totale',
        'numero_articoli',
    ];
    
    protected $casts = [
        'data_documento' => 'date',
        'data_carico' => 'datetime',
        'anno' => 'integer',
        'magazzino_logico' => 'integer',
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
        return $this->hasMany(DdtDettaglio::class, 'ddt_id');
    }
    
    public function userCarico(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'caricato_da');
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
        return $this->hasMany(CaricoDettaglio::class, 'ddt_id');
    }
    
    // ==========================================
    // SCOPES
    // ==========================================
    
    public function scopeCaricati($query)
    {
        return $query->where('stato', 'caricato');
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
    
    public function isCaricato(): bool
    {
        return $this->stato === 'caricato';
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
     * Calcola valore totale DDT
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
