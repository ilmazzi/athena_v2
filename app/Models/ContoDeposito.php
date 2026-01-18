<?php

namespace App\Models;

use App\Models\Societa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\FiltersBySede;
use Carbon\Carbon;

/**
 * ContoDeposito - Gestione depositi articoli tra sedi
 * 
 * Rappresenta un deposito temporaneo di articoli/PF tra sedi
 * con durata massima di 1 anno e tracking completo dei movimenti
 */
class ContoDeposito extends Model
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
    
    protected $table = 'conti_deposito';
    
    protected $fillable = [
        'codice',
        'sede_mittente_id',
        'sede_destinataria_id',
        'data_invio',
        'data_scadenza',
        'stato',
        'ddt_invio_id',
        'ddt_reso_id',
        'ddt_rimando_id',
        'deposito_precedente_id',
        'valore_totale_invio',
        'valore_venduto',
        'valore_rientrato',
        'articoli_inviati',
        'articoli_venduti',
        'articoli_rientrati',
        'note',
        'configurazione',
        'creato_da',
        'chiuso_da',
    ];
    
    protected $casts = [
        'data_invio' => 'date',
        'data_scadenza' => 'date',
        'valore_totale_invio' => 'decimal:2',
        'valore_venduto' => 'decimal:2',
        'valore_rientrato' => 'decimal:2',
        'configurazione' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];
    
    // ==========================================
    // RELATIONSHIPS
    // ==========================================
    
    /**
     * Sede che invia gli articoli
     */
    public function sedeMittente(): BelongsTo
    {
        return $this->belongsTo(Sede::class, 'sede_mittente_id');
    }
    
    /**
     * Sede che riceve gli articoli
     */
    public function sedeDestinataria(): BelongsTo
    {
        return $this->belongsTo(Sede::class, 'sede_destinataria_id');
    }
    
    /**
     * DDT Deposito associati a questo conto deposito
     */
    public function ddtDepositi(): HasMany
    {
        return $this->hasMany(DdtDeposito::class, 'conto_deposito_id');
    }
    
    /**
     * DDT di invio (primo DDT di tipo invio)
     */
    public function ddtInvio(): BelongsTo
    {
        return $this->belongsTo(DdtDeposito::class, 'ddt_invio_id');
    }
    
    /**
     * DDT di reso (primo DDT di tipo reso)
     */
    public function ddtReso(): BelongsTo
    {
        return $this->belongsTo(DdtDeposito::class, 'ddt_reso_id');
    }
    
    /**
     * DDT per rimando dopo reso
     */
    public function ddtRimando(): BelongsTo
    {
        return $this->belongsTo(DdtDeposito::class, 'ddt_rimando_id');
    }
    
    /**
     * Tutti i DDT di invio
     */
    public function ddtInvii(): HasMany
    {
        return $this->ddtDepositi()->where('tipo', 'invio');
    }
    
    /**
     * Tutti i DDT di reso
     */
    public function ddtResi(): HasMany
    {
        return $this->ddtDepositi()->where('tipo', 'reso');
    }
    
    /**
     * Deposito precedente (per rinnovi)
     */
    public function depositoPrecedente(): BelongsTo
    {
        return $this->belongsTo(ContoDeposito::class, 'deposito_precedente_id');
    }
    
    /**
     * Depositi successivi (rinnovi)
     */
    public function depositiSuccessivi(): HasMany
    {
        return $this->hasMany(ContoDeposito::class, 'deposito_precedente_id');
    }
    
    /**
     * Utente che ha creato il deposito
     */
    public function creatoDa(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creato_da');
    }
    
    /**
     * Utente che ha chiuso il deposito
     */
    public function chiusoDa(): BelongsTo
    {
        return $this->belongsTo(User::class, 'chiuso_da');
    }
    
    /**
     * Tutti i movimenti del deposito
     */
    public function movimenti(): HasMany
    {
        return $this->hasMany(MovimentoDeposito::class, 'conto_deposito_id');
    }
    
    /**
     * Movimenti di invio
     */
    public function movimentiInvio(): HasMany
    {
        return $this->movimenti()->where('tipo_movimento', 'invio');
    }
    
    /**
     * Movimenti di vendita
     */
    public function movimentiVendita(): HasMany
    {
        return $this->movimenti()->where('tipo_movimento', 'vendita');
    }
    
    /**
     * Movimenti di reso
     */
    public function movimentiReso(): HasMany
    {
        return $this->movimenti()->where('tipo_movimento', 'reso');
    }
    
    /**
     * Proforme associate al deposito
     */
    public function proforme()
    {
        return $this->hasMany(ProformaDeposito::class, 'conto_deposito_id');
    }
    
    /**
     * Articoli attualmente nel deposito
     */
    public function articoli(): HasMany
    {
        return $this->hasMany(Articolo::class, 'conto_deposito_corrente_id');
    }
    
    /**
     * Prodotti finiti attualmente nel deposito
     */
    public function prodottiFiniti(): HasMany
    {
        return $this->hasMany(ProdottoFinito::class, 'conto_deposito_corrente_id');
    }
    
    // ==========================================
    // SCOPES
    // ==========================================
    
    public function scopeAttivi($query)
    {
        return $query->where('stato', 'attivo');
    }
    
    public function scopeScaduti($query)
    {
        return $query->where('stato', 'scaduto');
    }
    
    public function scopeChiusi($query)
    {
        return $query->where('stato', 'chiuso');
    }
    
    public function scopeInScadenza($query, $giorni = 30)
    {
        return $query->where('stato', 'attivo')
                    ->where('data_scadenza', '<=', now()->addDays($giorni));
    }
    
    public function scopePerSede($query, $sedeId)
    {
        return $query->where(function($q) use ($sedeId) {
            $q->where('sede_mittente_id', $sedeId)
              ->orWhere('sede_destinataria_id', $sedeId);
        });
    }
    
    // ==========================================
    // BUSINESS LOGIC
    // ==========================================
    
    /**
     * Ottieni società mittente (tramite sede)
     */
    public function getSocietaMittente(): ?Societa
    {
        return $this->sedeMittente->societa ?? null;
    }
    
    /**
     * Ottieni società destinataria (tramite sede)
     */
    public function getSocietaDestinataria(): ?Societa
    {
        return $this->sedeDestinataria->societa ?? null;
    }
    
    /**
     * Verifica se il deposito è inter-società (tra 2 società diverse)
     */
    public function isInterSocieta(): bool
    {
        $socMittente = $this->getSocietaMittente();
        $socDestinataria = $this->getSocietaDestinataria();
        
        if (!$socMittente || !$socDestinataria) {
            return false;
        }
        
        return $socMittente->id !== $socDestinataria->id;
    }
    
    /**
     * Genera codice automatico per il deposito
     */
    public static function generaCodice(): string
    {
        $anno = now()->year;
        $ultimoNumero = static::where('codice', 'like', "CD-{$anno}-%")
                             ->orderBy('codice', 'desc')
                             ->value('codice');
        
        if ($ultimoNumero) {
            $numero = intval(substr($ultimoNumero, -4)) + 1;
        } else {
            $numero = 1;
        }
        
        return sprintf('CD-%d-%04d', $anno, $numero);
    }
    
    /**
     * Verifica se il deposito è scaduto
     */
    public function isScaduto(): bool
    {
        return $this->data_scadenza < now()->toDateString();
    }
    
    /**
     * Verifica se il deposito è in scadenza
     */
    public function isInScadenza($giorni = 30): bool
    {
        return $this->data_scadenza <= now()->addDays($giorni)->toDateString();
    }
    
    /**
     * Verifica se il deposito è attivo
     */
    public function isAttivo(): bool
    {
        return $this->stato === 'attivo';
    }
    
    /**
     * Verifica se il deposito è chiuso
     */
    public function isChiuso(): bool
    {
        return $this->stato === 'chiuso';
    }
    
    /**
     * Calcola giorni rimanenti
     */
    public function getGiorniRimanenti(): int
    {
        return max(0, now()->diffInDays($this->data_scadenza, false));
    }
    
    /**
     * Calcola giorni trascorsi
     */
    public function getGiorniTrascorsi(): int
    {
        return $this->data_invio->diffInDays(now());
    }
    
    /**
     * Calcola valore rimanente nel deposito
     */
    public function getValoreRimanente(): float
    {
        return $this->valore_totale_invio - $this->valore_venduto - $this->valore_rientrato;
    }
    
    /**
     * Calcola articoli rimanenti nel deposito
     */
    public function getArticoliRimanenti(): int
    {
        return $this->articoli_inviati - $this->articoli_venduti - $this->articoli_rientrati;
    }
    
    /**
     * Calcola percentuale vendita
     */
    public function getPercentualeVendita(): float
    {
        if ($this->articoli_inviati == 0) return 0;
        return round(($this->articoli_venduti / $this->articoli_inviati) * 100, 2);
    }
    
    /**
     * Verifica se può essere rinnovato
     */
    public function puoEssereRinnovato(): bool
    {
        return $this->stato === 'chiuso' && $this->getArticoliRimanenti() > 0;
    }
    
    /**
     * Crea un nuovo deposito identico (rinnovo)
     */
    public function creaRinnovo(): ContoDeposito
    {
        $nuovoDeposito = static::create([
            'codice' => static::generaCodice(),
            'sede_mittente_id' => $this->sede_mittente_id,
            'sede_destinataria_id' => $this->sede_destinataria_id,
            'data_invio' => now()->toDateString(),
            'data_scadenza' => now()->addYear()->toDateString(),
            'stato' => 'attivo',
            'deposito_precedente_id' => $this->id,
            'note' => "Rinnovo del deposito {$this->codice}",
            'creato_da' => auth()->id(),
        ]);
        
        return $nuovoDeposito;
    }
    
    /**
     * Aggiorna contatori e valori
     */
    public function aggiornaStatistiche(): void
    {
        $movimentiInvio = $this->movimentiInvio;
        $movimentiVendita = $this->movimentiVendita;
        $movimentiReso = $this->movimentiReso;
        
        $this->update([
            'valore_totale_invio' => $movimentiInvio->sum('costo_totale'),
            'valore_venduto' => $movimentiVendita->sum('costo_totale'),
            'valore_rientrato' => $movimentiReso->sum('costo_totale'),
            'articoli_inviati' => $movimentiInvio->sum('quantita'),
            'articoli_venduti' => $movimentiVendita->sum('quantita'),
            'articoli_rientrati' => $movimentiReso->sum('quantita'),
        ]);
        
        // Aggiorna stato se necessario
        if ($this->getArticoliRimanenti() == 0) {
            $this->update(['stato' => 'chiuso']);
        } elseif ($this->isScaduto() && $this->stato === 'attivo') {
            $this->update(['stato' => 'scaduto']);
        }
    }
    
    // ==========================================
    // MUTATORS & ACCESSORS
    // ==========================================
    
    /**
     * Imposta automaticamente la data scadenza
     */
    public function setDataInvioAttribute($value)
    {
        $this->attributes['data_invio'] = $value;
        if (!isset($this->attributes['data_scadenza'])) {
            $this->attributes['data_scadenza'] = Carbon::parse($value)->addYear()->toDateString();
        }
    }
    
    /**
     * Formatta il valore rimanente
     */
    public function getValoreRimanenteFormattedAttribute(): string
    {
        return '€' . number_format($this->getValoreRimanente(), 2, ',', '.');
    }
    
    /**
     * Stato con colore per UI
     */
    public function getStatoColorAttribute(): string
    {
        return match($this->stato) {
            'attivo' => 'success',
            'scaduto' => 'warning',
            'chiuso' => 'secondary',
            'parziale' => 'info',
            default => 'light'
        };
    }
    
    /**
     * Stato leggibile
     */
    public function getStatoLabelAttribute(): string
    {
        return match($this->stato) {
            'attivo' => 'Attivo',
            'scaduto' => 'Scaduto',
            'chiuso' => 'Chiuso',
            'parziale' => 'Parziale',
            default => 'Sconosciuto'
        };
    }
}