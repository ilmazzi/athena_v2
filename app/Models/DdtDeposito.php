<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\Traits\FiltersBySede;

/**
 * DdtDeposito - DDT specifico per Conti Deposito
 * 
 * Separato dal DDT acquisti per seguire i principi DDD
 * Gestisce esclusivamente trasferimenti tra sedi per conti deposito
 */
class DdtDeposito extends Model
{
    use SoftDeletes, FiltersBySede;

    protected static function booted(): void
    {
        if (!app()->runningInConsole()) {
            static::addGlobalScope('user_sede', function ($query) {
                $sedeId = auth()->user()?->sede_id;
                if ($sedeId) {
                    $query->where(function($q) use ($sedeId) {
                        $q->where('sede_mittente_id', $sedeId)
                          ->orWhere('sede_destinataria_id', $sedeId);
                    });
                }
            });
        }
    }
    
    protected $table = 'ddt_depositi';
    
    protected $fillable = [
        'numero',
        'data_documento',
        'anno',
        'conto_deposito_id',
        'tipo',
        'sede_mittente_id',
        'sede_destinataria_id',
        'stato',
        'data_stampa',
        'data_spedizione',
        'data_ricezione',
        'data_conferma',
        'causale',
        'numero_colli',
        'corriere',
        'numero_tracking',
        'valore_dichiarato',
        'articoli_totali',
        'creato_da',
        'confermato_da',
        'note',
        'configurazione',
    ];
    
    protected $casts = [
        'data_documento' => 'date',
        'data_stampa' => 'datetime',
        'data_spedizione' => 'datetime',
        'data_ricezione' => 'datetime',
        'data_conferma' => 'datetime',
        'valore_dichiarato' => 'decimal:2',
        'configurazione' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];
    
    // ==========================================
    // RELATIONSHIPS
    // ==========================================
    
    /**
     * Conto deposito di riferimento
     */
    public function contoDeposito(): BelongsTo
    {
        return $this->belongsTo(ContoDeposito::class, 'conto_deposito_id');
    }
    
    /**
     * Sede mittente
     */
    public function sedeMittente(): BelongsTo
    {
        return $this->belongsTo(Sede::class, 'sede_mittente_id');
    }
    
    /**
     * Sede destinataria
     */
    public function sedeDestinataria(): BelongsTo
    {
        return $this->belongsTo(Sede::class, 'sede_destinataria_id');
    }
    
    /**
     * Utente che ha creato il DDT
     */
    public function creatoDa(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creato_da');
    }
    
    /**
     * Utente che ha confermato la ricezione
     */
    public function confermatoDa(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confermato_da');
    }
    
    /**
     * Dettagli del DDT (righe)
     */
    public function dettagli(): HasMany
    {
        return $this->hasMany(DdtDepositoDettaglio::class, 'ddt_deposito_id');
    }
    
    // ==========================================
    // SCOPES
    // ==========================================
    
    public function scopePerTipo($query, string $tipo)
    {
        return $query->where('tipo', $tipo);
    }
    
    public function scopePerStato($query, string $stato)
    {
        return $query->where('stato', $stato);
    }
    
    public function scopeInTransito($query)
    {
        return $query->whereIn('stato', ['stampato', 'in_transito']);
    }
    
    public function scopePerSede($query, int $sedeId)
    {
        return $query->where(function($q) use ($sedeId) {
            $q->where('sede_mittente_id', $sedeId)
              ->orWhere('sede_destinataria_id', $sedeId);
        });
    }
    
    public function scopePerAnno($query, int $anno)
    {
        return $query->where('anno', $anno);
    }
    
    // ==========================================
    // HELPERS
    // ==========================================
    /**
     * Genera un numero progressivo per sede mittente (separato per sede e anno)
     * Restituisce un intero incrementale (1,2,3,...) per l'anno corrente.
     */
    public static function generaNumeroPerSede(int $sedeMittenteId): int
    {
        $anno = now()->year;
        $max = self::where('sede_mittente_id', $sedeMittenteId)
            ->where('anno', $anno)
            ->select(DB::raw('MAX(CAST(numero AS UNSIGNED)) as max_num'))
            ->value('max_num');
        return ((int) $max) + 1;
    }

    /**
     * Genera numero formattato e globalmente univoco per sede e anno: CAV-2025-0001
     */
    public static function generaNumeroPerSedeFormatted(int $sedeMittenteId): string
    {
        $anno = now()->year;
        $sede = Sede::find($sedeMittenteId);
        $prefix = $sede?->codice ?? 'SED';
        $seq = self::where('sede_mittente_id', $sedeMittenteId)
            ->where('anno', $anno)
            ->select(DB::raw('MAX(CAST(SUBSTRING_INDEX(numero, "-", -1) AS UNSIGNED)) as max_seq'))
            ->value('max_seq');
        $next = ((int)$seq) + 1;
        return sprintf('%s-%d-%04d', $prefix, $anno, $next);
    }
    
    // ==========================================
    // BUSINESS METHODS
    // ==========================================
    
    /**
     * Genera numero progressivo DDT deposito
     */
    public static function generaNumeroDdt(): string
    {
        $anno = now()->year;
        $ultimoNumero = static::where('anno', $anno)
            ->where('numero', 'like', "DEP-{$anno}-%")
            ->orderBy('numero', 'desc')
            ->value('numero');

        if ($ultimoNumero) {
            $numero = intval(substr($ultimoNumero, -4)) + 1;
        } else {
            $numero = 1;
        }

        return sprintf('DEP-%d-%04d', $anno, $numero);
    }
    
    /**
     * Marca DDT come stampato
     */
    public function marcaStampato(): bool
    {
        if ($this->stato !== 'creato') {
            return false;
        }
        
        return $this->update([
            'stato' => 'stampato',
            'data_stampa' => now()
        ]);
    }
    
    /**
     * Marca DDT come spedito
     */
    public function marcaSpedito(?string $numerTracking = null, ?string $corriere = null): bool
    {
        if (!in_array($this->stato, ['stampato', 'creato'])) {
            return false;
        }
        
        $data = [
            'stato' => 'in_transito',
            'data_spedizione' => now()
        ];
        
        if ($numerTracking) {
            $data['numero_tracking'] = $numerTracking;
        }
        
        if ($corriere) {
            $data['corriere'] = $corriere;
        }
        
        return $this->update($data);
    }
    
    /**
     * Marca DDT come ricevuto
     */
    public function marcaRicevuto(): bool
    {
        if ($this->stato !== 'in_transito') {
            return false;
        }
        
        return $this->update([
            'stato' => 'ricevuto',
            'data_ricezione' => now()
        ]);
    }
    
    /**
     * Conferma ricezione DDT
     */
    public function confermaRicezione(?int $userId = null): bool
    {
        if ($this->stato !== 'ricevuto') {
            return false;
        }
        
        return $this->update([
            'stato' => 'confermato',
            'data_conferma' => now(),
            'confermato_da' => $userId ?? auth()->id()
        ]);
    }
    
    /**
     * Chiude definitivamente il DDT
     */
    public function chiudi(): bool
    {
        if (!in_array($this->stato, ['confermato', 'ricevuto'])) {
            return false;
        }
        
        return $this->update(['stato' => 'chiuso']);
    }
    
    // ==========================================
    // ACCESSORS & MUTATORS
    // ==========================================
    
    /**
     * Stato con colore per UI
     */
    public function getStatoColorAttribute(): string
    {
        return match($this->stato) {
            'creato' => 'secondary',
            'stampato' => 'info',
            'in_transito' => 'warning',
            'ricevuto' => 'primary',
            'confermato' => 'success',
            'chiuso' => 'dark',
            default => 'secondary'
        };
    }
    
    /**
     * Stato con label per UI
     */
    public function getStatoLabelAttribute(): string
    {
        return match($this->stato) {
            'creato' => 'Creato',
            'stampato' => 'Stampato',
            'in_transito' => 'In Transito',
            'ricevuto' => 'Ricevuto',
            'confermato' => 'Confermato',
            'chiuso' => 'Chiuso',
            default => ucfirst($this->stato)
        };
    }
    
    /**
     * Tipo con label per UI
     */
    public function getTipoLabelAttribute(): string
    {
        return match($this->tipo) {
            'invio' => 'Invio Deposito',
            'reso' => 'Reso Deposito',
            'rimando' => 'Rimando',
            default => ucfirst($this->tipo)
        };
    }
    
    /**
     * Verifica se è modificabile
     */
    public function getIsModificabileAttribute(): bool
    {
        return in_array($this->stato, ['creato', 'stampato']);
    }
    
    /**
     * Verifica se è annullabile
     */
    public function getIsAnnullabileAttribute(): bool
    {
        return in_array($this->stato, ['creato', 'stampato', 'in_transito']);
    }
    
    /**
     * Giorni in transito
     */
    public function getGiorniInTransitoAttribute(): ?int
    {
        if (!$this->data_spedizione) {
            return null;
        }
        
        $dataFine = $this->data_ricezione ?? now();
        return $this->data_spedizione->diffInDays($dataFine);
    }
}
