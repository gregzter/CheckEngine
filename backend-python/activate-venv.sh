#!/bin/bash
# Script pour activer l'environnement virtuel Python

VENV_PATH="/workspace/backend-python/venv"

if [ ! -d "$VENV_PATH" ]; then
    echo "❌ Environnement virtuel non trouvé à: $VENV_PATH"
    echo "🔧 Création de l'environnement virtuel..."
    cd /workspace/backend-python
    python3 -m venv venv
    source venv/bin/activate
    pip install --upgrade pip
    pip install -r requirements.txt
    echo "✅ Environnement virtuel créé et activé !"
else
    source "$VENV_PATH/bin/activate"
    echo "✅ Environnement virtuel activé !"
    echo "📍 Python: $(which python)"
    echo "📦 Version: $(python --version)"
    echo ""
    echo "💡 Pour désactiver: deactivate"
fi
