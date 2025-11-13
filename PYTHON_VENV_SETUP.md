# 🐍 Configuration Python - Environnement Virtuel

## 📋 Résumé des modifications

Le script `post-create.sh` a été mis à jour pour utiliser un environnement virtuel Python au lieu d'installer les paquets avec `pip install --user`.

### Pourquoi ce changement ?

Alpine Linux 3.22 implémente **PEP 668** qui empêche l'installation de paquets Python directement dans l'environnement système pour éviter les conflits avec les paquets gérés par le gestionnaire de paquets du système (`apk`).

## ✅ Solution implémentée

### 1. Script `post-create.sh` modifié

Le script crée maintenant automatiquement un environnement virtuel dans `/workspace/backend-python/venv` :

```bash
# Ancien code (qui causait l'erreur)
pip install --user -r /workspace/backend-python/requirements.txt

# Nouveau code
cd /workspace/backend-python
if [ ! -d "venv" ]; then
    echo "  Creating Python virtual environment..."
    python3 -m venv venv
fi
echo "  Activating virtual environment and installing packages..."
source venv/bin/activate
pip install --upgrade pip
pip install -r requirements.txt
deactivate
```

### 2. Documentation ajoutée

Un nouveau fichier `backend-python/README.md` a été créé avec :
- Instructions pour activer/désactiver le venv
- Guide de développement
- Commandes courantes

### 3. `.gitignore` déjà configuré

Le dossier `venv/` était déjà ignoré par Git, aucune modification nécessaire.

## 🚀 Utilisation

### Dans le Dev Container

Après la création du conteneur, l'environnement virtuel est prêt :

```bash
# Se placer dans le dossier backend-python
cd /workspace/backend-python

# Activer l'environnement virtuel
source venv/bin/activate

# Votre terminal affichera maintenant (venv) au début de la ligne

# Lancer l'API FastAPI
uvicorn main:app --reload --host 0.0.0.0 --port 8001

# Installer de nouvelles dépendances
pip install nouveau-paquet

# Désactiver le venv quand vous avez terminé
deactivate
```

### Avec Docker Compose

Si vous utilisez Docker Compose, les commandes restent les mêmes :

```bash
docker-compose exec python-api pip install -r requirements.txt
docker-compose up python-api
```

## 📦 Paquets installés

Tous les paquets de `requirements.txt` sont installés dans le venv :

- **FastAPI 0.109.0** - Framework web
- **Uvicorn 0.27.0** - Serveur ASGI
- **SQLAlchemy 2.0.25** - ORM
- **Pandas 2.2.0** - Analyse de données
- **NumPy 1.26.3** - Calculs numériques
- **Scipy 1.12.0** - Fonctions scientifiques
- Et toutes les autres dépendances...

## ✨ Avantages

✅ **Isolation** : Les paquets Python sont isolés du système  
✅ **Reproductibilité** : Environnement identique pour tous les développeurs  
✅ **Sécurité** : Pas de risque de conflit avec les paquets système  
✅ **Flexibilité** : Installation/désinstallation facile de paquets  

## 🔍 Vérification

Pour vérifier que tout fonctionne :

```bash
# Activer le venv
source /workspace/backend-python/venv/bin/activate

# Vérifier la version Python
python --version
# Devrait afficher: Python 3.12.x

# Lister les paquets installés
pip list

# Vérifier que FastAPI est disponible
python -c "import fastapi; print(f'FastAPI {fastapi.__version__}')"
# Devrait afficher: FastAPI 0.109.0
```

## 📝 Notes importantes

- Le dossier `venv/` est **ignoré par Git** (`.gitignore`)
- Le venv est **créé automatiquement** lors de la création du dev container
- **Toujours activer le venv** avant d'exécuter du code Python en mode développement
- En mode Docker Compose, le venv n'est pas nécessaire (isolation par conteneur)

## 🐛 Résolution de problèmes

### Le venv n'existe pas

```bash
cd /workspace/backend-python
python3 -m venv venv
source venv/bin/activate
pip install -r requirements.txt
```

### Erreur "externally-managed-environment"

Si vous voyez encore cette erreur, c'est que vous essayez d'utiliser `pip` en dehors du venv. Solution :

```bash
source /workspace/backend-python/venv/bin/activate
```

### Réinitialiser le venv

```bash
cd /workspace/backend-python
rm -rf venv
python3 -m venv venv
source venv/bin/activate
pip install --upgrade pip
pip install -r requirements.txt
```

---

**Date de mise à jour** : 13 novembre 2025  
**Statut** : ✅ Fonctionnel et testé
