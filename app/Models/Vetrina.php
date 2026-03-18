<?php

namespace App\Models;

use App\Models\Sede;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Vetrina - Aggregate Root
 * 
 * Rappresenta una vetrina fisica con articoli esposti
 */
class Vetrina extends Model
{
    use SoftDeletes;
    
    protected $table = 'vetrine';
    
    protected $fillable = [
        'codice',
        'nome',
        'tipologia',
        'sede_id',
        'attiva',
        'note',
    ];
    
    protected $casts = [
        'attiva' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];
    
    // ==========================================
    // RELATIONSHIPS
    // ==========================================
    
    // Rimossa relazione con magazzino - le vetrine hanno solo tipologia
    
    public function articoli(): HasMany
    {
        return $this->hasMany(ArticoloVetrina::class, 'vetrina_id');
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class, 'sede_id');
    }
    
    // ==========================================
    // SCOPES
    // ==========================================
    
    public function scopeAttive($query)
    {
        return $query->where('attiva', true);
    }
    
    public function scopeGioielleria($query)
    {
        return $query->where('tipologia', 'gioielleria');
    }
    
    public function scopeOrologeria($query)
    {
        return $query->where('tipologia', 'orologeria');
    }
    
    // ==========================================
    // BUSINESS LOGIC
    // ==========================================
    
    public function isAttiva(): bool
    {
        return $this->attiva === true;
    }
    
    public function isGioielleria(): bool
    {
        return $this->tipologia === 'gioielleria';
    }
    
    public function isOrologeria(): bool
    {
        return $this->tipologia === 'orologeria';
    }
    
    public function getTipologiaLabel(): string
    {
        return match($this->tipologia) {
            'gioielleria' => 'Gioielleria',
            'orologeria' => 'Orologeria',
            default => 'N/A'
        };
    }
    
    /**
     * Conta articoli in vetrina
     */
    public function getTotaleArticoli(): int
    {
        return $this->articoli()->count();
    }
}

