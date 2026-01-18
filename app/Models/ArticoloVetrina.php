<?php

namespace App\Models;

use App\Models\Articolo;
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
    
    // ==========================================
    // BUSINESS LOGIC
    // ==========================================
    
    public function isInVetrina(): bool
    {
        return $this->data_rimozione === null;
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

