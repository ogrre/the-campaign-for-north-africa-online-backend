# Backend

API Laravel 13 de fondation multijoueur. Elle fournit l'authentification Sanctum par jeton, les scénarios de référence, le lobby, les équipes et rôles, les invitations, le démarrage minimal, les décisions en attente et le journal d'audit. Aucun moteur de règles CNA détaillé n'est implémenté.

Copier `.env.example` vers `.env`, puis lancer `docker compose up --build`. L'API répond sur `http://localhost:8080/api/health`; elle renvoie `200` si PostgreSQL et Redis répondent, sinon `503` avec le détail.

Après le premier démarrage, exécuter `docker compose exec app php artisan migrate --seed`. Commandes locales : `php artisan test`, `vendor/bin/pint --test`, `php -d memory_limit=512M vendor/bin/phpstan analyse`.

Les routes sont sous `/api`. Les mutations portant sur une partie existante exigent sa `revision`; une révision périmée renvoie `409 Conflict` avec la révision courante.

L'image de production est la cible `production` du `Dockerfile`. Elle ne dépend pas de `.env`, active OPcache et Apache rewrite. Exécuter `php artisan migrate --force` explicitement dans l'étape de release Dokploy; aucune migration n'est lancée automatiquement au démarrage.
