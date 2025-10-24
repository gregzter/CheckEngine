.PHONY: help install up down restart logs clean build test

# Colors
GREEN  := $(shell tput -Txterm setaf 2)
YELLOW := $(shell tput -Txterm setaf 3)
RESET  := $(shell tput -Txterm sgr0)

help: ## Show this help
	@echo '${GREEN}CheckEngine - Development Commands${RESET}'
	@echo ''
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "  ${YELLOW}%-15s${RESET} %s\n", $$1, $$2}'
	@echo ''

install: ## Install all dependencies
	@echo "${GREEN}📦 Installing dependencies...${RESET}"
	docker-compose run --rm symfony composer install
	docker-compose run --rm python-api pip install -r requirements.txt
	docker-compose run --rm frontend pnpm install
	@echo "${GREEN}✅ Installation complete!${RESET}"

up: ## Start all containers
	@echo "${GREEN}🚀 Starting containers...${RESET}"
	docker-compose up -d
	@echo "${GREEN}✅ All services running!${RESET}"
	@echo "Frontend:     http://localhost:5173"
	@echo "Symfony:      http://localhost:8000"
	@echo "Python API:   http://localhost:8001"
	@echo "Adminer:      http://localhost:8080"

down: ## Stop all containers
	@echo "${YELLOW}⏹️  Stopping containers...${RESET}"
	docker-compose down

restart: down up ## Restart all containers

logs: ## Show logs from all containers
	docker-compose logs -f

logs-symfony: ## Show Symfony logs
	docker-compose logs -f symfony

logs-python: ## Show Python API logs
	docker-compose logs -f python-api

logs-frontend: ## Show Frontend logs
	docker-compose logs -f frontend

build: ## Rebuild all containers
	@echo "${GREEN}🔨 Building containers...${RESET}"
	docker-compose build --no-cache
	@echo "${GREEN}✅ Build complete!${RESET}"

clean: ## Remove all containers, volumes and images
	@echo "${YELLOW}🧹 Cleaning up...${RESET}"
	docker-compose down -v --rmi all
	@echo "${GREEN}✅ Cleanup complete!${RESET}"

db-migrate: ## Run Symfony database migrations
	docker-compose exec symfony php bin/console doctrine:migrations:migrate --no-interaction

db-reset: ## Reset database (⚠️  DESTRUCTIVE)
	@echo "${YELLOW}⚠️  This will DELETE all data!${RESET}"
	docker-compose exec symfony php bin/console doctrine:database:drop --force --if-exists
	docker-compose exec symfony php bin/console doctrine:database:create
	docker-compose exec symfony php bin/console doctrine:migrations:migrate --no-interaction

shell-symfony: ## Open Symfony container shell
	docker-compose exec symfony sh

shell-python: ## Open Python API container shell
	docker-compose exec python-api bash

shell-db: ## Open PostgreSQL shell
	docker-compose exec postgres psql -U postgres -d checkengine

test-symfony: ## Run Symfony tests
	docker-compose exec symfony php bin/phpunit

test-python: ## Run Python tests
	docker-compose exec python-api pytest

test: test-symfony test-python ## Run all tests

coverage: ## Generate code coverage report (HTML + XML)
	@echo "${GREEN}📊 Generating code coverage...${RESET}"
	docker-compose exec symfony php -d pcov.enabled=1 -d pcov.directory=. vendor/bin/phpunit --coverage-text --coverage-clover=coverage/clover.xml --coverage-html=coverage/html
	@echo "${GREEN}✅ Coverage report generated!${RESET}"
	@echo "HTML Report: backend-symfony/coverage/html/index.html"
	@echo "XML Report:  backend-symfony/coverage/clover.xml"

coverage-text: ## Show code coverage in terminal
	@echo "${GREEN}📊 Generating code coverage (text only)...${RESET}"
	docker-compose exec symfony php -d pcov.enabled=1 -d pcov.directory=. vendor/bin/phpunit --coverage-text

coverage-html: ## Generate HTML coverage report only
	@echo "${GREEN}📊 Generating HTML coverage report...${RESET}"
	docker-compose exec symfony php -d pcov.enabled=1 -d pcov.directory=. vendor/bin/phpunit --coverage-html=coverage/html
	@echo "${GREEN}✅ HTML report generated: backend-symfony/coverage/html/index.html${RESET}"

format-python: ## Format Python code
	docker-compose exec python-api black .
	docker-compose exec python-api isort .

lint-frontend: ## Lint frontend code
	docker-compose exec frontend pnpm lint

status: ## Show status of all services
	@docker-compose ps
