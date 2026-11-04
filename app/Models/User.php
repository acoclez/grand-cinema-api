<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\RoleUtilisateur;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * @property RoleUtilisateur $role
 */
#[Fillable(['prenom', 'nom', 'email', 'password', 'role', 'pays_residence', 'consentement_emails'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'anonymise_at' => 'datetime',
            'password' => 'hashed',
            'role' => RoleUtilisateur::class,
            'consentement_emails' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Reservation, $this>
     */
    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function estAdministrateur(): bool
    {
        return $this->role === RoleUtilisateur::Administrateur;
    }

    public function estAgent(): bool
    {
        return $this->role === RoleUtilisateur::Agent;
    }
}
