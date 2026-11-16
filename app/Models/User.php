<?php

namespace App\Models;

use App\Enums\RoleUtilisateur;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

/**
 * @property RoleUtilisateur $role
 */
#[Fillable(['prenom', 'nom', 'email', 'password', 'role', 'pays_residence', 'consentement_emails'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
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

    /**
     * Droit à l'effacement: le nom, l'adresse e-mail et le mot de passe sont remplacés,
     * le consentement retiré et la date d'anonymisation renseignée. Le compte ne peut plus
     * se connecter. La ligne reste en base pour que les réservations passées comptent encore
     * dans les statistiques, sans lien avec une personne identifiable. Le pays de résidence
     * est conservé: seul, il ne permet pas d'identifier quelqu'un et sert à l'indicateur O5.
     */
    public function anonymiser(): void
    {
        DB::transaction(function (): void {
            DB::table('password_reset_tokens')->where('email', $this->email)->delete();

            $this->forceFill([
                'prenom' => 'Compte',
                'nom' => 'supprimé',
                'email' => 'compte-supprime-'.$this->id.'@anonyme.invalid',
                'password' => Str::random(64),
                'email_verified_at' => null,
                'consentement_emails' => false,
                'remember_token' => null,
                'anonymise_at' => now(),
            ])->save();
        });
    }

    public function estAdministrateur(): bool
    {
        return $this->role === RoleUtilisateur::Administrateur;
    }

    public function estAgent(): bool
    {
        return $this->role === RoleUtilisateur::Agent;
    }

    /**
     * Identifiant du compte, inscrit dans le jeton JWT.
     */
    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    /**
     * @return array<string, mixed>
     */
    public function getJWTCustomClaims(): array
    {
        return [];
    }
}
