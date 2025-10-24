#!/bin/bash
set -e

echo "🔧 Fixing permissions..."
sudo chown -R www-data:www-data /workspace/backend-symfony/vendor 2>/dev/null || true
sudo chown -R www-data:www-data /workspace/backend-symfony/var 2>/dev/null || true

echo "� Installing PCOV for code coverage..."
sudo apk add --no-cache pcre-dev $PHPIZE_DEPS 2>/dev/null || true
sudo pecl install pcov 2>/dev/null || true
sudo docker-php-ext-enable pcov 2>/dev/null || true

echo "�🔐 Configuring Git safe directory..."
git config --global --add safe.directory /workspace
git config --global --add safe.directory '*'

echo "📦 Installing Composer dependencies..."
cd /workspace/backend-symfony && composer install --no-interaction --prefer-dist

echo "🐍 Setting up Python virtual environment..."
cd /workspace/backend-python
if [ ! -d "venv" ]; then
    python3 -m venv venv
fi
source venv/bin/activate
pip install --upgrade pip
pip install -r requirements.txt
deactivate

echo "✅ DevContainer ready! You can now run: make install"
