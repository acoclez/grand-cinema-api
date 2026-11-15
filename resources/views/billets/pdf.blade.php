@php
    $seance = $billet->seance;
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Billet {{ $billet->reservation->numero }} {{ $billet->place->libelle() }}</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; color: #1a1a1a; font-size: 12pt; margin: 0; }
        .entete { border-bottom: 2px solid #8b1a1a; padding-bottom: 8pt; margin-bottom: 14pt; }
        .cinema { font-size: 11pt; color: #8b1a1a; font-weight: bold; text-transform: uppercase; letter-spacing: 1pt; }
        h1 { font-size: 20pt; margin: 4pt 0 0; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; font-weight: normal; color: #555; width: 32%; padding: 3pt 0; vertical-align: top; }
        td { padding: 3pt 0; font-weight: bold; }
        .qr { text-align: center; margin-top: 16pt; }
        .qr img { width: 190pt; height: 190pt; }
        .consigne { text-align: center; font-size: 10pt; color: #555; margin-top: 6pt; }
    </style>
</head>
<body>
    <div class="entete">
        <div class="cinema">Le Grand Cinéma</div>
        <h1>{{ $seance->film->titre }}</h1>
    </div>

    <table>
        <tr><th>Séance</th><td>{{ ucfirst($seance->debut->locale('fr')->isoFormat('dddd D MMMM YYYY [à] HH[:]mm')) }}</td></tr>
        <tr><th>Salle</th><td>{{ $seance->salle->nom }}, {{ strtoupper($seance->langue->value) }}</td></tr>
        <tr><th>Place</th><td>{{ $billet->place->libelle() }}{{ $billet->place->pmr ? ' (place PMR)' : '' }}</td></tr>
        <tr><th>Tarif</th><td>{{ $billet->tarif?->libelle }}, {{ number_format($billet->prix_centimes / 100, 2, ',', ' ') }} €</td></tr>
        <tr><th>Réservation</th><td>{{ $billet->reservation->numero }}</td></tr>
    </table>

    <div class="qr">
        <img src="{{ $qrCode }}" alt="QR code du billet">
    </div>
    <p class="consigne">Présentez ce QR code à l'entrée de la salle, imprimé ou sur votre téléphone.<br>Un billet par personne, valable pour cette séance uniquement.</p>
</body>
</html>
