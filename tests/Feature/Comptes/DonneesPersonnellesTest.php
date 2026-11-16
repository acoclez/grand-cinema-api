<?php

namespace Tests\Feature\Comptes;

use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Un spectateur modifie ses informations, gère son consentement et supprime son compte.
 */
class DonneesPersonnellesTest extends TestCase
{
    use RefreshDatabase;

    private const string MOT_DE_PASSE = 'Festival-Jazz-2026';

    private User $camille;

    protected function setUp(): void
    {
        parent::setUp();

        $this->camille = User::factory()->create([
            'prenom' => 'Camille',
            'nom' => 'Durand',
            'email' => 'camille.durand@exemple.fr',
            'password' => self::MOT_DE_PASSE,
            'pays_residence' => null,
            'consentement_emails' => false,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function profil(array $changements = []): array
    {
        return [
            'prenom' => 'Camille',
            'nom' => 'Durand',
            'email' => 'camille.durand@exemple.fr',
            'pays_residence' => null,
            'consentement_emails' => false,
            ...$changements,
        ];
    }

    private function jetonDe(string $email): string
    {
        $jeton = $this->postJson(route('auth.connexion'), ['email' => $email, 'password' => self::MOT_DE_PASSE])
            ->assertOk()
            ->json('access_token');
        $this->assertIsString($jeton);
        $this->oublierLaConnexion();

        return $jeton;
    }

    /**
     * Chaque requête d'un test repart de zéro, comme en production.
     */
    private function oublierLaConnexion(): void
    {
        $this->app->make('auth')->forgetGuards();
        $this->app->forgetScopedInstances();
    }

    public function test_le_spectateur_modifie_ses_coordonnees_et_son_pays_de_residence(): void
    {
        $this->actingAs($this->camille, 'api')
            ->putJson(route('auth.moi.modifier'), $this->profil([
                'prenom' => 'Camille',
                'nom' => 'Martin',
                'email' => ' Camille.Martin@Exemple.fr ',
                'pays_residence' => 'BE',
            ]))
            ->assertOk()
            ->assertJsonPath('data.nom', 'Martin')
            ->assertJsonPath('data.email', 'camille.martin@exemple.fr')
            ->assertJsonPath('data.pays_residence', 'BE');

        $this->assertDatabaseHas('users', ['id' => $this->camille->id, 'nom' => 'Martin', 'pays_residence' => 'BE']);
    }

    public function test_le_pays_de_residence_est_facultatif_et_peut_etre_efface(): void
    {
        $this->camille->update(['pays_residence' => 'FR']);

        $this->actingAs($this->camille, 'api')
            ->putJson(route('auth.moi.modifier'), $this->profil(['pays_residence' => null]))
            ->assertOk()
            ->assertJsonPath('data.pays_residence', null);
    }

    public function test_le_consentement_aux_e_mails_se_modifie_a_tout_moment(): void
    {
        $this->assertFalse($this->camille->consentement_emails);

        $this->actingAs($this->camille, 'api')
            ->putJson(route('auth.moi.modifier'), $this->profil(['consentement_emails' => true]))
            ->assertOk()
            ->assertJsonPath('data.consentement_emails', true);

        $this->putJson(route('auth.moi.modifier'), $this->profil(['consentement_emails' => false]))
            ->assertOk()
            ->assertJsonPath('data.consentement_emails', false);
    }

    public function test_les_valeurs_invalides_sont_signalees(): void
    {
        User::factory()->create(['email' => 'deja.pris@exemple.fr']);

        $this->actingAs($this->camille, 'api')
            ->putJson(route('auth.moi.modifier'), $this->profil([
                'prenom' => '',
                'email' => 'deja.pris@exemple.fr',
                'pays_residence' => 'XX',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'prenom' => 'Le champ prénom est obligatoire.',
                'email' => 'Un compte existe déjà avec cette adresse e-mail.',
                'pays_residence' => 'Choisissez un pays dans la liste.',
            ]);
    }

    public function test_sans_jeton_le_profil_ne_peut_pas_etre_modifie(): void
    {
        $this->putJson(route('auth.moi.modifier'), $this->profil())->assertUnauthorized();
        $this->deleteJson(route('auth.moi.supprimer'), ['password' => self::MOT_DE_PASSE])->assertUnauthorized();
    }

    public function test_la_suppression_efface_les_donnees_personnelles_et_garde_les_reservations_sans_lien(): void
    {
        $this->camille->update(['pays_residence' => 'BE', 'consentement_emails' => true]);
        $reservation = Reservation::factory()->confirmee()->create(['user_id' => $this->camille->id]);
        DB::table('password_reset_tokens')->insert(['email' => 'camille.durand@exemple.fr', 'token' => 'x', 'created_at' => now()]);
        $jeton = $this->jetonDe('camille.durand@exemple.fr');

        $this->withToken($jeton)
            ->deleteJson(route('auth.moi.supprimer'), ['password' => self::MOT_DE_PASSE])
            ->assertNoContent();

        $compte = $this->camille->fresh();
        $this->assertNotNull($compte?->anonymise_at);
        $this->assertSame('Compte', $compte->prenom);
        $this->assertSame('supprimé', $compte->nom);
        $this->assertSame('compte-supprime-'.$compte->id.'@anonyme.invalid', $compte->email);
        $this->assertFalse($compte->consentement_emails);
        $this->assertSame('BE', $compte->pays_residence);
        $this->assertDatabaseMissing('users', ['email' => 'camille.durand@exemple.fr']);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'camille.durand@exemple.fr']);
        $this->assertDatabaseHas('reservations', ['id' => $reservation->id]);

        // Le compte ne se connecte plus et son jeton est refusé.
        $this->oublierLaConnexion();
        $this->withToken($jeton)->getJson(route('auth.moi'))->assertUnauthorized();
        $this->postJson(route('auth.connexion'), ['email' => 'camille.durand@exemple.fr', 'password' => self::MOT_DE_PASSE])
            ->assertUnauthorized();
    }

    public function test_un_jeton_delivre_avant_la_suppression_est_refuse_sur_un_autre_appareil(): void
    {
        $telephone = $this->jetonDe('camille.durand@exemple.fr');
        $ordinateur = $this->jetonDe('camille.durand@exemple.fr');

        $this->withToken($ordinateur)
            ->deleteJson(route('auth.moi.supprimer'), ['password' => self::MOT_DE_PASSE])
            ->assertNoContent();

        $this->oublierLaConnexion();
        $this->withToken($telephone)->getJson(route('auth.moi'))->assertUnauthorized();
    }

    public function test_la_suppression_demande_le_bon_mot_de_passe(): void
    {
        $this->actingAs($this->camille, 'api')
            ->deleteJson(route('auth.moi.supprimer'), ['password' => 'mauvais-mot-de-passe'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password' => 'Le mot de passe est incorrect.']);

        $this->assertNull($this->camille->fresh()?->anonymise_at);
    }

    public function test_un_compte_du_personnel_ne_se_supprime_pas_lui_meme(): void
    {
        $administrateur = User::factory()->administrateur()->create(['password' => self::MOT_DE_PASSE]);

        $this->actingAs($administrateur, 'api')
            ->deleteJson(route('auth.moi.supprimer'), ['password' => self::MOT_DE_PASSE])
            ->assertForbidden();
    }
}
