<?php

namespace App\Models;

use App\Exceptions\GiacenzaInsufficienteException;
use App\Models\Sede;
use App\Models\Ubicazione;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Giacenza - Entity del dominio Magazzino
 * 
 * Business Rules:
 * - Relazione 1:1 OBBLIGATORIA con Articolo
 * - Un articolo fisico può essere in UNA SOLA sede
 * - Quantità mai negativa
 * - Decremento solo tramite business logic (no update diretto)
 * - Quando quantita_residua=0 articolo è scaricato (NO data_scarico!)
 */
class Giacenza extends Model
{
    protected $table = 'giacenze';
    
    public $timestamps = true;
    
    protected $fillable = [
        'articolo_id',
        'categoria_merceologica_id',
        'magazzino_logico',
        'sede_id',          // Sede fisica giacenza
        'ubicazione_id',
        'quantita',
        'quantita_iniziale',
        'quantita_residua', // Disponibile (dopo scarichi)
        'quantita_deposito', // In conto deposito
        'quantita_minima',
        'quantita_riservata',
        'costo_unitario',   // Costo unitario acquisto
        'scaffale',         // ⚠️ Da rimuovere dopo migrazione completa
        'box',              // ⚠️ Da rimuovere dopo migrazione completa
        'posizione',        // ⚠️ Da rimuovere dopo migrazione completa
        'ultimo_movimento_at',
        'ultimo_inventario_at',
        'ultima_verifica_at',
        'note',
    ];
    
    protected $casts = [
        'quantita' => 'integer',
        'magazzino_logico' => 'integer',
        'quantita_iniziale' => 'integer',
        'quantita_residua' => 'integer',
        'quantita_deposito' => 'integer',
        'quantita_minima' => 'integer',
        'quantita_riservata' => 'integer',
        'costo_unitario' => 'decimal:2',
        'ultimo_movimento_at' => 'datetime',
        'ultimo_inventario_at' => 'datetime',
        'ultima_verifica_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
    
    // ==========================================
    // RELATIONSHIPS
    // ==========================================
    
    public function articolo(): BelongsTo
    {
        return $this->belongsTo(Articolo::class, 'articolo_id');
    }
    
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
     * Sede fisica dove si trova la giacenza
     */
    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class, 'sede_id');
    }
    
    /**
     * Ubicazione fisica specifica (scaffale/box)
     */
    public function ubicazione(): BelongsTo
    {
        return $this->belongsTo(Ubicazione::class, 'ubicazione_id');
    }
    
    // ==========================================
    // BUSINESS LOGIC
    // ==========================================
    
    /**
     * Incrementa giacenza (carico)
     */
    public function incrementa(int $quantita): void
    {
        if ($quantita <= 0) {
            throw new \InvalidArgumentException("Quantità deve essere positiva, ricevuto: {$quantita}");
        }
        
        $this->quantita += $quantita;
        $this->ultimo_movimento_at = now();
        $this->save();
    }
    
    /**
     * Decrementa giacenza (scarico/vendita)
     * 
     * @throws GiacenzaInsufficienteException
     */
    public function decrementa(int $quantita): void
    {
        if ($quantita <= 0) {
            throw new \InvalidArgumentException("Quantità deve essere positiva, ricevuto: {$quantita}");
        }
        
        $disponibile = max($this->quantita_residua ?? 0, $this->quantita ?? 0);
        if ($disponibile < $quantita) {
            throw GiacenzaInsufficienteException::forArticolo(
                $this->articolo_id,
                $quantita,
                $disponibile
            );
        }
        
        if (!is_null($this->quantita_residua)) {
            $this->quantita_residua = max(0, $this->quantita_residua - $quantita);
        }

        if (!is_null($this->quantita)) {
            $this->quantita = max(0, $this->quantita - $quantita);
        }
        $this->ultimo_movimento_at = now();
        $this->save();
        
        // Se quantità = 0, aggiorna stato articolo
        // ⚠️ NO data_scarico! Solo stato ENUM
        if ($this->quantita === 0 && $this->articolo) {
            $this->articolo->update(['stato' => 'venduto']);
        }
    }
    
    /**
     * Verifica se c'è giacenza disponibile
     */
    public function hasDisponibilita(int $quantita = 1): bool
    {
        $disponibile = max($this->quantita_residua ?? 0, $this->quantita ?? 0);
        return $disponibile >= $quantita;
    }
    
    /**
     * Verifica se giacenza è vuota (articolo scaricato)
     */
    public function isEmpty(): bool
    {
        return $this->quantita === 0;
    }
    
    /**
     * Verifica se giacenza è piena (per articoli non frazionabili)
     */
    public function isFull(): bool
    {
        return $this->quantita > 0;
    }
    
    // ==========================================
    // SCOPES
    // ==========================================
    
    public function scopeDisponibili($query)
    {
        return $query->where('quantita', '>', 0);
    }
    
    public function scopeVuote($query)
    {
        return $query->where('quantita', 0);
    }
    
    public function scopeInMagazzino($query, int $magazzinoId)
    {
        return $query->where('categoria_merceologica_id', $magazzinoId);
    }
    
    public function scopeScaffale($query, string $scaffale)
    {
        return $query->where('scaffale', $scaffale);
    }
}
