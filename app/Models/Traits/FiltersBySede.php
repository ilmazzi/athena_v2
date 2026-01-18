<?php

namespace App\Models\Traits;

use Illuminate\Database\Eloquent\Builder;

trait FiltersBySede
{
    /**
     * Scope per filtrare per sede dell'utente corrente.
     */
    public function scopeForCurrentUserSede(Builder $query, string $column = 'sede_id'): Builder
    {
        $sedeId = auth()->user()?->sede_id;
        if ($sedeId) {
            $query->where($column, $sedeId);
        } else {
            // Se utente senza sede assegnata, restituisci query vuota per sicurezza
            $query->whereRaw('1 = 0');
        }
        return $query;
    }
}



