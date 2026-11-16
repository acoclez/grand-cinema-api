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
                '/auth/moi' => ['get', 'put', 'delete'],
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

    public function test_la_documentation_decrit_la_gestion_des_salles_et_des_seances(): void
    {
        $this->getJson('/docs/api.json')
            ->assertOk()
            ->assertJsonStructure(['paths' => [
                '/admin/salles' => ['get'],
                '/admin/seances' => ['get', 'post'],
                '/admin/seances/{seance}' => ['get', 'put', 'delete'],
                '/admin/seances/{seance}/publier' => ['post'],
                '/admin/seances/{seance}/depublier' => ['post'],
            ]]);
    }

    public function test_la_documentation_decrit_les_films_a_l_affiche(): void
    {
        $this->getJson('/docs/api.json')
            ->assertOk()
            ->assertJsonStructure(['paths' => [
                '/films' => ['get'],
                '/films/{film}' => ['get'],
            ]]);
    }

    public function test_la_documentation_decrit_le_paiement_en_ligne(): void
    {
        $this->getJson('/docs/api.json')
            ->assertOk()
            ->assertJsonStructure(['paths' => [
                '/reservations/{reservation}/paiement' => ['post'],
                '/reservations/{reservation}/paiement/verification' => ['post'],
                '/paiements/stripe/notifications' => ['post'],
            ]]);
    }

    public function test_la_documentation_decrit_les_billets_electroniques(): void
    {
        $this->getJson('/docs/api.json')
            ->assertOk()
            ->assertJsonStructure(['paths' => [
                '/billets' => ['get'],
                '/billets/{billet}/pdf' => ['get'],
            ]])
            ->assertJsonPath('paths./billets/{billet}/pdf.get.responses.200.content.application/pdf.schema.format', 'binary');
    }

    public function test_la_documentation_decrit_le_controle_des_billets(): void
    {
        $this->getJson('/docs/api.json')
            ->assertOk()
            ->assertJsonStructure(['paths' => ['/controle/seances/{seance}/billets' => ['post']]]);
    }

    public function test_la_documentation_decrit_la_recherche_des_reservations(): void
    {
        $this->getJson('/docs/api.json')
            ->assertOk()
            ->assertJsonStructure(['paths' => [
                '/guichet/reservations' => ['get'],
                '/guichet/reservations/{reservation}' => ['get'],
            ]]);
    }

    public function test_la_documentation_decrit_les_seances_publiees(): void
    {
        $this->getJson('/docs/api.json')
            ->assertOk()
            ->assertJsonStructure(['paths' => [
                '/seances' => ['get'],
            ]]);
    }
}
