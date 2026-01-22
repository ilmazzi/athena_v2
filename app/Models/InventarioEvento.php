<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventarioEvento extends Model
{
    use HasFactory;

    protected $table = 'inventario_eventi';

    protected $fillable = [
        'sessione_id',
        'articolo_id',
        'sede_id',
        'user_id',
        'tipo',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function sessione(): BelongsTo
    {
        return $this->belongsTo(InventarioSessione::class, 'sessione_id');
    }

    public function articolo(): BelongsTo
    {
        return $this->belongsTo(Articolo::class, 'articolo_id');
    }

    public function utente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
