<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class ProformaDeposito extends Model
{
    use SoftDeletes;

    public const STATO_DA_FATTURARE = 'da_fatturare';
    public const STATO_FATTURATA = 'fatturata';

    protected $table = 'proforme_deposito';

    protected $fillable = [
        'numero',
        'anno',
        'data_documento',
        'cliente_nome',
        'cliente_cognome',
        'cliente_telefono',
        'cliente_email',
        'totale',
        'imponibile',
        'iva',
        'sede_id',
        'conto_deposito_id',
        'ddt_invio_id',
        'quantita_totale',
        'numero_articoli',
        'note',
        'stato',
        'fattura_pdf_path',
        'fatturata_da',
        'fatturata_il',
        'fattura_note',
        'fattura_numero',
        'fattura_data',
    ];

    protected $casts = [
        'data_documento' => 'date',
        'anno' => 'integer',
        'totale' => 'decimal:2',
        'imponibile' => 'decimal:2',
        'iva' => 'decimal:2',
        'quantita_totale' => 'integer',
        'numero_articoli' => 'integer',
        'fatturata_il' => 'datetime',
        'fattura_data' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // ==========================================
    // RELATIONSHIPS
    // ==========================================

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class, 'sede_id');
    }

    public function contoDeposito(): BelongsTo
    {
        return $this->belongsTo(ContoDeposito::class, 'conto_deposito_id');
    }

    public function ddtInvio(): BelongsTo
    {
        return $this->belongsTo(DdtDeposito::class, 'ddt_invio_id');
    }

    public function movimenti(): HasMany
    {
        return $this->hasMany(MovimentoDeposito::class, 'proforma_id');
    }

    public function fatturataDa(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fatturata_da');
    }

    // ==========================================
    // SCOPES
    // ==========================================

    public function scopeAnno($query, int $anno)
    {
        return $query->where('anno', $anno);
    }

    public function scopeBySede($query, int $sedeId)
    {
        return $query->where('sede_id', $sedeId);
    }

    public function scopeDaFatturare($query)
    {
        return $query->where('stato', self::STATO_DA_FATTURARE);
    }

    public function scopeFatturate($query)
    {
        return $query->where('stato', self::STATO_FATTURATA);
    }

    // ==========================================
    // ACCESSORS & HELPERS
    // ==========================================

    public function getClienteNomeCompletoAttribute(): string
    {
        return trim("{$this->cliente_nome} {$this->cliente_cognome}");
    }

    public function getIsFatturataAttribute(): bool
    {
        return $this->stato === self::STATO_FATTURATA;
    }

    public function getFatturaPdfUrlAttribute(): ?string
    {
        if (!$this->fattura_pdf_path) {
            return null;
        }

        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk('public');

        if ($disk->exists($this->fattura_pdf_path)) {
            return $disk->url($this->fattura_pdf_path);
        }

        return null;
    }
}

