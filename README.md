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
