<?php

namespace Tests\Feature\Comptes;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/**
 * "Mot de passe oublié" envoie un lien de réinitialisation valable une heure.
 */
class MotDePasseOublieTest extends TestCase
{
    use RefreshDatabase;

    private const string MESSAGE_ENVOI = 'Si un compte existe pour cette adresse, un lien de réinitialisation valable une heure vient d\'être envoyé.';

    private const string NOUVEAU_MOT_DE_PASSE = 'Nouveau-Festival-2026';

    public function test_la_demande_envoie_un_lien_vers_l_application_web(): void
    {
        Notification::fake();
        $utilisateur = User::factory()->create(['prenom' => 'Camille', 'email' => 'camille.durand@exemple.fr']);

        $this->postJson(route('auth.mot-de-passe-oublie'), ['email' => 'Camille.Durand@exemple.fr'])
            ->assertOk()
            ->assertExactJson(['message' => self::MESSAGE_ENVOI]);

        Notification::assertSentTo($utilisateur, ResetPassword::class, function (ResetPassword $notification) use ($utilisateur): bool {
            $mail = $notification->toMail($utilisateur);

            $this->assertSame('Réinitialisation de votre mot de passe', $mail->subject);
            $this->assertSame('Bonjour Camille,', $mail->greeting);
            $this->assertStringStartsWith('http://localhost:5173/reinitialiser-mot-de-passe?token=', (string) $mail->actionUrl);
            $this->assertStringContainsString('email=camille.durand%40exemple.fr', (string) $mail->actionUrl);
            $this->assertContains('Ce lien est valable une heure.', $mail->outroLines);

            return true;
        });
    }

    public function test_une_adresse_inconnue_recoit_la_meme_reponse_sans_envoi(): void
    {
        Notification::fake();

        $this->postJson(route('auth.mot-de-passe-oublie'), ['email' => 'inconnu@exemple.fr'])
            ->assertOk()
            ->assertExactJson(['message' => self::MESSAGE_ENVOI]);

        Notification::assertNothingSent();
    }

    public function test_le_lien_permet_de_choisir_un_nouveau_mot_de_passe(): void
    {
        $utilisateur = User::factory()->create();
        $jeton = Password::createToken($utilisateur);

        $this->postJson(route('auth.reinitialiser-mot-de-passe'), $this->donnees($utilisateur, $jeton))
            ->assertOk()
            ->assertJsonPath('message', 'Votre mot de passe a été modifié. Vous pouvez vous connecter.');

        $this->assertTrue(Hash::check(self::NOUVEAU_MOT_DE_PASSE, $utilisateur->refresh()->password));
    }

    public function test_le_lien_n_est_plus_valable_apres_une_heure(): void
    {
        $utilisateur = User::factory()->create();
        $jeton = Password::createToken($utilisateur);

        $this->travel(61)->minutes();

        $this->postJson(route('auth.reinitialiser-mot-de-passe'), $this->donnees($utilisateur, $jeton))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['token' => 'Ce lien de réinitialisation est invalide ou a expiré.']);

        $this->assertFalse(Hash::check(self::NOUVEAU_MOT_DE_PASSE, $utilisateur->refresh()->password));
    }

    public function test_un_lien_invente_est_refuse(): void
    {
        $utilisateur = User::factory()->create();

        $this->postJson(route('auth.reinitialiser-mot-de-passe'), $this->donnees($utilisateur, 'jeton-invente'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['token']);
    }

    /**
     * @return array<string, string>
     */
    private function donnees(User $utilisateur, string $jeton): array
    {
        return [
            'token' => $jeton,
            'email' => $utilisateur->email,
            'password' => self::NOUVEAU_MOT_DE_PASSE,
            'password_confirmation' => self::NOUVEAU_MOT_DE_PASSE,
        ];
    }
}
