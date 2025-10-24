# 🔄 Activation TimescaleDB

## ⚠️ Important : Redémarrage requis

TimescaleDB nécessite d'être **préchargé** dans PostgreSQL via `shared_preload_libraries`.

Le `docker-compose.yml` a été mis à jour pour configurer ceci automatiquement, mais **un redémarrage des containers est nécessaire**.

## 🚀 Procédure d'activation

### 1. Depuis VS Code (Dev Container)

```bash
# Quitter le Dev Container
# Ctrl+Shift+P → "Dev Containers: Reopen Folder Locally"

# Puis redémarrer les containers (depuis votre machine locale)
cd CheckEngine
docker-compose down
docker-compose up -d

# Rouvrir le Dev Container
# Ctrl+Shift+P → "Dev Containers: Reopen in Container"
```

### 2. Depuis le terminal (local)

```bash
# Arrêter tous les containers
docker-compose down

# Supprimer le volume PostgreSQL (ATTENTION: efface les données!)
docker volume rm checkengine_postgres-data

# Redémarrer avec la nouvelle configuration
docker-compose up -d

# Attendre que PostgreSQL démarre
docker-compose logs -f postgres
# (Ctrl+C pour quitter les logs quand prêt)
```

### 3. Vérification

```bash
# Se reconnecter au Dev Container, puis:
cd /workspace/backend-symfony

# Vérifier que TimescaleDB est chargé
php bin/console dbal:run-sql "SHOW shared_preload_libraries;"
# Devrait afficher: timescaledb

# Créer l'extension
php bin/console dbal:run-sql "CREATE EXTENSION IF NOT EXISTS timescaledb CASCADE;"

# Vérifier l'installation
php bin/console dbal:run-sql "SELECT extname, extversion FROM pg_extension WHERE extname = 'timescaledb';"
# Devrait afficher: timescaledb | 2.22.1
```

### 4. Migrations et Fixtures

```bash
cd /workspace/backend-symfony

# Exécuter les migrations (création hypertable)
php bin/console doctrine:migrations:migrate --no-interaction

# Charger les fixtures OBD2
php bin/console doctrine:fixtures:load --no-interaction

# Tester le parser CSV
php bin/demo-parse-csv var/tmp/data/trackLog-2025-oct.-23_12-00-00.csv
```

## 📊 Vérification finale

```bash
# Vérifier que l'hypertable est créée
php bin/console dbal:run-sql "
SELECT 
    hypertable_name, 
    num_chunks,
    compression_enabled
FROM timescaledb_information.hypertables 
WHERE hypertable_name = 'trip_data';
"

# Devrait afficher:
# hypertable_name | num_chunks | compression_enabled
# trip_data       | 0          | t
```

## 🔍 Troubleshooting

### Erreur "extension timescaledb must be preloaded"

→ Les containers n'ont pas été redémarrés avec la nouvelle configuration  
→ Solution: `docker-compose down && docker-compose up -d`

### Erreur "extension timescaledb is not available"

→ L'image Docker n'est pas la bonne  
→ Vérifier: `docker-compose config | grep image.*postgres`  
→ Devrait afficher: `image: timescale/timescaledb:latest-pg17`

### PostgreSQL ne démarre pas

→ Conflit de port 5432  
→ Solution: `lsof -i :5432` puis kill le processus conflictuel

### Perte des données après `docker volume rm`

→ C'est normal, le volume a été supprimé  
→ Solution: Recharger les fixtures et ré-importer les CSV

## 💡 Notes

- TimescaleDB est **rétrocompatible** avec PostgreSQL
- Les requêtes SQL normales continuent de fonctionner
- Les hypertables sont transparentes pour l'application
- La compression est automatique après 7 jours
- La rétention efface automatiquement après 1 an

## 📚 Documentation

- TimescaleDB: https://docs.timescale.com/
- Docker Image: https://hub.docker.com/r/timescale/timescaledb
- Migrations: `/workspace/backend-symfony/migrations/Version20251024042318.php`
