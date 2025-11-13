# Backend Python - API Analytics

API d'analyse de données OBD-II construite avec FastAPI.

## 🐍 Environnement Python

Ce projet utilise Python 3.12 avec un environnement virtuel.

### En Dev Container

L'environnement virtuel est créé automatiquement dans `/workspace/backend-python/venv` lors de la création du conteneur.

Pour utiliser Python dans le dev container :

```bash
# Activer l'environnement virtuel
cd /workspace/backend-python
source venv/bin/activate

# Installer/mettre à jour les dépendances
pip install -r requirements.txt

# Lancer l'API
uvicorn main:app --reload --host 0.0.0.0 --port 8001

# Désactiver l'environnement
deactivate
```

### Avec Docker Compose

Si vous utilisez Docker Compose au lieu du dev container :

```bash
# Installer les dépendances
docker-compose exec python-api pip install -r requirements.txt

# Lancer l'API
docker-compose up python-api
```

## 📦 Dépendances

Les principales dépendances sont :
- **FastAPI** : Framework web moderne et performant
- **Uvicorn** : Serveur ASGI
- **SQLAlchemy** : ORM pour PostgreSQL/TimescaleDB
- **Pandas & NumPy** : Analyse de données
- **Pydantic** : Validation des données

Voir `requirements.txt` pour la liste complète.

## 🚀 Développement

```bash
# Activer le venv
source venv/bin/activate

# Lancer en mode développement (avec rechargement automatique)
uvicorn main:app --reload --host 0.0.0.0 --port 8001

# Accéder à la documentation Swagger
# http://localhost:8001/docs
```

## 🧪 Tests

```bash
# Activer le venv
source venv/bin/activate

# Installer pytest si nécessaire
pip install pytest pytest-asyncio

# Lancer les tests
pytest
```

## 📝 Notes

- L'environnement virtuel (`venv/`) est **ignoré par Git**
- Les paquets sont installés localement dans le venv, pas globalement
- Toujours activer le venv avant d'exécuter du code Python
