<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventarioSessione extends Model
{
    use HasFactory;
    
    protected $table = 'inventario_sessioni';

    protected $fillable = [
        'nome',
        'sede_id',
        'categorie_permesse',
        'data_inizio',
        'data_fine',
        'stato',
        'utente_id',
        'note',
        'articoli_totali',
        'articoli_trovati',
        'articoli_eliminati',
        'valore_eliminato'
    ];

    protected $casts = [
        'categorie_permesse' => 'array',
        'data_inizio' => 'datetime',
        'data_fine' => 'datetime'
    ];

    /**
     * Relazione con la sede
     */
    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class, 'sede_id');
    }

    /**
     * Relazione con l'utente che ha creato la sessione
     */
    public function utente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'utente_id');
    }

    /**
     * Relazione con le scansioni
     */
    public function scansioni(): HasMany
    {
        return $this->hasMany(InventarioScansione::class, 'sessione_id');
    }

    /**
     * Relazione con gli articoli storici eliminati
     */
    public function articoliEliminati(): HasMany
    {
        return $this->hasMany(ArticoloStorico::class, 'sessione_inventario_id');
    }

    /**
     * Ottieni gli articoli trovati durante l'inventario
     */
    public function getArticoliTrovati()
    {
        return $this->scansioni()
            ->where('azione', 'trovato')
            ->with('articolo')
            ->get();
    }

    /**
     * Ottieni gli articoli eliminati durante l'inventario
     */
    public function getArticoliEliminati()
    {
        return $this->scansioni()
            ->where('azione', 'eliminato')
            ->with('articolo')
            ->get();
    }

    /**
     * Calcola le statistiche della sessione
     */
    public function calcolaStatistiche()
    {
        $this->articoli_trovati = $this->scansioni()->where('azione', 'trovato')->count();
        $this->articoli_eliminati = $this->scansioni()->where('azione', 'eliminato')->count();
        
        // Calcola valore eliminato
        $this->valore_eliminato = $this->articoliEliminati()
            ->sum('dati_completi->prezzo_acquisto');
        
        $this->save();
    }

    /**
     * Chiudi la sessione
     */
    public function chiudi()
    {
        $this->stato = 'chiusa';
        $this->data_fine = now();
        $this->calcolaStatistiche();
        $this->save();
    }

    /**
     * Annulla la sessione
     */
    public function annulla()
    {
        $this->stato = 'annullata';
        $this->data_fine = now();
        $this->save();
    }

    /**
     * Riattiva la sessione
     */
    public function riattiva(): void
    {
        $this->stato = 'attiva';
        $this->data_fine = null;
        $this->save();
    }

    /**
     * Verifica se la sessione è attiva
     */
    public function isAttiva(): bool
    {
        return $this->stato === 'attiva';
    }

    /**
     * Verifica se la sessione è chiusa
     */
    public function isChiusa(): bool
    {
        return $this->stato === 'chiusa';
    }

    /**
     * Ottieni il progresso dell'inventario in percentuale
     */
    public function getProgressoAttribute(): float
    {
        if ($this->articoli_totali == 0) {
            return 0;
        }
        
        return round(($this->articoli_trovati + $this->articoli_eliminati) / $this->articoli_totali * 100, 2);
    }

    /**
     * Scope per sessioni attive
     */
    public function scopeAttive($query)
    {
        return $query->where('stato', 'attiva');
    }

    /**
     * Scope per sessioni chiuse
     */
    public function scopeChiuse($query)
    {
        return $query->where('stato', 'chiusa');
    }

    /**
     * Scope per sede
     */
    public function scopePerSede($query, int $sedeId)
    {
        return $query->where('sede_id', $sedeId);
    }

    /**
     * Scope per utente
     */
    public function scopePerUtente($query, int $utenteId)
    {
        return $query->where('utente_id', $utenteId);
    }
}