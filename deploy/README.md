# Déploiement Devis (VPS)

Même principe que Komkom : chaque push sur `main` construit une image Docker, la publie sur GHCR,
puis se connecte en SSH au VPS pour relancer le conteneur.

| Service  | Image                                 | Port local (VPS) | Domaine                     |
|----------|---------------------------------------|------------------|-----------------------------|
| backend  | `ghcr.io/app-niangdev/devis-backend`  | 127.0.0.1:8087   | backenddevis.niangdev.com   |
| frontend | `ghcr.io/app-niangdev/devis-frontend` | 127.0.0.1:8086   | devis.niangdev.com          |

PostgreSQL est le serveur partagé du réseau Docker externe `infrastructure_net`.
L'application Flutter appelle `https://backenddevis.niangdev.com/api/v1` (adresse fixée à la compilation).

## Secrets GitHub (dans les deux dépôts)

`VPS_HOST`, `VPS_USER`, `VPS_SSH_KEY`, `GHCR_USERNAME`, `GHCR_TOKEN` (jeton avec `read:packages`).

## Première installation

```bash
# 1. Fichiers sur le VPS : /opt/devis/docker-compose.yml (deploy/docker-compose.yml)
#    et /opt/devis/backend/.env (deploy/backend.env.example rempli)
chmod 600 /opt/devis/backend/.env

# 2. Base dans le PostgreSQL partagé (adapter le nom du conteneur postgres)
docker exec -it <conteneur-postgres> psql -U postgres -c "CREATE USER devis WITH PASSWORD '...';"
docker exec -it <conteneur-postgres> psql -U postgres -c "CREATE DATABASE devis OWNER devis;"

# 3. Clés (à coller dans backend/.env)
echo "<GHCR_TOKEN>" | docker login ghcr.io -u <GHCR_USERNAME> --password-stdin
docker run --rm --entrypoint php ghcr.io/app-niangdev/devis-backend:latest artisan key:generate --show  # APP_KEY
openssl rand -hex 32                                                                                  # APP_JWT_SECRET

# 4. Démarrage + base
cd /opt/devis && docker compose up -d
docker compose exec -u www-data backend php artisan migrate --force
docker compose exec -u www-data backend php artisan db:seed --class=RoleSeeder --force
docker compose exec -u www-data backend php artisan db:seed --class=MenuSeeder --force
docker compose exec -u www-data backend php artisan admin:create   # premier administrateur

# 5. HTTPS : certificats d'abord (les fichiers de site les référencent), un par domaine
sudo certbot certonly --nginx -d backenddevis.niangdev.com
sudo certbot certonly --nginx -d devis.niangdev.com
#    puis Nginx de l'hôte
sudo cp nginx-backenddevis.niangdev.com.conf /etc/nginx/sites-available/backenddevis.niangdev.com
sudo cp nginx-devis.niangdev.com.conf /etc/nginx/sites-available/devis.niangdev.com
sudo ln -s /etc/nginx/sites-available/backenddevis.niangdev.com /etc/nginx/sites-enabled/
sudo ln -s /etc/nginx/sites-available/devis.niangdev.com /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

Ne pas lancer `DatabaseSeeder` / `UserSeeder` en production : ils créent des comptes de démonstration.

## Exploitation

```bash
docker compose logs -f backend    # logs Laravel (LOG_CHANNEL=stderr)
docker compose up -d backend      # après une modification de backend/.env
```

Les logos sont dans le volume `backend_storage` : ils survivent aux déploiements.
