<?php

namespace App\Services\Paiements;

use App\Enums\StatutPaiement;
use App\Enums\StatutReservation;
use App\Events\ReservationConfirmee;
use App\Exceptions\RegleMetierException;
use App\Models\Paiement;
use App\Models\Reservation;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Paiement en ligne d'une réservation: le spectateur choisit un tarif par place, paie sur la
 * page du prestataire, puis la réservation est confirmée.
 *
 * Le résultat du paiement arrive par deux chemins: la notification du prestataire (webhook) et
 * la vérification demandée par l'application web au retour du spectateur. Les deux passent par
 * enregistrer(), qui ne traite chaque paiement qu'une fois.
 */
readonly class PaiementEnLigne
{
    public const string DELAI_DEPASSE = 'Le délai de 10 minutes est dépassé: vos places ont été libérées. Choisissez-en de nouvelles.';

    public function __construct(private PrestataireDePaiement $prestataire) {}

    /**
     * Attribue un tarif à chaque place, calcule le montant et ouvre la page de paiement.
     *
     * @param  array<int, int>  $tarifsParPlace  Tarif choisi pour chaque place, déjà validé
     *
     * @throws RegleMetierException Le blocage des places est terminé
     */
    public function demarrer(Reservation $reservation, array $tarifsParPlace): SessionDePaiement
    {
        if (! $reservation->estBloquee()) {
            throw new RegleMetierException(self::DELAI_DEPASSE);
        }

        $tarifs = $reservation->seance->tarifs()->get()->keyBy('id');

        DB::transaction(function () use ($reservation, $tarifsParPlace, $tarifs): void {
            foreach ($reservation->billets as $billet) {
                $tarif = $tarifs[$tarifsParPlace[$billet->place_id]];
                $billet->update(['tarif_id' => $tarif->id, 'prix_centimes' => $tarif->prix_centimes]);
            }
            $reservation->update(['montant_centimes' => $reservation->billets->sum('prix_centimes')]);
        });

        // Une seule page de paiement ouverte par réservation: changer de tarifs ferme la précédente.
        $this->fermerLesPaiementsOuverts($reservation);

        $retour = Config::string('app.frontend_url').'/reservations/'.$reservation->id;
        $session = $this->prestataire->ouvrir($reservation, $retour.'?paiement=termine', $retour.'?paiement=abandonne');

        $reservation->paiements()->create([
            'prestataire' => $this->prestataire->nom(),
            'reference_externe' => $session->reference,
            'statut' => StatutPaiement::EnAttente,
            'montant_centimes' => $reservation->montant_centimes,
        ]);

        return $session;
    }

    /**
     * Demande au prestataire où en est le dernier paiement ouvert, au retour du spectateur.
     * La réservation est confirmée tout de suite, sans attendre la notification.
     */
    public function verifier(Reservation $reservation): void
    {
        $paiement = $reservation->paiements()->where('statut', StatutPaiement::EnAttente)->latest('id')->first();

        if ($paiement !== null) {
            $reference = (string) $paiement->reference_externe;
            $this->enregistrer(new NotificationDePaiement($reference, $this->prestataire->consulter($reference)));
        }
    }

    /**
     * Enregistre le résultat d'un paiement. Un paiement accepté confirme la réservation si ses
     * places sont toujours à elle; sinon (places libérées, tarifs changés entre-temps), le
     * spectateur est remboursé. Un paiement déjà traité est ignoré.
     */
    public function enregistrer(NotificationDePaiement $notification): void
    {
        if ($notification->etat === EtatDuPaiement::EnCours) {
            return;
        }

        $aRembourser = DB::transaction(function () use ($notification): ?Paiement {
            $paiement = Paiement::query()
                ->where('prestataire', $this->prestataire->nom())
                ->where('reference_externe', $notification->reference)
                ->lockForUpdate()
                ->first();

            if ($paiement === null || in_array($paiement->statut, [StatutPaiement::Accepte, StatutPaiement::Rembourse], true)) {
                return null;
            }

            if ($notification->etat === EtatDuPaiement::Abandonne) {
                $paiement->update(['statut' => StatutPaiement::Refuse]);

                return null;
            }

            $reservation = Reservation::query()->lockForUpdate()->findOrFail($paiement->reservation_id);

            // Même un peu après les 10 minutes, les places restent à elle tant qu'elles n'ont pas
            // été libérées: personne d'autre n'a pu les prendre.
            $confirmable = $paiement->statut === StatutPaiement::EnAttente
                && $reservation->statut === StatutReservation::EnAttente
                && $reservation->billets()->exists();

            if (! $confirmable) {
                return $paiement;
            }

            $paiement->update(['statut' => StatutPaiement::Accepte]);
            $reservation->update(['statut' => StatutReservation::Confirmee, 'expire_at' => null]);

            // Les billets partent par e-mail une fois la transaction validée.
            ReservationConfirmee::dispatch($reservation);

            return null;
        });

        if ($aRembourser !== null) {
            $this->prestataire->rembourser($notification->reference);
            $aRembourser->update(['statut' => StatutPaiement::Rembourse]);
        }
    }

    /**
     * Ferme les pages de paiement encore ouvertes: quand les places sont libérées, ou quand le
     * spectateur change de tarifs. Elles n'acceptent plus de paiement.
     */
    public function fermerLesPaiementsOuverts(Reservation $reservation): void
    {
        $reservation->paiements()
            ->where('statut', StatutPaiement::EnAttente)
            ->get()
            ->each(function (Paiement $paiement): void {
                try {
                    $this->prestataire->fermer((string) $paiement->reference_externe);
                } catch (Throwable $erreur) {
                    // Page déjà payée ou déjà fermée: si un paiement a eu lieu, sa notification
                    // arrivera et le spectateur sera remboursé.
                    report($erreur);
                }
                $paiement->update(['statut' => StatutPaiement::Refuse]);
            });
    }
}
