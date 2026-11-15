<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Vos billets</title>
</head>
<body style="margin: 0; padding: 24px 16px; background: #f5f3ef; font-family: Arial, Helvetica, sans-serif; color: #1a1a1a; line-height: 1.5;">
    <div style="max-width: 560px; margin: 0 auto; background: #ffffff; padding: 24px; border-radius: 6px;">
        <p style="margin: 0 0 4px; color: #8b1a1a; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; font-size: 13px;">Le Grand Cinéma</p>
        <h1 style="margin: 0 0 16px; font-size: 22px;">Vos billets sont prêts</h1>

        <p>Bonjour{{ $prenom ? ' '.$prenom : '' }},</p>
        <p>Votre paiement est accepté et votre réservation est confirmée. Vous trouverez en pièce jointe un billet PDF par place, chacun avec son QR code.</p>

        <table role="presentation" style="width: 100%; border-collapse: collapse; margin: 16px 0;">
            <tr><td style="padding: 4px 0; color: #555; width: 35%;">Film</td><td style="padding: 4px 0; font-weight: bold;">{{ $film }}</td></tr>
            <tr><td style="padding: 4px 0; color: #555;">Séance</td><td style="padding: 4px 0; font-weight: bold;">{{ $quand }}</td></tr>
            <tr><td style="padding: 4px 0; color: #555;">Salle</td><td style="padding: 4px 0; font-weight: bold;">{{ $salle }}</td></tr>
            <tr><td style="padding: 4px 0; color: #555;">Places</td><td style="padding: 4px 0; font-weight: bold;">{{ $places }}</td></tr>
            <tr><td style="padding: 4px 0; color: #555;">Réservation</td><td style="padding: 4px 0; font-weight: bold;">{{ $numero }}</td></tr>
        </table>

        <p>À l'entrée, présentez le QR code de chaque billet, imprimé ou sur votre téléphone. Vos billets sont aussi dans votre compte:</p>
        <p style="margin: 20px 0;"><a href="{{ $lienMesBillets }}" style="background: #8b1a1a; color: #ffffff; padding: 10px 18px; border-radius: 4px; text-decoration: none; font-weight: bold;">Voir mes billets</a></p>

        <p style="margin-bottom: 0;">Bonne séance,<br>L'équipe du Grand Cinéma</p>
    </div>
</body>
</html>
