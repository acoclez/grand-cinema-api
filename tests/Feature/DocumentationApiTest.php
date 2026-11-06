<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * La documentation publique OpenAPI générée par Scramble.
 */
class DocumentationApiTest extends TestCase
{
    public function test_la_documentation_est_consultable_sans_compte(): void
    {
        $this->get('/docs/api')->assertOk();
    }

    public function test_la_documentation_decrit_les_routes_de_compte_et_le_jeton_jwt(): void
    {
        $this->getJson('/docs/api.json')
            ->assertOk()
            ->assertJsonPath('components.securitySchemes.http.scheme', 'bearer')
            ->assertJsonStructure(['paths' => [
                '/auth/inscription',
                '/auth/connexion',
                '/auth/moi',
                '/auth/rafraichir',
                '/auth/deconnexion',
                '/auth/mot-de-passe-oublie',
                '/auth/reinitialiser-mot-de-passe',
            ]]);
    }

    public function test_la_documentation_decrit_la_gestion_des_films(): void
    {
        $this->getJson('/docs/api.json')
            ->assertOk()
            ->assertJsonStructure(['paths' => [
                '/admin/films' => ['get', 'post'],
                '/admin/films/{film}' => ['get', 'put'],
                '/admin/films/{film}/archiver' => ['post'],
                '/admin/films/{film}/restaurer' => ['post'],
            ]]);
    }
}
