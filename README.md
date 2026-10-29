<div align="center">

# 🐳 TD2 Docker - Mini-projet conteneurisation & orchestration

**Un petit projet pour comprendre Docker et l'orchestration multi-conteneurs avec Docker Compose :**
une application PHP connectée à MariaDB, cloisonnée, sécurisée et supervisée.

![Docker](https://img.shields.io/badge/Docker-2496ED?style=for-the-badge&logo=docker&logoColor=white)
![Compose](https://img.shields.io/badge/Compose-v2-2496ED?style=for-the-badge&logo=docker&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-8.4-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MariaDB](https://img.shields.io/badge/MariaDB-11.4-003545?style=for-the-badge&logo=mariadb&logoColor=white)
<br>
![Telegraf](https://img.shields.io/badge/Telegraf-1.40-22ADF6?style=for-the-badge&logo=influxdb&logoColor=white)
![InfluxDB](https://img.shields.io/badge/InfluxDB-2.7-22ADF6?style=for-the-badge&logo=influxdb&logoColor=white)
![Grafana](https://img.shields.io/badge/Grafana-11.3-F46800?style=for-the-badge&logo=grafana&logoColor=white)

</div>

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

| Service | Port publié | Rôle |
|---|---|---|
| 🌐 `web` | `127.0.0.1:8080` | API JSON : `GET` lit, `POST` ajoute un événement |
| 🗄️ `db` | ❌ | MariaDB : base `td2`, healthcheck, volume `db_data` |
| 📡 `telegraf` | ❌ | Métriques, sonde HTTP, journaux |
| 💾 `influxdb` | ❌ | Stockage des métriques (7 jours) |
| 📊 `grafana` | `127.0.0.1:3000` | Dashboard « TD2 Supervision » |
| 🛡️ `socket-proxy` | ❌ | Accès **lecture seule** à l'API Docker |

---

## 🚀 Démarrage rapide

> **Prérequis :** Docker et Docker Compose v2 (Linux, macOS ou Windows avec WSL 2)

### 1️⃣ Créer les secrets (obligatoire)
Le dossier `secrets/` **n'est jamais versionné**. Crée-le en une commande :

```bash
mkdir -p secrets && for s in db_root_password db_app_password influx_admin_password grafana_admin_password; do
  openssl rand -hex 18 > secrets/$s; done && openssl rand -hex 32 > secrets/influx_token
```
> ⚠️ Garde les droits par défaut (`644`). En `600`, les conteneurs (`www-data`, `mysql`) ne pourraient pas lire leur secret.

### 2️⃣ Lancer l'application
```bash
docker compose up -d --build
docker compose ps        # attendre que db soit "healthy"
```
➡️ http://127.0.0.1:8080

### 3️⃣ Lancer la supervision (optionnel)
```bash
docker compose --profile monitoring up -d
```
➡️ http://127.0.0.1:3000, avec l'utilisateur `admin` et le mot de passe donné par `cat secrets/grafana_admin_password`

---

## ✅ Vérifier que tout fonctionne

| | Test | Commande | Attendu |
|---|---|---|---|
| 📖 | Lecture | `curl -s http://127.0.0.1:8080/` | JSON avec les événements |
| ✍️ | Écriture | `curl -i -X POST http://127.0.0.1:8080/ -H "Content-Type: application/json" -d '{"message":"test"}'` | `201 Created` |
| 🔒 | Base isolée | `docker compose ps db` | Aucun port publié |
| 💾 | Persistance | `docker compose down && docker compose up -d` | L'événement `test` est toujours là |
| 💥 | Panne | `docker compose stop db` | `503`, et « Panne » dans Grafana |
| 🔁 | Reprise | `docker compose start db` | De nouveau `200` |

---

## 🔒 Sécurité appliquée

- **Base isolée :** `db` est uniquement sur le réseau `backend` (`internal: true`), sans port publié.
- **Secrets :** montés en fichiers dans `/run/secrets/`. Ils ne sont ni dans l'image, ni dans `compose.yaml`, ni dans `docker inspect`.
- **Moindre privilège :** `web` tourne avec `cap_drop: ALL`, plus seulement `NET_BIND_SERVICE`, `SETUID` et `SETGID`, et avec `no-new-privileges`.
- **Socket Docker :** jamais exposé à Telegraf, qui passe par un proxy en lecture seule (`POST=0`).
- **Versions fixées :** toutes les images ont un tag explicite, jamais `latest`.

---

## 🧹 Arrêt

```bash
docker compose --profile monitoring down   # arrête tout, garde les données
docker compose down -v                     # ⚠️ supprime aussi les volumes (base comprise)
```

---

## 📁 Structure

```
├── app/              🐘 Dockerfile · .dockerignore · public/index.php
├── db/init/          🗄️ 01-init.sql (premier démarrage uniquement)
├── monitoring/       📊 telegraf.conf · provisioning et dashboard Grafana
├── compose.yaml      ⚙️ services · réseaux · volumes · secrets
└── reponses.md       📝 justifications techniques (Q8.1 → Q8.28)
```

---

## 🎯 Compétences mises en œuvre

| Domaine | Ce qui a été fait |
|---|---|
| 🏗️ **Images** | Dockerfile optimisé pour le cache, tags et versionnement, export avec `save`/`load` |
| ⚙️ **Orchestration** | Docker Compose multi-services, profils, dépendances `service_healthy` |
| 🌐 **Réseau** | Segmentation `frontend` / `backend` / `monitoring`, réseaux `internal`, DNS interne |
| 🔐 **Sécurité** | Secrets fichiers, capabilities minimales, `no-new-privileges`, proxy du socket Docker |
| 💾 **Données** | Volumes nommés, persistance après recréation, sauvegarde `mariadb-dump` |
| 📊 **Observabilité** | Métriques système et applicatives, journaux, test de panne et de reprise |

---

<div align="center">

👤 **Firas Bouricha** — Mastère 2 Expert Cloud, Sécurité & Infrastructure · Ynov Sophia

[![GitHub](https://img.shields.io/badge/GitHub-docker--secure--deployment-181717?style=for-the-badge&logo=github)](https://github.com/firas-bouricha/docker-secure-deployment)

</div>
