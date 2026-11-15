# Grand Cinéma – API

API de réservation du Grand Cinéma (bloc 3 du Bachelor Développeur d'application Web, Studi).
Elle sert l'application web [grand-cinema-web](https://github.com/acoclez/grand-cinema-web).

## Pile technique

- PHP 8.5, Laravel 13, Eloquent ORM, MySQL 8.
- Authentification par jetons JWT (`php-open-source-saver/jwt-auth`).
- Documentation OpenAPI générée par Scramble, consultable sur `/docs/api`.
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
| `FRONTEND_URL` | Adresse de l'application web, autorisée par CORS et utilisée dans les e-mails |
| `JWT_SECRET` | Clé générée par `php artisan jwt:secret --show` |
| `DB_CONNECTION` | `mysql` |
| `LOG_CHANNEL` | `stderr`, pour lire les journaux dans la console Clever Cloud |
| `QUEUE_CONNECTION` | `sync` |
| `MAIL_MAILER` | `log`: en démonstration, les e-mails sont écrits dans les journaux |
