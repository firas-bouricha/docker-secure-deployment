# docker-secure-deployment# Docker Secure Deployment

Déploiement sécurisé d'une application web PHP et de sa base de données MariaDB avec Docker Compose, en isolant la base de données sur un réseau interne et en gérant les mots de passe par fichiers secrets.

## Objectifs

- Déployer une stack à deux services (application web + base de données) avec Docker Compose.
- Ne jamais écrire de mot de passe dans les fichiers de configuration : utiliser les **secrets Docker**.
- Isoler la base de données : elle n'est accessible que par l'application, et jamais depuis l'hôte ni depuis Internet.
- Appliquer le **principe du moindre privilège** : chaque service ne reçoit que les secrets dont il a besoin.

## Architecture

```
          Navigateur (hôte)
                 │
        127.0.0.1:8080 (uniquement local)
                 │
          ┌──────▼──────┐
          │     web     │
          └──┬───────┬──┘
   réseau    │       │   réseau
  frontend   │       │  backend (internal)
             │       │
          ┌──▼───────▼──┐
          │     db      │   MariaDB 11.4
          └─────────────┘   aucun port publié
```

| Service | Image | Réseaux | Port publié | Secrets |
|---|---|---|---|---|
| `web` | construite depuis `app/` | `frontend`, `backend` | `127.0.0.1:8080` → 80 | `db_app_password` |
| `db` | `mariadb:11.4` | `backend` | aucun | `db_root_password`, `db_app_password` |

### Pourquoi deux réseaux ?

- **`frontend`** : réseau classique, relié à l'hôte pour publier l'application.
- **`backend`** : marqué `internal: true`. Les conteneurs qui n'y sont connectés que ne peuvent ni sortir vers Internet, ni être joints depuis l'hôte. La base reste donc inaccessible de l'extérieur.

Le service `web` relie les deux réseaux : il reçoit les requêtes des utilisateurs et communique avec la base.

### Pourquoi des secrets plutôt que des variables d'environnement ?

Une variable d'environnement (`MARIADB_PASSWORD=...`) est visible avec `docker inspect` et apparaît souvent dans les logs. Un secret est monté sous forme de fichier dans `/run/secrets/` : le mot de passe n'apparaît ni dans le fichier `compose.yaml`, ni dans l'historique des commandes.

## Structure du projet

```
.
├── compose.yaml          # Définition des services, réseaux, volumes et secrets
├── app/
│   ├── Dockerfile        # Construction de l'image de l'application web
│   ├── .dockerignore     # Fichiers exclus du contexte de build
│   └── public/
│       └── index.php     # Point d'entrée de l'application
├── db/
│   └── init/
│       └── 01-init.sql   # Script exécuté au premier démarrage de la base
├── secrets/
│   ├── db_root_password.example
│   └── db_app_password.example
└── .gitignore
```

## Prérequis

- Docker et Docker Compose (v2)
- Linux, macOS, ou Windows avec WSL 2

## Installation

**1. Cloner le dépôt**

```bash
git clone https://github.com/firas-bouricha/docker-secure-deployment.git
cd docker-secure-deployment
```

**2. Créer les fichiers de secrets**

Les vrais mots de passe ne sont jamais versionnés. Copie les modèles puis remplace leur contenu :

```bash
cp secrets/db_root_password.example secrets/db_root_password
cp secrets/db_app_password.example secrets/db_app_password
```

Choisis des mots de passe robustes, par exemple avec `openssl rand -base64 24`.

**3. Lancer la stack**

```bash
docker compose up -d --build
```

**4. Accéder à l'application**

Ouvre [http://127.0.0.1:8080](http://127.0.0.1:8080) dans ton navigateur.

## Vérifications

**Les services tournent :**

```bash
docker compose ps
```

**Le réseau backend est bien interne :**

```bash
docker network inspect docker-secure-deployment_backend \
  --format '{{range .Containers}}{{.Name}} {{end}}'
```

Le résultat doit lister `db` et `web`, et seulement eux.

**La base n'est pas joignable depuis l'hôte :**

```bash
nc -zv 127.0.0.1 3306   # doit échouer : aucun port publié
```

## Arrêt et nettoyage

```bash
docker compose down        # arrête et supprime les conteneurs et réseaux
docker compose down -v     # supprime aussi le volume de données (perte des données)
```

## Sécurité : ce qui est versionné et ce qui ne l'est pas

| Élément | Versionné | Raison |
|---|---|---|
| `secrets/*.example` | Oui | Modèles sans valeur réelle |
| `secrets/*` (mots de passe réels) | Non | Exclus par `.gitignore` |
| `.env` | Non | Peut contenir des valeurs sensibles |

Si un mot de passe réel a été versionné par erreur, il doit être **considéré comme compromis** : il faut le changer. Le supprimer dans un commit ultérieur ne suffit pas, il reste dans l'historique Git.

## Compétences mises en œuvre

- Orchestration de conteneurs avec Docker Compose
- Segmentation réseau (réseaux `bridge`, réseau `internal`)
- Gestion des secrets sans les exposer dans le code ou les variables d'environnement
- Principe du moindre privilège sur les services
- Initialisation automatique d'une base de données au premier démarrage

## Auteur

Firas Bouricha, étudiant en Mastère 1 Cloud, Security & Infrastructure (Ynov Sophia).