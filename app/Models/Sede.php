<?php

namespace App\Models;

use App\Models\Articolo;
use App\Models\CategoriaMerceologica;
use App\Models\Societa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Sede - Entity
 * 
 * Rappresenta una sede fisica dell'azienda
 * (es: CAVOUR, JOLLY, MONASTERO, MAZZINI, ROMA)
 * 
 * Una sede può contenere più magazzini (categorie merceologiche)
 */
class Sede extends Model
{
    use SoftDeletes;
    
    protected $table = 'sedi';
    
    protected $fillable = [
        'codice',
        'nome',
        'indirizzo',
        'citta',
        'provincia',
        'cap',
        'telefono',
        'email',
        'tipo',
        'societa_id',
        'attivo',
        'note',
        'orari',
        'sede_legale',
        'sede_legale_indirizzo',
        'sede_legale_citta',
        'sede_legale_provincia',
        'sede_legale_cap',
        'partita_iva',
        'codice_fiscale',
    ];
    
    protected $casts = [
        'attivo' => 'boolean',
        'orari' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];
    
    // ==========================================
    // RELATIONSHIPS
    // ==========================================
    
    /**
     * Società di appartenenza
     */
    public function societa(): BelongsTo
    {
        return $this->belongsTo(Societa::class, 'societa_id');
    }
    
    /**
     * Categorie merceologiche presenti in questa sede
     */
    public function categorieMerceologiche(): HasMany
    {
        return $this->hasMany(CategoriaMerceologica::class, 'sede_id');
    }
    
    /**
     * Alias per compatibilità frontend (chiamato ancora "magazzini")
     */
    public function magazzini(): HasMany
    {
        return $this->categorieMerceologiche();
    }
    
    /**
     * Articoli attualmente in questa sede
     */
    public function articoli(): HasMany
    {
        return $this->hasMany(Articolo::class, 'sede_id');
    }
    
    /**
     * Ubicazioni fisiche in questa sede
     */
    public function ubicazioni(): HasMany
    {
        return $this->hasMany(Ubicazione::class, 'sede_id');
    }
    
    // ==========================================
    // SCOPES
    // ==========================================
    
    public function scopeAttive($query)
    {
        return $query->where('attivo', true);
    }
    
    public function scopeByCitta($query, string $citta)
    {
        return $query->where('citta', $citta);
    }
    
    // ==========================================
    // BUSINESS LOGIC
    // ==========================================
    
    public function isAttiva(): bool
    {
        return $this->attivo === true;
    }
    
    /**
     * Conta articoli presenti in questa sede
     */
    public function countArticoliPresenti(): int
    {
        return $this->articoli()
            ->whereHas('giacenza', function($q) {
                $q->where('quantita', '>', 0);
            })
            ->count();
    }
    
    /**
     * Valore totale articoli in questa sede
     */
    public function valoreTotaleArticoli(): float
    {
        return $this->articoli()
            ->with('giacenza')
            ->get()
            ->sum(function($articolo) {
                return $articolo->prezzo_acquisto * ($articolo->giacenza->quantita ?? 0);
            });
    }
    
    /**
     * Nome completo con città
     */
    public function getNomeCompletoAttribute(): string
    {
        return $this->nome . ' (' . $this->citta . ')';
    }
    
    /**
     * Codice formattato per etichette
     */
    public function getCodiceFormattatoAttribute(): string
    {
        return strtoupper($this->codice);
    }
}

