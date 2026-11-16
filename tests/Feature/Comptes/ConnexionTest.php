<?php

namespace Tests\Feature\Comptes;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Connexion, et message d'erreur qui ne précise pas quel identifiant est faux.
 */
class ConnexionTest extends TestCase
{
    use RefreshDatabase;

    private const string MOT_DE_PASSE = 'Festival-Jazz-2026';

    private const string MESSAGE_ECHEC = 'Adresse e-mail ou mot de passe incorrect.';

    protected function setUp(): void
    {
        parent::setUp();

        User::factory()->create(['email' => 'camille.durand@exemple.fr', 'password' => self::MOT_DE_PASSE]);
    }

    public function test_un_spectateur_se_connecte_avec_les_bons_identifiants(): void
    {
        $this->postJson(route('auth.connexion'), ['email' => 'Camille.Durand@exemple.fr', 'password' => self::MOT_DE_PASSE])
            ->assertOk()
            ->assertJsonPath('token_type', 'bearer')
            ->assertJsonPath('utilisateur.email', 'camille.durand@exemple.fr');
    }

    public function test_un_mauvais_mot_de_passe_et_une_adresse_inconnue_donnent_le_meme_message(): void
    {
        $this->postJson(route('auth.connexion'), ['email' => 'camille.durand@exemple.fr', 'password' => 'Mauvais-mot-de-passe'])
            ->assertUnauthorized()
            ->assertExactJson(['message' => self::MESSAGE_ECHEC]);

        $this->postJson(route('auth.connexion'), ['email' => 'inconnu@exemple.fr', 'password' => self::MOT_DE_PASSE])
            ->assertUnauthorized()
            ->assertExactJson(['message' => self::MESSAGE_ECHEC]);
    }

    public function test_les_champs_vides_sont_refuses(): void
    {
        $this->postJson(route('auth.connexion'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'email' => 'Le champ adresse e-mail est obligatoire.',
                'password' => 'Le champ mot de passe est obligatoire.',
            ]);
    }

    public function test_les_essais_en_serie_sont_bloques_apres_5_tentatives(): void
    {
        for ($essai = 1; $essai <= 5; $essai++) {
            $this->postJson(route('auth.connexion'), ['email' => 'camille.durand@exemple.fr', 'password' => 'Essai-'.$essai])
                ->assertUnauthorized();
        }

        $this->postJson(route('auth.connexion'), ['email' => 'camille.durand@exemple.fr', 'password' => self::MOT_DE_PASSE])
            ->assertTooManyRequests()
            ->assertJsonPath('message', 'Trop de tentatives. Réessayez dans une minute.');
    }
}
