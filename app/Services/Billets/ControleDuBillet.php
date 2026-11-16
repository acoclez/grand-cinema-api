<?php

namespace App\Services\Billets;

use App\Models\Billet;

/** Contrôle d'un billet: le résultat, le message affiché à l'agent et le billet reconnu. */
readonly class ControleDuBillet
{
    public function __construct(
        public ResultatDuControle $resultat,
        public string $message,
        public ?Billet $billet = null,
    ) {}
}
