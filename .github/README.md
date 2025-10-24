# GitHub Actions CI/CD Configuration

Ce dossier contient les workflows GitHub Actions pour l'intégration continue et le déploiement de CheckEngine.

## 📋 Workflows disponibles

### 🧪 CI - Tests & Coverage (`ci.yml`)
Lance automatiquement sur chaque push/PR :
- Tests PHPUnit complets avec TimescaleDB
- Génération de la couverture de code (PCOV)
- Upload vers Codecov
- Vérification du seuil minimum de couverture (15%)
- Publication des résultats de tests
- Génération des badges de couverture

**Triggers** : push sur `main`/`develop`, pull requests

### 🔒 Security Analysis (`security.yml`)
Analyse de sécurité complète :
- **Symfony Security Checker** : Vulnérabilités dans composer.lock
- **CodeQL** : Analyse de sécurité du code (JavaScript, Python)
- **Trivy** : Scan des images Docker (vulnérabilités CRITICAL/HIGH)
- **OWASP Dependency Check** : CVEs dans toutes les dépendances
- **TruffleHog** : Détection de secrets dans le code
- **Dependency Review** : Vérification des nouvelles dépendances en PR

**Triggers** : push, pull requests, lundi 9h UTC (schedule)

### 📊 Code Quality (`code-quality.yml`)
Analyse de qualité du code :
- **PHPStan** : Analyse statique niveau 6 avec plugins Symfony/Doctrine
- **PHP-CS-Fixer** : Vérification du style de code (@Symfony, @PSR12)
- **Psalm** : Analyse statique niveau 3
- **Rector** : Suggestions de modernisation du code
- **SonarCloud** : Analyse de qualité globale (nécessite token)
- **ESLint** : Linting du frontend Vue.js

**Triggers** : push sur `main`/`develop`, pull requests

## 🤖 Dependabot (`dependabot.yml`)
Mises à jour automatiques des dépendances tous les lundis :
- Composer (Symfony, Doctrine)
- Pip (Python)
- NPM (Frontend Vue.js)
- Docker (Images de base)
- GitHub Actions

## 🎯 Configuration requise

### Secrets GitHub à configurer
Aller dans Settings → Secrets and variables → Actions :

1. **CODECOV_TOKEN** (optionnel) : Token Codecov pour upload de couverture
   - S'inscrire sur https://codecov.io/gh/gregzter/CheckEngine
   - Copier le token

2. **SONAR_TOKEN** (optionnel) : Token SonarCloud pour analyse de qualité
   - S'inscrire sur https://sonarcloud.io
   - Créer un projet pour CheckEngine
   - Copier le token

### Permissions GitHub Actions
Aller dans Settings → Actions → General :
- **Workflow permissions** : Cocher "Read and write permissions"
- Autoriser les Actions à créer des PRs (pour Dependabot)

## 📊 Badges pour README.md

Les badges sont déjà configurés dans le README.md :

```markdown
[![CI Tests](https://github.com/gregzter/CheckEngine/workflows/CI%20-%20Tests%20%26%20Coverage/badge.svg)](...)
[![Security](https://github.com/gregzter/CheckEngine/workflows/Security%20Analysis/badge.svg)](...)
[![Code Quality](https://github.com/gregzter/CheckEngine/workflows/Code%20Quality/badge.svg)](...)
[![Coverage](.github/badges/jacoco.svg)](...)
```

## 🚀 Premier lancement

Après le push :
1. Aller sur https://github.com/gregzter/CheckEngine/actions
2. Les workflows se lancent automatiquement
3. Certains peuvent échouer au début (normal, configurations à ajuster)
4. Les erreurs seront visibles dans les logs

## 🔧 Personnalisation

### Modifier le seuil de couverture
Dans `ci.yml` ligne ~110 :
```yaml
THRESHOLD=15  # Augmenter progressivement (objectif 80%)
```

### Ajuster PHPStan
Dans `code-quality.yml` ligne ~30 :
```yaml
level: 6  # Monter progressivement jusqu'à 8
```

### Désactiver un workflow
Commenter ou supprimer les triggers `on:` en haut du fichier.

## 📚 Documentation
- [GitHub Actions](https://docs.github.com/en/actions)
- [PHPUnit](https://phpunit.de/)
- [PHPStan](https://phpstan.org/)
- [Codecov](https://docs.codecov.com/)
- [SonarCloud](https://docs.sonarcloud.io/)
