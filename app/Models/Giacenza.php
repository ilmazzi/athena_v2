<?php

namespace App\Models;

use App\Exceptions\GiacenzaInsufficienteException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Giacenza - Entity del dominio Magazzino
 *
 * Business rules:
 * - Relazione 1:1 obbligatoria con Articolo
 * - Un articolo fisico puo essere in una sola sede alla volta
 * - `quantita` = quantita caricata storica
 * - `quantita_residua` = disponibilita operativa corrente
 * - I movimenti ordinari non devono modificare `quantita`
 */
class Giacenza extends Model
{
    protected $table = 'giacenze';

    public $timestamps = true;

    protected $fillable = [
        'articolo_id',
        'categoria_merceologica_id',
        'magazzino_logico',
        'sede_id',
        'ubicazione_id',
        'quantita',
        'quantita_iniziale',
        'quantita_residua',
        'quantita_deposito',
        'quantita_minima',
        'quantita_riservata',
        'costo_unitario',
        'scaffale',
        'box',
        'posizione',
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

    public function articolo(): BelongsTo
    {
        return $this->belongsTo(Articolo::class, 'articolo_id');
    }

    public function categoriaMerceologica(): BelongsTo
    {
        return $this->belongsTo(CategoriaMerceologica::class, 'categoria_merceologica_id');
    }

    /**
     * Alias per compatibilita frontend.
     */
    public function magazzino(): BelongsTo
    {
        return $this->categoriaMerceologica();
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class, 'sede_id');
    }

    public function ubicazione(): BelongsTo
    {
        return $this->belongsTo(Ubicazione::class, 'ubicazione_id');
    }

    /**
     * Incrementa la sola disponibilita operativa.
     */
    public function incrementa(int $quantita): void
    {
        if ($quantita <= 0) {
            throw new \InvalidArgumentException("Quantita deve essere positiva, ricevuto: {$quantita}");
        }

        $disponibileAttuale = $this->quantita_residua;
        if (is_null($disponibileAttuale)) {
            $disponibileAttuale = $this->quantita ?? 0;
        }

        $this->quantita_residua = max(0, (int) $disponibileAttuale + $quantita);
        $this->ultimo_movimento_at = now();
        $this->save();
    }

    /**
     * Decrementa la sola disponibilita operativa.
     *
     * @throws GiacenzaInsufficienteException
     */
    public function decrementa(int $quantita): void
    {
        if ($quantita <= 0) {
            throw new \InvalidArgumentException("Quantita deve essere positiva, ricevuto: {$quantita}");
        }

        $disponibile = $this->quantita_residua;
        if (is_null($disponibile)) {
            $disponibile = $this->quantita ?? 0;
        }
        if ($disponibile < $quantita) {
            throw GiacenzaInsufficienteException::forArticolo(
                $this->articolo_id,
                $quantita,
                $disponibile
            );
        }

        $baseResidua = $this->quantita_residua;
        if (is_null($baseResidua)) {
            $baseResidua = $this->quantita ?? 0;
        }

        $this->quantita_residua = max(0, (int) $baseResidua - $quantita);
        $this->ultimo_movimento_at = now();
        $this->save();

        if (($this->quantita_residua ?? 0) === 0 && $this->articolo) {
            $this->articolo->update(['stato' => 'venduto']);
        }
    }

    public function hasDisponibilita(int $quantita = 1): bool
    {
        $disponibile = $this->quantita_residua;
        if (is_null($disponibile)) {
            $disponibile = $this->quantita ?? 0;
        }

        return $disponibile >= $quantita;
    }

    public function isEmpty(): bool
    {
        return ($this->quantita_residua ?? $this->quantita ?? 0) === 0;
    }

    public function isFull(): bool
    {
        return ($this->quantita_residua ?? $this->quantita ?? 0) > 0;
    }

    public function scopeDisponibili($query)
    {
        return $query->where(function ($subQuery) {
            $subQuery->where('quantita_residua', '>', 0)
                ->orWhere(function ($fallback) {
                    $fallback->whereNull('quantita_residua')
                        ->where('quantita', '>', 0);
                });
        });
    }

    public function scopeVuote($query)
    {
        return $query->where(function ($subQuery) {
            $subQuery->where('quantita_residua', '<=', 0)
                ->orWhere(function ($fallback) {
                    $fallback->whereNull('quantita_residua')
                        ->where('quantita', '<=', 0);
                });
        });
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
