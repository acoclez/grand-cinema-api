<?php

namespace Tests\Unit;

use App\Enums\RoleUtilisateur;
use App\Models\Place;
use App\Models\User;
use PHPUnit\Framework\TestCase;

/**
 * Règles portées par les modèles, testées sans base de données.
 */
class ReglesDesModelesTest extends TestCase
{
    public function test_le_role_d_un_compte_determine_ses_droits(): void
    {
        $administrateur = new User(['role' => RoleUtilisateur::Administrateur]);
        $agent = new User(['role' => RoleUtilisateur::Agent]);
        $spectateur = new User(['role' => RoleUtilisateur::Spectateur]);

        $this->assertTrue($administrateur->estAdministrateur());
        $this->assertFalse($administrateur->estAgent());

        $this->assertTrue($agent->estAgent());
        $this->assertFalse($agent->estAdministrateur());

        $this->assertFalse($spectateur->estAdministrateur());
        $this->assertFalse($spectateur->estAgent());
    }

    public function test_le_libelle_d_une_place_associe_sa_rangee_et_son_numero(): void
    {
        $place = new Place(['rangee' => 'C', 'numero' => 12]);

        $this->assertSame('C12', $place->libelle());
    }
}
