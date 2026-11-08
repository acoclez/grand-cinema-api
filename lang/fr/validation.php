<?php

/*
|--------------------------------------------------------------------------
| Messages de validation en français
|--------------------------------------------------------------------------
| Seules les règles utilisées par l'API sont traduites. Pour une règle absente
| de ce fichier, Laravel affiche le message anglais par défaut : ajouter ici
| la traduction quand une user story utilise une nouvelle règle.
*/

return [
    'accepted' => 'Vous devez accepter :attribute.',
    'after' => 'Le champ :attribute doit être une date postérieure au :date.',
    'after_or_equal' => 'Le champ :attribute doit être une date postérieure ou égale au :date.',
    'array' => 'Le champ :attribute doit être une liste.',
    'between' => [
        'numeric' => 'Le champ :attribute doit être compris entre :min et :max.',
    ],
    'boolean' => 'Le champ :attribute doit être vrai ou faux.',
    'confirmed' => 'La confirmation du champ :attribute ne correspond pas.',
    'current_password' => 'Le mot de passe est incorrect.',
    'date_format' => 'Le champ :attribute ne correspond pas au format :format.',
    'distinct' => 'Le champ :attribute a une valeur en double.',
    'email' => 'Le champ :attribute doit être une adresse e-mail valide.',
    'enum' => 'La valeur du champ :attribute n\'est pas valide.',
    'exists' => 'La valeur du champ :attribute n\'existe pas.',
    'in' => 'La valeur du champ :attribute n\'est pas valide.',
    'integer' => 'Le champ :attribute doit être un nombre entier.',
    'max' => [
        'array' => 'Le champ :attribute ne doit pas contenir plus de :max éléments.',
        'string' => 'Le champ :attribute ne doit pas dépasser :max caractères.',
    ],
    'min' => [
        'array' => 'Le champ :attribute doit contenir au moins :min élément.',
        'string' => 'Le champ :attribute doit contenir au moins :min caractères.',
    ],
    'present' => 'Le champ :attribute doit être envoyé, même vide.',
    'required' => 'Le champ :attribute est obligatoire.',
    'string' => 'Le champ :attribute doit être une chaîne de caractères.',
    'unique' => 'Cette valeur du champ :attribute est déjà utilisée.',
    'url' => 'Le champ :attribute doit être une adresse web valide, commençant par https://.',

    'custom' => [
        'email' => [
            'unique' => 'Un compte existe déjà avec cette adresse e-mail.',
        ],
        'pays_residence' => [
            'in' => 'Choisissez un pays dans la liste.',
        ],
        'film_id' => [
            'exists' => 'Ce film n\'existe pas ou n\'est plus à l\'affiche.',
        ],
        'date' => [
            'after_or_equal' => 'Les séances passées ne sont plus proposées: choisissez aujourd’hui ou une date à venir.',
            'date_format' => 'Indiquez une date au format AAAA-MM-JJ.',
        ],
        'debut' => [
            'after' => 'La séance doit commencer dans le futur.',
            'date_format' => 'Indiquez la date et l’heure de la séance.',
        ],
        'tarifs' => [
            'required' => 'Ajoutez au moins un tarif.',
            'min' => 'Ajoutez au moins un tarif.',
        ],
        'tarifs.*.libelle' => [
            'distinct' => 'Deux tarifs ne peuvent pas avoir le même libellé.',
        ],
    ],

    'attributes' => [
        'prenom' => 'prénom',
        'nom' => 'nom',
        'email' => 'adresse e-mail',
        'password' => 'mot de passe',
        'consentement_emails' => 'consentement aux e-mails',
        'pays_residence' => 'pays de résidence',
        'conditions_acceptees' => 'les conditions générales de vente',
        'token' => 'lien de réinitialisation',
        'titre' => 'titre',
        'synopsis' => 'synopsis',
        'duree_minutes' => 'durée',
        'genre' => 'genre',
        'classification' => 'classification',
        'langue_originale' => 'langue originale',
        'affiche_url' => 'affiche',
        'bande_annonce_url' => 'bande-annonce',
        'archives' => 'films archivés',
        'par_page' => 'nombre par page',
        'film_id' => 'film',
        'salle_id' => 'salle',
        'debut' => 'début',
        'langue' => 'langue',
        'tarifs' => 'tarifs',
        'tarifs.*.libelle' => 'libellé du tarif',
        'tarifs.*.prix_centimes' => 'prix du tarif',
        'date' => 'date',
    ],
];
