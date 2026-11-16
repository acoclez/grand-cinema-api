<?php

namespace Tests\Feature\Comptes;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Accès aux routes protégées avec le jeton, renouvellement et déconnexion.
 */
class JetonTest extends TestCase
{
    use RefreshDatabase;

    private const string MOT_DE_PASSE = 'Festival-Jazz-2026';

    private const string MESSAGE_401 = 'Authentification requise: jeton absent, invalide ou expiré.';

    public function test_une_route_protegee_refuse_une_requete_sans_jeton(): void
    {
        $this->getJson(route('auth.moi'))
            ->assertUnauthorized()
            ->assertExactJson(['message' => self::MESSAGE_401]);
    }

    public function test_une_route_protegee_refuse_un_jeton_invalide(): void
    {
        $this->withToken('jeton-invente')
            ->getJson(route('auth.moi'))
            ->assertUnauthorized();
    }

    public function test_le_jeton_donne_acces_au_profil_du_compte_connecte(): void
    {
        $utilisateur = User::factory()->create(['password' => self::MOT_DE_PASSE]);

        $this->withToken($this->jetonDe($utilisateur))
            ->getJson(route('auth.moi'))
            ->assertOk()
            ->assertJsonPath('data.id', $utilisateur->id)
            ->assertJsonPath('data.email', $utilisateur->email)
            ->assertJsonMissingPath('data.password');
    }

    public function test_le_renouvellement_donne_un_nouveau_jeton_et_invalide_l_ancien(): void
    {
        $ancien = $this->jetonDe(User::factory()->create(['password' => self::MOT_DE_PASSE]));

        $nouveau = $this->withToken($ancien)
            ->postJson(route('auth.rafraichir'))
            ->assertOk()
            ->json('access_token');

        $this->assertIsString($nouveau);
        $this->assertNotSame($ancien, $nouveau);

        $this->oublierLaConnexion();
        $this->withToken($ancien)->getJson(route('auth.moi'))->assertUnauthorized();

        $this->oublierLaConnexion();
        $this->withToken($nouveau)->getJson(route('auth.moi'))->assertOk();
    }

    public function test_apres_la_deconnexion_le_jeton_ne_donne_plus_acces(): void
    {
        $jeton = $this->jetonDe(User::factory()->create(['password' => self::MOT_DE_PASSE]));

        $this->withToken($jeton)->postJson(route('auth.deconnexion'))->assertNoContent();

        $this->oublierLaConnexion();
        $this->withToken($jeton)->getJson(route('auth.moi'))->assertUnauthorized();
    }

    private function jetonDe(User $utilisateur): string
    {
        $jeton = $this->postJson(route('auth.connexion'), ['email' => $utilisateur->email, 'password' => self::MOT_DE_PASSE])
            ->assertOk()
            ->json('access_token');
        $this->assertIsString($jeton);
        $this->oublierLaConnexion();

        return $jeton;
    }

    /**
     * En production, chaque requête repart de zéro.
     * Dans un test, l'application est partagée entre les requêtes: la garde et le
     * service JWT garderaient le compte et le jeton de la requête précédente.
     * Ils sont oubliés pour que seul le nouveau jeton compte.
     */
    private function oublierLaConnexion(): void
    {
        $this->app->make('auth')->forgetGuards();
        $this->app->forgetScopedInstances();
    }
}
