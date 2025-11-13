#!/bin/bash
set -e

echo "🔧 Fixing permissions..."
sudo chown -R www-data:www-data /workspace/backend-symfony/vendor 2>/dev/null || true
sudo chown -R www-data:www-data /workspace/backend-symfony/var 2>/dev/null || true

echo "🔐 Configuring Git safe directory..."
git config --global --add safe.directory /workspace
git config --global --add safe.directory '*'

echo "📦 Installing Composer dependencies..."
cd /workspace/backend-symfony && composer install --no-interaction --prefer-dist

echo "🐍 Installing Python dependencies..."
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
echo "  ✓ Python venv created at /workspace/backend-python/venv"

echo "✅ DevContainer ready! You can now run: make install"
