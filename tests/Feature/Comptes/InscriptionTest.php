<?php

namespace Tests\Feature\Comptes;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Création du compte, mot de passe d'au moins 12 caractères, jamais stocké en clair.
 */
class InscriptionTest extends TestCase
{
    use RefreshDatabase;

    private const string MOT_DE_PASSE = 'Festival-Jazz-2026';

    public function test_un_visiteur_cree_un_compte_spectateur_et_recoit_un_jeton(): void
    {
        $reponse = $this->postJson(route('auth.inscription'), $this->donnees(['email' => ' Camille.Durand@Exemple.FR ']));

        $reponse->assertCreated()
            ->assertJsonStructure(['access_token', 'token_type', 'expires_in', 'utilisateur' => ['id', 'prenom', 'nom', 'email', 'role']])
            ->assertJsonPath('token_type', 'bearer')
            ->assertJsonPath('expires_in', 3600)
            ->assertJsonPath('utilisateur.email', 'camille.durand@exemple.fr')
            ->assertJsonPath('utilisateur.role', 'spectateur')
            ->assertJsonPath('utilisateur.consentement_emails', false)
            ->assertJsonMissingPath('utilisateur.password');

        $utilisateur = User::where('email', 'camille.durand@exemple.fr')->firstOrFail();
        $this->assertNotSame(self::MOT_DE_PASSE, $utilisateur->password);
        $this->assertTrue(Hash::check(self::MOT_DE_PASSE, $utilisateur->password));
    }

    public function test_le_consentement_aux_emails_est_enregistre_quand_il_est_donne(): void
    {
        $this->postJson(route('auth.inscription'), $this->donnees(['consentement_emails' => true]))
            ->assertCreated()
            ->assertJsonPath('utilisateur.consentement_emails', true);
    }

    public function test_une_adresse_deja_utilisee_est_refusee(): void
    {
        User::factory()->create(['email' => 'camille.durand@exemple.fr']);

        $this->postJson(route('auth.inscription'), $this->donnees(['email' => 'CAMILLE.DURAND@exemple.fr']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => 'Un compte existe déjà avec cette adresse e-mail.']);
    }

    public function test_un_mot_de_passe_de_moins_de_12_caracteres_est_refuse(): void
    {
        $this->postJson(route('auth.inscription'), $this->donnees([
            'password' => 'Court-2026',
            'password_confirmation' => 'Court-2026',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password' => 'au moins 12 caractères']);
    }

    public function test_les_conditions_generales_doivent_etre_acceptees(): void
    {
        $this->postJson(route('auth.inscription'), $this->donnees(['conditions_acceptees' => false]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['conditions_acceptees' => 'Vous devez accepter les conditions générales de vente.']);

        $this->assertDatabaseCount('users', 0);
    }

    /**
     * @param  array<string, mixed>  $remplacements
     * @return array<string, mixed>
     */
    private function donnees(array $remplacements = []): array
    {
        return [
            'prenom' => 'Camille',
            'nom' => 'Durand',
            'email' => 'camille.durand@exemple.fr',
            'password' => self::MOT_DE_PASSE,
            'password_confirmation' => self::MOT_DE_PASSE,
            'conditions_acceptees' => true,
            ...$remplacements,
        ];
    }
}
