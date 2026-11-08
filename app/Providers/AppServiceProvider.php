<?php

namespace App\Providers;

use App\Models\User;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Un compte supprimé (anonymisé) n'est plus reconnu: ni connexion, ni réinitialisation
        // du mot de passe, ni accès avec un jeton délivré avant la suppression.
        Auth::provider('comptes-actifs', fn (Application $app, array $config) => (new EloquentUserProvider($app->make('hash'), $config['model']))
            ->withQuery(fn (Builder $comptes) => $comptes->whereNull('anonymise_at')));

        // Un mot de passe compte au moins 12 caractères.
        Password::defaults(fn () => Password::min(12));

        // Contre les essais de mots de passe en série: 5 tentatives par minute
        // pour une même adresse depuis une même adresse IP.
        RateLimiter::for('comptes', fn (Request $request) => Limit::perMinute(5)->by(
            mb_strtolower((string) $request->string('email')).'|'.$request->ip(),
        ));

        // Le lien de réinitialisation ouvre la page de l'application web, pas l'API.
        ResetPassword::toMailUsing(function (User $utilisateur, string $jeton): MailMessage {
            $lien = Config::string('app.frontend_url').'/reinitialiser-mot-de-passe?'
                .http_build_query(['token' => $jeton, 'email' => $utilisateur->email]);

            return (new MailMessage)
                ->subject('Réinitialisation de votre mot de passe')
                ->greeting('Bonjour '.$utilisateur->prenom.',')
                ->line('Vous avez demandé à réinitialiser le mot de passe de votre compte Grand Cinéma.')
                ->action('Choisir un nouveau mot de passe', $lien)
                ->line('Ce lien est valable une heure.')
                ->line('Si vous n\'êtes pas à l\'origine de cette demande, ignorez ce message: votre mot de passe reste inchangé.')
                ->salutation('L\'équipe du Grand Cinéma');
        });

        // La documentation de l'API (/docs/api) est publique, y compris en production.
        Gate::define('viewApiDocs', fn (?User $utilisateur = null): bool => true);

        // Les routes protégées attendent un jeton JWT dans l'en-tête Authorization.
        Scramble::configure()->withDocumentTransformers(function (OpenApi $openApi): void {
            $openApi->secure(SecurityScheme::http('bearer', 'JWT'));
        });
    }
}
