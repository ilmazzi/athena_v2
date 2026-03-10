<?php

namespace App\Models;

use App\Models\Articolo;
use App\Models\CategoriaMerceologica;
use App\Models\Sede;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ArticoloVetrina - Pivot con prezzo_vetrina
 * 
 * ⚠️ IMPORTANTE: prezzo_vetrina è QUI (pivot), NON in articoli!
 * Questo è conforme alle business rules del cliente
 */
class ArticoloVetrina extends Model
{
    protected $table = 'articoli_vetrine';
    
    public $timestamps = true;
    
    protected $fillable = [
        'vetrina_id',
        'articolo_id',
        'tipo_articolo',
        'descrizione_esterno',
        'categoria_merceologica_id',
        'sede_id',
        'foto_principale_esterno',
        'materiale_esterno',
        'titolo_esterno',
        'caratura_esterno',
        'colore_esterno',
        'peso_lordo_esterno',
        'peso_netto_esterno',
        'prezzo_acquisto_esterno',
        'prezzo_fornitore_esterno',
        'note_esterno',
        'prezzo_vetrina',  // ⚠️ UNICO posto dove salviamo prezzo vendita!
        'testo_vetrina',
        'posizione',
        'ripiano',
        'data_inserimento',
        'data_rimozione',
        'giorni_esposizione',
        'note',
    ];
    
    protected $casts = [
        'prezzo_vetrina' => 'string',
        'peso_lordo_esterno' => 'decimal:2',
        'peso_netto_esterno' => 'decimal:2',
        'prezzo_acquisto_esterno' => 'decimal:2',
        'prezzo_fornitore_esterno' => 'decimal:2',
        'data_inserimento' => 'date',
        'data_rimozione' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
    
    // ==========================================
    // RELATIONSHIPS
    // ==========================================
    
    public function vetrina(): BelongsTo
    {
        return $this->belongsTo(Vetrina::class, 'vetrina_id');
    }
    
    public function articolo(): BelongsTo
    {
        return $this->belongsTo(Articolo::class, 'articolo_id');
    }

    public function categoriaMerceologica(): BelongsTo
    {
        return $this->belongsTo(CategoriaMerceologica::class, 'categoria_merceologica_id');
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class, 'sede_id');
    }
    
    // ==========================================
    // BUSINESS LOGIC
    // ==========================================
    
    public function isInVetrina(): bool
    {
        return $this->data_rimozione === null;
    }

    public function getIsEsternoAttribute(): bool
    {
        return $this->tipo_articolo === 'esterno';
    }

    public function getCodiceDisplayAttribute(): string
    {
        if ($this->is_esterno) {
            return 'NC';
        }
        return $this->articolo?->codice ?? '';
    }

    public function getDescrizioneDisplayAttribute(): string
    {
        if ($this->is_esterno) {
            return $this->descrizione_esterno ?? '';
        }
        return $this->articolo?->descrizione ?? '';
    }

    public function getCategoriaDisplayAttribute(): string
    {
        if ($this->is_esterno) {
            return $this->categoriaMerceologica?->nome ?? 'N/A';
        }
        return $this->articolo?->categoriaMerceologica?->nome ?? 'N/A';
    }

    public function getSedeDisplayAttribute(): string
    {
        if ($this->is_esterno) {
            return $this->sede?->nome ?? 'N/A';
        }
        return $this->articolo?->sede?->nome ?? 'N/A';
    }

    public function getFotoDisplayAttribute(): ?string
    {
        if ($this->is_esterno) {
            return $this->foto_principale_esterno;
        }
        return $this->articolo?->foto_principale;
    }
    
    /**
     * Formatta prezzo vetrina per display
     */
    public function getPrezzoFormatted(): string
    {
        if (is_numeric($this->prezzo_vetrina)) {
            return '€' . number_format((float) $this->prezzo_vetrina, 2, ',', '.');
        }
        return (string) $this->prezzo_vetrina;
    }
}

