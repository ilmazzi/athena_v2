<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventarioScansione extends Model
{
    use HasFactory;
    
    protected $table = 'inventario_scansioni';

    protected $fillable = [
        'sessione_id',
        'articolo_id',
        'azione',
        'quantita_trovata',
        'quantita_sistema',
        'differenza',
        'note',
        'data_scansione'
    ];

    protected $casts = [
        'data_scansione' => 'datetime'
    ];

    /**
     * Relazione con la sessione inventario
     */
    public function sessione(): BelongsTo
    {
        return $this->belongsTo(InventarioSessione::class, 'sessione_id');
    }

    /**
     * Relazione con l'articolo
     */
    public function articolo(): BelongsTo
    {
        return $this->belongsTo(Articolo::class, 'articolo_id');
    }

    /**
     * Calcola automaticamente la differenza
     */
    protected static function boot()
    {
        parent::boot();
        
        static::saving(function ($scansione) {
            if ($scansione->quantita_trovata !== null && $scansione->quantita_sistema !== null) {
                $scansione->differenza = $scansione->quantita_trovata - $scansione->quantita_sistema;
            }
        });
    }

    /**
     * Verifica se l'articolo è stato trovato
     */
    public function isTrovato(): bool
    {
        return $this->azione === 'trovato';
    }

    /**
     * Verifica se l'articolo è stato eliminato
     */
    public function isEliminato(): bool
    {
        return $this->azione === 'eliminato';
    }

    /**
     * Verifica se c'è una differenza tra trovato e sistema
     */
    public function hasDifferenza(): bool
    {
        return $this->differenza !== 0;
    }

    /**
     * Ottieni il tipo di differenza
     */
    public function getTipoDifferenzaAttribute(): string
    {
        if ($this->differenza > 0) {
            return 'eccesso';
        } elseif ($this->differenza < 0) {
            return 'mancanza';
        }
        
        return 'corretto';
    }

    /**
     * Ottieni il valore assoluto della differenza
     */
    public function getDifferenzaAssolutaAttribute(): int
    {
        return abs($this->differenza);
    }

    /**
     * Scope per azione
     */
    public function scopePerAzione($query, string $azione)
    {
        return $query->where('azione', $azione);
    }

    /**
     * Scope per articoli trovati
     */
    public function scopeTrovati($query)
    {
        return $query->where('azione', 'trovato');
    }

    /**
     * Scope per articoli eliminati
     */
    public function scopeEliminati($query)
    {
        return $query->where('azione', 'eliminato');
    }

    /**
     * Scope per differenze
     */
    public function scopeConDifferenza($query)
    {
        return $query->where('differenza', '!=', 0);
    }

    /**
     * Scope per eccessi
     */
    public function scopeConEccesso($query)
    {
        return $query->where('differenza', '>', 0);
    }

    /**
     * Scope per mancanze
     */
    public function scopeConMancanza($query)
    {
        return $query->where('differenza', '<', 0);
    }

    /**
     * Scope per parziali (quantità trovata < quantità sistema)
     */
    public function scopeParziali($query)
    {
        return $query->whereNotNull('quantita_trovata')
            ->whereNotNull('quantita_sistema')
            ->where('quantita_trovata', '>', 0)
            ->whereColumn('quantita_trovata', '<', 'quantita_sistema');
    }

    /**
     * Scope per sessione
     */
    public function scopePerSessione($query, int $sessioneId)
    {
        return $query->where('sessione_id', $sessioneId);
    }

    /**
     * Scope per data scansione
     */
    public function scopePerData($query, $dataInizio, $dataFine = null)
    {
        $query->where('data_scansione', '>=', $dataInizio);
        
        if ($dataFine) {
            $query->where('data_scansione', '<=', $dataFine);
        }
        
        return $query;
    }
}