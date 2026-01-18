<?php

namespace App\Models;

use App\Models\Sede;
use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\FiltersBySede;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * CategoriaMerceologica - Entity
 * 
 * Rappresenta una categoria di prodotti (es: Sveglie, Orologi Acciaio, Gioielleria)
 * 
 * NOTA: Nel frontend viene ancora chiamato "Magazzino" per familiarità utente
 * ma concettualmente è una CATEGORIA MERCEOLOGICA, non un luogo fisico.
 * 
 * I luoghi fisici sono gestiti dalla tabella "sedi" (CAVOUR, JOLLY, MONASTERO...)
 */
class CategoriaMerceologica extends Model
{
    use SoftDeletes, FiltersBySede;

    protected static function booted(): void
    {
        if (!app()->runningInConsole()) {
            static::addGlobalScope('user_sede', function ($query) {
                $sedeId = auth()->user()?->sede_id;
                if ($sedeId) {
                    $table = $query->getModel()->getTable();
                    $query->where($table . '.sede_id', $sedeId);
                }
            });
        }
    }
    
    protected $table = 'categorie_merceologiche';
    
    protected $fillable = [
        'sede_id',
        'nome',
        'codice',
        'indirizzo',
        'citta',
        'provincia',
        'cap',
        'telefono',
        'email',
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
    
    /**
     * Sede fisica principale della categoria
     */
    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class, 'sede_id');
    }
    
    /**
     * Articoli di questa categoria merceologica
     */
    public function articoli(): HasMany
    {
        return $this->hasMany(Articolo::class, 'categoria_merceologica_id');
    }
    
    /**
     * Giacenze di questa categoria merceologica
     */
    public function giacenze(): HasMany
    {
        return $this->hasMany(Giacenza::class, 'categoria_merceologica_id');
    }
    
    // ==========================================
    // SCOPES
    // ==========================================
    
    public function scopeAttive($query)
    {
        return $query->where('attivo', true);
    }
    
    public function scopeBySede($query, int $sedeId)
    {
        return $query->where('sede_id', $sedeId);
    }
    
    // ==========================================
    // BUSINESS LOGIC
    // ==========================================
    
    public function isAttiva(): bool
    {
        return $this->attivo === true;
    }
    
    /**
     * Conta articoli in questa categoria
     */
    public function countArticoli(): int
    {
        return $this->articoli()->count();
    }
    
    /**
     * Conta articoli disponibili (con giacenza > 0)
     */
    public function countArticoliDisponibili(): int
    {
        return $this->articoli()
            ->whereHas('giacenza', function($q) {
                $q->where('quantita', '>', 0);
            })
            ->count();
    }
    
    /**
     * Valore totale articoli in questa categoria
     */
    public function valoreTotale(): float
    {
        return $this->articoli()
            ->with('giacenza')
            ->get()
            ->sum(function($articolo) {
                return $articolo->prezzo_acquisto * ($articolo->giacenza->quantita ?? 0);
            });
    }
    
    /**
     * Nome per display (backend: Categoria, frontend: Magazzino)
     */
    public function getNomeDisplayAttribute(): string
    {
        return $this->nome;
    }
    
    /**
     * Codice formattato per etichette
     */
    public function getCodiceFormattatoAttribute(): string
    {
        return strtoupper($this->codice);
    }
    
    /**
     * Nome completo con sede
     */
    public function getNomeCompletoAttribute(): string
    {
        $sede = $this->sede ? " ({$this->sede->nome})" : '';
        return $this->nome . $sede;
    }
}
