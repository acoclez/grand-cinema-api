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
    'boolean' => 'Le champ :attribute doit être vrai ou faux.',
    'confirmed' => 'La confirmation du champ :attribute ne correspond pas.',
    'email' => 'Le champ :attribute doit être une adresse e-mail valide.',
    'max' => [
        'string' => 'Le champ :attribute ne doit pas dépasser :max caractères.',
    ],
    'min' => [
        'string' => 'Le champ :attribute doit contenir au moins :min caractères.',
    ],
    'required' => 'Le champ :attribute est obligatoire.',
    'string' => 'Le champ :attribute doit être une chaîne de caractères.',
    'unique' => 'Cette valeur du champ :attribute est déjà utilisée.',

    'custom' => [
        'email' => [
            'unique' => 'Un compte existe déjà avec cette adresse e-mail.',
        ],
    ],

    'attributes' => [
        'prenom' => 'prénom',
        'nom' => 'nom',
        'email' => 'adresse e-mail',
        'password' => 'mot de passe',
        'consentement_emails' => 'consentement aux e-mails',
        'conditions_acceptees' => 'les conditions générales de vente',
        'token' => 'lien de réinitialisation',
    ],
];
