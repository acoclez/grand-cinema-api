Bonjour{{ $prenom ? ' '.$prenom : '' }},

Votre paiement est accepté et votre réservation est confirmée. Vous trouverez en pièce jointe un billet PDF par place, chacun avec son QR code.

Film: {{ $film }}
Séance: {{ $quand }}
Salle: {{ $salle }}
Places: {{ $places }}
Réservation: {{ $numero }}

À l'entrée, présentez le QR code de chaque billet, imprimé ou sur votre téléphone. Vos billets sont aussi dans votre compte: {{ $lienMesBillets }}

Bonne séance,
L'équipe du Grand Cinéma
