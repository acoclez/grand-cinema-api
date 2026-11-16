#!/bin/bash
# Lancé par Clever Cloud après chaque build (CC_POST_BUILD_HOOK), avant que la nouvelle
# version ne remplace la précédente: migrations, puis données de démonstration.
# Les seeders ne créent que ce qui manque: les relancer ne crée aucun doublon.
set -euo pipefail
php artisan migrate --force
php artisan db:seed --force
php artisan config:cache
php artisan route:cache
