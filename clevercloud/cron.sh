#!/bin/bash -l
# Lancé chaque minute sur chaque instance par clevercloud/cron.json: le planificateur de
# Laravel exécute les tâches de routes/console.php, chacune sur une seule instance.
set -euo pipefail
cd "$APP_HOME"
php artisan schedule:run
