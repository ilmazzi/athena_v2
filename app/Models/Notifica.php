<?php

namespace App\Models;

use App\Models\ContoDeposito;
use App\Models\MovimentoDeposito;
use App\Models\Societa;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Notifica - Modello per notifiche sistema conti deposito
 */
class Notifica extends Model
{
    protected $table = 'notifiche';
    
    protected $fillable = [
        'tipo',
        'societa_id',
        'user_id',
        'conto_deposito_id',
        'movimento_deposito_id',
        'titolo',
        'messaggio',
        'dati_aggiuntivi',
        'letta',
        'letta_il',
        'email_inviata',
        'email_inviata_il',
        'email_errore',
    ];
    
    protected $casts = [
        'dati_aggiuntivi' => 'array',
        'letta' => 'boolean',
        'letta_il' => 'datetime',
        'email_inviata' => 'boolean',
        'email_inviata_il' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
    
    // ==========================================
    // RELATIONSHIPS
    // ==========================================
    
    public function societa(): BelongsTo
    {
        return $this->belongsTo(Societa::class, 'societa_id');
    }
    
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    
    public function contoDeposito(): BelongsTo
    {
        return $this->belongsTo(ContoDeposito::class, 'conto_deposito_id');
    }
    
    public function movimentoDeposito(): BelongsTo
    {
        return $this->belongsTo(MovimentoDeposito::class, 'movimento_deposito_id');
    }
    
    // ==========================================
    // SCOPES
    // ==========================================
    
    public function scopeNonLette($query)
    {
        return $query->where('letta', false);
    }
    
    public function scopePerSocieta($query, $societaId)
    {
        return $query->where('societa_id', $societaId);
    }
    
    public function scopePerUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }
    
    public function scopePerTipo($query, $tipo)
    {
        return $query->where('tipo', $tipo);
    }
    
    public function scopeRecenti($query, $limit = null)
    {
        $query->orderBy('created_at', 'desc');
        if ($limit) {
            $query->limit($limit);
        }
        return $query;
    }
    
    // ==========================================
    // BUSINESS LOGIC
    // ==========================================
    
    /**
     * Segna notifica come letta
     */
    public function marcaComeLetta(): void
    {
        if (!$this->letta) {
            $this->update([
                'letta' => true,
                'letta_il' => now(),
            ]);
        }
    }
    
    /**
     * Segna email come inviata
     */
    public function marcaEmailInviata(): void
    {
        $this->update([
            'email_inviata' => true,
            'email_inviata_il' => now(),
        ]);
    }
    
    /**
     * Segna errore invio email
     */
    public function marcaErroreEmail(string $errore): void
    {
        $this->update([
            'email_errore' => $errore,
        ]);
    }
    
    /**
     * Ottieni icona in base al tipo
     */
    public function getIcona(): string
    {
        return match($this->tipo) {
            'nuovo_deposito' => 'solar:box-bold-duotone',
            'reso' => 'solar:import-bold-duotone',
            'vendita' => 'solar:cart-check-bold-duotone',
            'scadenza' => 'solar:clock-circle-bold-duotone',
            'deposito_scaduto' => 'solar:danger-triangle-bold-duotone',
            default => 'solar:bell-bold-duotone',
        };
    }
    
    /**
     * Ottieni colore badge in base al tipo
     */
    public function getColoreBadge(): string
    {
        return match($this->tipo) {
            'nuovo_deposito' => 'info',
            'reso' => 'warning',
            'vendita' => 'success',
            'scadenza' => 'info',
            'deposito_scaduto' => 'danger',
            default => 'primary',
        };
    }
}
