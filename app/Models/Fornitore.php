<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Fornitore - Entity del dominio Vendita
 * 
 * Rappresenta un fornitore da cui si acquistano articoli
 */
class Fornitore extends Model
{
    use SoftDeletes;
    
    protected $table = 'fornitori';
    
    protected $fillable = [
        'codice',
        'ragione_sociale',
        'partita_iva',
        'codice_fiscale',
        'indirizzo',
        'citta',
        'provincia',
        'cap',
        'nazione',
        'telefono',
        'email',
        'pec',
        'note',
        'attivo',
    ];
    
    protected $casts = [
        'attivo' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];
    
    // ==========================================
    // RELATIONSHIPS
    // ==========================================
    
    public function prezzi(): HasMany
    {
        return $this->hasMany(FornitorePrezzo::class, 'fornitore_id');
    }
    
    public function ddt(): HasMany
    {
        return $this->hasMany(Ddt::class, 'fornitore_id');
    }
    
    public function fatture(): HasMany
    {
        return $this->hasMany(Fattura::class, 'fornitore_id');
    }
    
    // ==========================================
    // SCOPES
    // ==========================================
    
    public function scopeAttivi($query)
    {
        return $query->where('attivo', true);
    }
    
    // ==========================================
    // BUSINESS LOGIC
    // ==========================================
    
    public function isAttivo(): bool
    {
        return $this->attivo === true;
    }
    
    /**
     * Ottiene descrizione completa fornitore
     */
    public function getDescrizioneCompleta(): string
    {
        $parts = [$this->ragione_sociale];
        
        if ($this->citta) {
            $parts[] = $this->citta;
        }
        
        if ($this->partita_iva) {
            $parts[] = "P.IVA: {$this->partita_iva}";
        }
        
        return implode(' - ', $parts);
    }
}

