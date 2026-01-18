<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FornitorePrezzo extends Model
{
    protected $table = 'fornitori_prezzi';

    protected $fillable = [
        'fornitore_id',
        'match_type',
        'match_value',
        'prezzo',
        'note',
    ];

    protected $casts = [
        'prezzo' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function fornitore(): BelongsTo
    {
        return $this->belongsTo(Fornitore::class, 'fornitore_id');
    }
}
