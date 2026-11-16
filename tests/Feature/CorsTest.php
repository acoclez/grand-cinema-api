<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Seule l'application web (FRONTEND_URL) peut appeler l'API depuis un navigateur.
 */
class CorsTest extends TestCase
{
    public function test_l_application_web_est_autorisee(): void
    {
        $this->withHeaders(['Origin' => 'http://localhost:5173', 'Access-Control-Request-Method' => 'POST'])
            ->options('/api/auth/connexion')
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173');
    }

    /**
     * L'API annonce seulement l'adresse de l'application web: le navigateur de l'autre site
     * constate qu'elle ne correspond pas à la sienne et bloque alors la réponse.
     */
    public function test_un_autre_site_n_est_pas_autorise(): void
    {
        $reponse = $this->withHeaders(['Origin' => 'https://autre-site.example', 'Access-Control-Request-Method' => 'POST'])
            ->options('/api/auth/connexion');

        $this->assertNotSame('https://autre-site.example', $reponse->headers->get('Access-Control-Allow-Origin'));
        $reponse->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173');
    }
}
