<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'sede_id',
        'stampante_default_id',
        'categorie_permesse',
        'sedi_permesse',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'sede_id' => 'integer',
            'categorie_permesse' => 'array',
            'sedi_permesse' => 'array',
        ];
    }

    /**
     * Relazione con la stampante predefinita
     */
    public function stampanteDefault()
    {
        return $this->belongsTo(Stampante::class, 'stampante_default_id');
    }

    /**
     * Sede di appartenenza dell'utente
     */
    public function sede()
    {
        return $this->belongsTo(Sede::class, 'sede_id');
    }

    /**
     * Verifica se l'utente è admin (senza restrizioni)
     */
    public function isAdmin(): bool
    {
        // Considera admin solo se non ha sede assegnata (superuser)
        return empty($this->sede_id) && empty($this->categorie_permesse) && empty($this->sedi_permesse);
    }

    /**
     * Verifica se l'utente può accedere a una categoria
     */
    public function canAccessCategory(int $categoriaId): bool
    {
        // Admin può accedere a tutto
        if ($this->isAdmin()) {
            return true;
        }
        
        return in_array($categoriaId, $this->categorie_permesse ?? []);
    }

    /**
     * Verifica se l'utente può accedere a una sede
     */
    public function canAccessSede(int $sedeId): bool
    {
        // Se sede singola, consenti solo la propria sede (o admin)
        return $this->isAdmin() || $this->sede_id === $sedeId;
    }

    /**
     * Verifica se l'utente può accedere a un articolo
     */
    public function canAccessArticolo(Articolo $articolo): bool
    {
        // Admin può accedere a tutto
        if ($this->isAdmin()) {
            return true;
        }
        
        return $this->canAccessCategory($articolo->categoria_merceologica_id) &&
               $this->canAccessSede($articolo->sede_id);
    }
}
