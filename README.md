# 🚗 CheckEngine

> Advanced OBD2 data analysis platform for vehicle diagnostics with focus on catalyst and oxygen sensor health monitoring.

[![CI Tests](https://github.com/gregzter/CheckEngine/workflows/CI%20-%20Tests%20%26%20Coverage/badge.svg)](https://github.com/gregzter/CheckEngine/actions/workflows/ci.yml)
[![Security](https://github.com/gregzter/CheckEngine/workflows/Security%20Analysis/badge.svg)](https://github.com/gregzter/CheckEngine/actions/workflows/security.yml)
[![Code Quality](https://github.com/gregzter/CheckEngine/workflows/Code%20Quality/badge.svg)](https://github.com/gregzter/CheckEngine/actions/workflows/code-quality.yml)
[![Coverage](.github/badges/jacoco.svg)](https://github.com/gregzter/CheckEngine/actions/workflows/ci.yml)
[![License](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)

[![Symfony](https://img.shields.io/badge/Symfony-7.3-black?logo=symfony)](https://symfony.com)
[![PHP](https://img.shields.io/badge/PHP-8.3-777BB4?logo=php)](https://php.net)
[![Python](https://img.shields.io/badge/Python-3.12-blue?logo=python)](https://python.org)
[![PostgreSQL](https://img.shields.io/badge/PostgreSQL-17-blue?logo=postgresql)](https://postgresql.org)
[![Vue.js](https://img.shields.io/badge/Vue.js-3-green?logo=vue.js)](https://vuejs.org)
[![Docker](https://img.shields.io/badge/Docker-Compose-2496ED?logo=docker)](https://docker.com)

## 📋 Table des matières

- [Architecture](#architecture)
- [Prérequis](#prérequis)
- [Installation](#installation)
- [Utilisation](#utilisation)
- [Structure du projet](#structure-du-projet)
- [API](#api)
- [Développement](#développement)

---

## 🏗️ Architecture

### Vue d'ensemble

```
┌──────────────┐     ┌──────────────┐     ┌──────────────┐
│   Frontend   │────▶│   Symfony    │────▶│  PostgreSQL  │
│   (Vue.js)   │     │   Backend    │     │      17      │
└──────────────┘     └──────┬───────┘     └──────────────┘
                             │
                             ▼
                     ┌──────────────┐
                     │  Python API  │
                     │  (Analytics) │
                     └──────────────┘
```

### Stack technique

**Backend Principal (Symfony 7.3)**
- Framework: Symfony 7.3 + API Platform
- ORM: Doctrine
- Langage: PHP 8.3
- Rôle: Upload fichiers, gestion BDD, API REST, authentification

**Backend Analytics (Python 3.12)**
- Framework: FastAPI
- Bibliothèques: Pandas, NumPy, SciPy
- Rôle: Analyse scientifique des données OBD2, calculs d'efficacité catalyseur

**Frontend (Vue.js 3)**
- Framework: Vue.js 3 + TypeScript
- UI: Tailwind CSS
- Graphiques: Chart.js / Apache ECharts
- Build: Vite

**Base de données**
- PostgreSQL 17
- Extensions: uuid-ossp, pg_trgm

**DevOps**
- Docker + Docker Compose
- DevContainer (VS Code)

---

## 🛠️ Prérequis

- **Docker Desktop** (Windows/Mac) ou **Docker Engine** (Linux)
- **VS Code** avec extension **Dev Containers**
- **Git**
- Minimum 4 GB RAM disponible pour Docker

---

## 🚀 Installation

### 1. Cloner le repository

```bash
git clone https://github.com/gregzter/CheckEngine.git
cd CheckEngine
```

### 2. Ouvrir dans DevContainer (Recommandé)

**Dans VS Code :**
1. Installer l'extension "Dev Containers"
2. `Ctrl+Shift+P` → "Dev Containers: Reopen in Container"
3. Attendre que le container se construise (~5-10 min la première fois)

**Le DevContainer configure automatiquement :**
- ✅ Symfony 7.3
- ✅ Python 3.12 (avec environnement virtuel)
- ✅ PostgreSQL 17
- ✅ Toutes les extensions VS Code nécessaires

> 📘 **Note Python** : Un environnement virtuel est créé automatiquement dans `/workspace/backend-python/venv`.  
> Voir [PYTHON_VENV_SETUP.md](PYTHON_VENV_SETUP.md) pour plus de détails.

### 3. OU Installation manuelle

```bash
# Démarrer tous les services
docker-compose up -d

# Installer dépendances Symfony
docker-compose exec symfony composer install

# Créer la base de données
docker-compose exec symfony php bin/console doctrine:database:create
docker-compose exec symfony php bin/console doctrine:migrations:migrate

# Installer dépendances Python
docker-compose exec python-api pip install -r requirements.txt

# OU en dev container avec venv (recommandé)
# cd /workspace/backend-python && source venv/bin/activate && pip install -r requirements.txt

# Installer dépendances Frontend
docker-compose exec frontend pnpm install
```

---

## 📱 Utilisation

### Accès aux services

| Service | URL | Description |
|---------|-----|-------------|
| **Frontend** | http://localhost:5173 | Interface utilisateur |
| **Symfony API** | http://localhost:8000 | API REST principale |
| **Python API** | http://localhost:8001 | API d'analyse |
| **Adminer** | http://localhost:8080 | Interface BDD |
| **PostgreSQL** | localhost:5432 | Base de données |

### Credentials par défaut

**PostgreSQL:**
- User: `postgres`
- Password: `postgres`
- Database: `prius_diagnostics`

### Upload d'un log Torque

1. Ouvrir http://localhost:5173
2. Drag & drop d'un fichier `.zip` contenant les logs CSV
3. L'application extrait automatiquement et analyse les données
4. Visualisation des résultats en temps réel

---

## 📂 Structure du projet

```
check-engine/
├── .devcontainer/          # Configuration DevContainer
│   └── devcontainer.json
├── backend-symfony/        # Backend principal (Symfony)
│   ├── src/
│   │   ├── Controller/     # Endpoints API
│   │   ├── Entity/         # Modèles Doctrine
│   │   ├── Service/        # Logique métier
│   │   └── Repository/     # Requêtes BDD
│   ├── config/
│   └── composer.json
├── backend-python/         # API Analytics (Python)
│   ├── main.py             # FastAPI app
│   ├── services/           # Services d'analyse
│   │   ├── parser.py       # Parsing CSV
│   │   ├── analyzer.py     # Calculs métriques
│   │   └── catalyst.py     # Efficacité catalyseur
│   └── requirements.txt
├── frontend/               # Interface Vue.js
│   ├── src/
│   │   ├── components/     # Composants réutilisables
│   │   ├── views/          # Pages
│   │   ├── stores/         # State management (Pinia)
│   │   └── composables/    # Logique réutilisable
│   └── package.json
├── docker/                 # Dockerfiles
│   ├── symfony/
│   ├── python/
│   ├── frontend/
│   └── postgres/
│       └── init.sql        # Schéma BDD
├── docker-compose.yml      # Orchestration services
└── README.md
```

---

## 🔌 API

### Symfony API (REST)

**Upload d'un log**
```http
POST /api/trips/upload
Content-Type: multipart/form-data

{
  "logfile": <zip_file>
}
```

**Lister les trajets**
```http
GET /api/trips
```

**Détails d'un trajet**
```http
GET /api/trips/{id}
```

**Statistiques long terme**
```http
GET /api/statistics?from=2025-01-01&to=2025-10-23
```

### Python API (Analytics)

**Analyser des données CSV**
```http
POST /analyze
Content-Type: application/json

{
  "trip_id": 123,
  "data": [...]
}
```

**Calculer efficacité catalyseur**
```http
POST /catalyst/efficiency
```

---

## 🧪 Développement

### Commandes utiles

**Symfony**
```bash
# Créer une entité
docker-compose exec symfony php bin/console make:entity

# Créer une migration
docker-compose exec symfony php bin/console make:migration

# Exécuter les migrations
docker-compose exec symfony php bin/console doctrine:migrations:migrate

# Créer un controller
docker-compose exec symfony php bin/console make:controller
```

**Python**
```bash
# Lancer les tests
docker-compose exec python-api pytest

# Formatter le code
docker-compose exec python-api black .

# Type checking
docker-compose exec python-api mypy .
```

**Frontend**
```bash
# Build de production
docker-compose exec frontend pnpm build

# Linter
docker-compose exec frontend pnpm lint

# Tests
docker-compose exec frontend pnpm test
```

### Architecture des données

**Flow d'analyse :**
1. Upload ZIP → Symfony
2. Extraction CSV → Symfony
3. Stockage raw data → PostgreSQL
4. Appel Python API → Analyse scientifique
5. Stockage métriques → PostgreSQL
6. Affichage → Frontend

### Métriques calculées

- **Efficacité catalyseur** : Basée sur oscillations sondes O2
- **Score santé** : Agrégation pondérée de multiples indicateurs
- **Fuel Trims** : STFT/LTFT avec détection d'anomalies
- **Tendances long terme** : Régression linéaire sur historique

---

## 📊 Fonctionnalités

### ✅ Implémentées (Phase 1)

- [x] Upload automatique fichiers ZIP
- [x] Parsing CSV avec filtrage colonnes nulles
- [x] Calcul efficacité catalyseur
- [x] Dashboard trajet simple
- [x] Base de données robuste
- [x] DevContainer configuration

### 🚧 En cours (Phase 2)

- [ ] Graphiques interactifs complets
- [ ] Analyse long terme multi-trajets
- [ ] Export PDF rapports
- [ ] Comparaison trajets

### 🔮 Futur (Phase 3+)

- [ ] Intégration IA (Claude) pour insights textuels
- [ ] PWA (application mobile)
- [ ] Notifications alertes
- [ ] Multi-véhicules

---

## 🤝 Contribution

Ce projet est personnel mais les suggestions sont bienvenues via issues/PR.

---

## 📝 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

**You are free to:**
- ✅ Use commercially
- ✅ Modify
- ✅ Distribute
- ✅ Private use

**Conditions:**
- 📄 Include original license and copyright notice

---

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

1. Fork the project
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

---

## 🚗 About

Originally developed for monitoring a **Toyota Prius+ (Prius V) 2012** with 290,000 km, CheckEngine is designed to work with any OBD2-compatible vehicle.

**Goal:** Monitor catalyst and oxygen sensor health over time, detect issues early (like P0420), and provide actionable insights before problems become critical.

### Why CheckEngine?

- 📊 **Data-driven diagnostics** - Make decisions based on real data, not guesses
- 📈 **Long-term tracking** - See degradation trends over months/years
- 💰 **Save money** - Avoid unnecessary part replacements
- 🔧 **DIY-friendly** - Take control of your vehicle diagnostics
- 🌍 **Open source** - Free for everyone, forever

---

**Made with ❤️ and lots of OBD2 data**
