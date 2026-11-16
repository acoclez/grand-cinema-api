# Grand Cinéma – API

API de réservation du Grand Cinéma (bloc 3 du Bachelor Développeur d'application Web, Studi).
Elle sert l'application web [grand-cinema-web](https://github.com/acoclez/grand-cinema-web).

## Pile technique

- PHP 8.5, Laravel 13, Eloquent ORM, MySQL 8.
- Authentification par jetons JWT (`php-open-source-saver/jwt-auth`).
- Documentation OpenAPI générée par Scramble, consultable sur `/docs/api`.
- Paiement en ligne par Stripe Checkout (`stripe/stripe-php`), billets PDF avec QR code (`barryvdh/laravel-dompdf`, `chillerlan/php-qrcode`), e-mails par Brevo en SMTP.
- Qualité : Laravel Pint (style PSR-12), Larastan (analyse statique), PHPUnit (tests et couverture), SonarCloud.
- Intégration continue : GitHub Actions, à chaque merge request vers `develop` et à chaque push sur `develop` et `main`.

## Convention de branches

| Branche | Rôle | Créée depuis | Fusionnée dans |
|---|---|---|---|
| `main` | Version en production, toujours déployable | – | – |
| `develop` | Intégration des fonctionnalités terminées, déployée en préproduction | `main` | `main` (mise en production) |
| `feature/<nom-court>` | Une user story (ex. `feature/plan-de-salle`) | `develop` | `develop` par merge request |
| `fix/<nom-court>` | Correction d'un bug constaté en développement ou en préproduction | `develop` | `develop` par merge request |
| `hotfix/<nom-court>` | Correction urgente en production | `main` | `main` puis `develop` |
| `chore/<nom-court>` | Outillage, dépendances, intégration continue, documentation | `develop` | `develop` par merge request |

Règles :

- Le nom court est en minuscules, en français, les mots séparés par des tirets, et reprend le nom de la branche prévu sur la carte Trello de la user story.
- Aucun commit direct sur `main` ni sur `develop` : tout passe par une merge request relue, avec l'intégration continue au vert.
- Une merge request = une user story (ou une correction). La branche est supprimée après la fusion.
- Message de commit : `type(périmètre): description à l'impératif`, avec `type` parmi `feat`, `fix`, `test`, `refactor`, `docs`, `chore`, `ci`. Exemple : `feat(seances): lister les séances publiées d'une date`.

## Installation locale

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan jwt:secret
php artisan migrate --seed
php artisan serve
```

Prérequis : PHP 8.5, Composer, une base MySQL `grand_cinema` (voir `.env.example`).

## Commandes utiles

| Commande | Rôle |
|---|---|
| `composer test` | Tests unitaires et de fonctionnalité |
| `vendor/bin/phpunit --coverage-html coverage` | Tests avec rapport de couverture |
| `vendor/bin/pint --test` | Vérification du style de code |
| `vendor/bin/phpstan analyse` | Analyse statique |
| `php artisan schedule:work` | Tâches planifiées en local, dont l'expiration des réservations non payées chaque minute (en production, `php artisan schedule:run` lancé chaque minute) |

## Déploiement (Clever Cloud)

L'environnement de démonstration de l'examen est une préproduction minimale, déployée depuis `develop`: une instance PHP nano, une base MySQL DEV liée à l'application, et l'application web sur une application statique. La production du cinéma reprendrait la configuration chiffrée au bloc 1 (2 à 4 instances XS, MySQL XS Small Space).

L'application Clever Cloud suit la branche `develop` du dépôt GitHub: chaque fusion d'une merge request la redéploie. Une branche protégée n'accepte une merge request qu'avec l'intégration continue au vert, donc seul du code testé est déployé.

Fichiers lus par Clever Cloud:

- `clevercloud/post_build.sh`: après chaque build, les migrations, puis les données de démonstration. Les seeders ne créent que ce qui manque: relancés, ils ne créent aucun doublon.
- `clevercloud/cron.json` et `clevercloud/cron.sh`: le planificateur de Laravel chaque minute, sur chaque instance. Chaque tâche ne tourne que sur une instance à la fois (`onOneServer`), grâce au verrou pris dans le cache en base.

Variables d'environnement de l'application PHP (la base MySQL liée ajoute les variables `MYSQL_ADDON_*`, lues par `config/database.php`):

| Variable | Valeur |
|---|---|
| `CC_PHP_VERSION` | `8.5` |
| `CC_WEBROOT` | `/public` |
| `CC_POST_BUILD_HOOK` | `bash clevercloud/post_build.sh` |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_KEY` | Clé générée par `php artisan key:generate --show` |
| `APP_URL` | Adresse de l'API, par exemple `https://grand-cinema-api.cleverapps.io` |
| `APP_DEMONSTRATION` | `true`: les séances d'exemple sont reprogrammées chaque nuit pour les 7 jours à venir |
| `FRONTEND_URL` | Adresse complète de l'application web, avec `https://` et sans `/` final, par exemple `https://grand-cinema.cleverapps.io`: autorisée par CORS et utilisée dans les e-mails |
| `JWT_SECRET` | Clé générée par `php artisan jwt:secret --show` |
| `DB_CONNECTION` | `mysql` |
| `LOG_CHANNEL` | `stderr`, pour lire les journaux dans la console Clever Cloud |
| `QUEUE_CONNECTION` | `database`: les e-mails partent par la file d'attente, vidée chaque minute par le planificateur |
| `MAIL_MAILER` | `smtp` |
| `MAIL_HOST` | `smtp-relay.brevo.com` |
| `MAIL_PORT` | `587` |
| `MAIL_USERNAME` | Identifiant SMTP du compte Brevo |
| `MAIL_PASSWORD` | Clé SMTP créée dans Brevo |
| `MAIL_FROM_ADDRESS` | Adresse d'expédition validée dans Brevo |
| `MAIL_FROM_NAME` | `Le Grand Cinéma` |
| `STRIPE_SECRET` | Clé secrète du mode test de Stripe, `sk_test_…` |
| `STRIPE_WEBHOOK_SECRET` | Secret de signature de la destination d'événements Stripe, `whsec_…` |

## Paiement en ligne (Stripe)

Le spectateur paie sur la page Stripe Checkout: l'API ne reçoit ni n'enregistre aucune donnée de carte, seulement l'identifiant de la session de paiement (table `paiements`). La préproduction utilise le mode test de Stripe: aucun paiement réel.

- Carte de test acceptée: `4242 4242 4242 4242`, une date d'expiration future, n'importe quel code à 3 chiffres.
- Carte de test refusée: `4000 0000 0000 0002`.

Le résultat du paiement arrive par deux chemins, traités par le même code et une seule fois:

1. au retour du spectateur, l'application web appelle `POST /api/reservations/{reservation}/paiement/verification`, qui interroge Stripe;
2. Stripe envoie une notification signée à `POST /api/paiements/stripe/notifications` (webhook).

Configuration, dans le tableau de bord Stripe en mode test:

1. Développeurs, Clés API: copier la clé secrète dans `STRIPE_SECRET`.
2. Paramètres, Moyens de paiement: garder seulement les cartes. La page Checkout propose les moyens activés ici, l'API Stripe n'accepte plus de les choisir à la création de la session.
3. Développeurs, Webhooks: ajouter une destination vers `https://grand-cinema-api.cleverapps.io/api/paiements/stripe/notifications` avec les événements `checkout.session.completed` et `checkout.session.expired`, puis copier son secret de signature dans `STRIPE_WEBHOOK_SECRET`.

En local, sans webhook, la vérification au retour suffit à confirmer la réservation. Pour recevoir aussi les notifications: `stripe listen --forward-to localhost:8000/api/paiements/stripe/notifications` (Stripe CLI), qui affiche le secret `whsec_…` à mettre dans `.env`.

## Billets électroniques et e-mails (Brevo)

Quand le paiement d'une réservation est accepté, le spectateur reçoit un e-mail avec un billet PDF par place. Il retrouve aussi ses billets dans « Mes billets » (`GET /api/billets`), et peut télécharger chaque PDF (`GET /api/billets/{billet}/pdf`).

- Le QR code d'un billet contient seulement son jeton: 48 caractères aléatoires, uniques, sans donnée personnelle.
- À l'entrée, l'agent d'accueil scanne le QR code (`POST /api/controle/seances/{seance}/billets`, le jour de la séance): un billet valide est aussitôt marqué utilisé, un second passage affiche l'heure du premier, un QR code inconnu ou d'une autre séance est refusé.
- Les PDF sont générés à la demande (dompdf, QR code par chillerlan/php-qrcode) et ne sont pas stockés.
- L'e-mail passe par la file d'attente (`QUEUE_CONNECTION=database`). Le planificateur la vide chaque minute (`queue:work --stop-when-empty`), sans processus permanent. Un envoi en échec est retenté deux fois, à une puis deux minutes d'intervalle: l'e-mail part en moins de 5 minutes.

Configuration de Brevo (offre gratuite, 300 e-mails par jour):

1. Expéditeurs, domaines et IP dédiées: ajouter et valider l'adresse d'expédition, à mettre dans `MAIL_FROM_ADDRESS`.
2. SMTP et API, onglet SMTP: copier l'identifiant dans `MAIL_USERNAME`, puis générer une clé SMTP et la mettre dans `MAIL_PASSWORD`.

En local, `MAIL_MAILER=log` écrit les e-mails dans `storage/logs/laravel.log`, et `php artisan queue:work` envoie ceux qui attendent dans la file.
