# 🐳 TD2 Docker — Application PHP / MariaDB conteneurisée et supervisée

Application de suivi d'événements (**Apache/PHP + MariaDB**) déployée avec **Docker Compose** :
réseaux cloisonnés, secrets externes, healthcheck, persistance et supervision **Telegraf / InfluxDB / Grafana**.

> Ynov — Mastère 2 Expert Cloud, Sécurité & Infrastructure · Firas Bouricha

---

## 🗺️ Architecture

```
 Navigateur ──► 127.0.0.1:8080 ──► web (PHP/Apache)      127.0.0.1:3000 ──► grafana
                                     │  frontend                                │
                                     │  backend (interne)          monitoring (interne)
                                     ▼                                          │
                               db (MariaDB 11.4)       telegraf ──► influxdb ◄──┘
                               aucun port publié          │
                               volume db_data       socket-proxy (lecture seule) ──► docker.sock
```

| Service | Image | Port publié | Rôle |
|---|---|---|---|
| `web` | `ynov/td2-web:1.0` (Dockerfile) | `127.0.0.1:8080` | API JSON : `GET` lit, `POST` ajoute un événement |
| `db` | `mariadb:11.4` | ❌ aucun | Base `td2`, healthcheck, volume `db_data` |
| `telegraf` | `telegraf:1.40.2` | ❌ | Métriques, sonde HTTP, journaux |
| `influxdb` | `influxdb:2.7` | ❌ | Stockage des métriques (7 jours) |
| `grafana` | `grafana/grafana:11.3.0` | `127.0.0.1:3000` | Dashboard « TD2 Supervision » |
| `socket-proxy` | `tecnativa/docker-socket-proxy:v0.5.0` | ❌ | Accès **lecture seule** à l'API Docker |

**🔒 Sécurité appliquée**
- `db` est uniquement sur le réseau `backend` (interne) : inaccessible depuis l'hôte.
- Les mots de passe sont des **secrets fichiers** (`/run/secrets/`) : ils ne sont ni dans l'image, ni dans `compose.yaml`, ni dans `docker inspect`.
- `web` tourne avec `cap_drop: ALL`, plus seulement `NET_BIND_SERVICE`, `SETUID` et `SETGID`, et avec `no-new-privileges`.
- Telegraf n'a jamais le socket Docker : il passe par `socket-proxy` (`POST=0`).

---

## 🚀 Démarrage rapide

**Prérequis :** Docker et Docker Compose v2 (Linux, macOS ou Windows avec WSL 2).

### 1. Créer les secrets ⚠️ obligatoire
Le dossier `secrets/` **n'est pas fourni** (exclu du rendu et de Git). Crée-le en une commande :

```bash
mkdir -p secrets && for s in db_root_password db_app_password influx_admin_password grafana_admin_password; do
  openssl rand -hex 18 > secrets/$s; done && openssl rand -hex 32 > secrets/influx_token
```

> Garde les droits par défaut (`644`). En `600`, les utilisateurs des conteneurs (`www-data`, `mysql`) ne pourraient pas lire leur secret.

### 2. Lancer l'application
```bash
docker compose up -d --build
docker compose ps          # attendre que db soit "healthy"
```

### 3. Lancer la supervision (optionnel)
```bash
docker compose --profile monitoring up -d
```
Ouvre http://127.0.0.1:3000 avec l'utilisateur `admin` et le mot de passe donné par `cat secrets/grafana_admin_password`.
La source InfluxDB et le dashboard sont chargés automatiquement (provisioning).

---

## ✅ Vérifier que tout fonctionne

| Test | Commande | Attendu |
|---|---|---|
| Lecture | `curl -s http://127.0.0.1:8080/` | JSON avec 3 événements |
| Écriture | `curl -i -X POST http://127.0.0.1:8080/ -H "Content-Type: application/json" -d '{"message":"test"}'` | `201 Created` |
| Base isolée | `docker compose port db 3306` | Aucun port publié |
| Persistance | `docker compose down && docker compose up -d` puis `curl` | L'événement `test` est toujours là |
| Panne | `docker compose stop db` puis `curl -i …` | `503`, et « Panne » dans Grafana |
| Reprise | `docker compose start db` | De nouveau `200` |

---

## 💾 Sauvegarde de la base
```bash
docker compose exec db sh -c 'mariadb-dump -uroot -p"$(cat /run/secrets/db_root_password)" td2' > backup_td2.sql
```

---

## 🧹 Arrêt
```bash
docker compose --profile monitoring down   # garde les données
docker compose down -v                     # ⚠️ supprime aussi les volumes (base comprise)
```

---

## 📁 Contenu du projet
```
app/            Dockerfile, .dockerignore, public/index.php
db/init/        01-init.sql (exécuté au premier démarrage uniquement)
monitoring/     telegraf.conf, provisioning et dashboard Grafana
compose.yaml    Services, réseaux, volumes, secrets
reponses.md     Justifications techniques (Q8.1 à Q8.28)
```