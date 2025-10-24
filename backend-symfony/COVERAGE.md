# 📊 Guide de Couverture de Code

## Taux de couverture actuel

**Global: 16.96%** (486/2865 lignes)
- Méthodes: 22.18% (63/284)
- Classes: 5.71% (2/35)

## Détail par classe

| Classe | Couverture | Lignes testées |
|--------|------------|----------------|
| 🟢 StreamingDiagnosticAnalyzer | **88.30%** | 151/171 |
| 🟢 OBD2ColumnMapper | **64.52%** | 40/62 |
| 🟡 TripDataService | **46.30%** | 50/108 |
| 🟡 TripController | **46.69%** | 120/257 |
| 🔴 OBD2CsvParser | **15.59%** | 29/186 |

## 🚀 Utilisation

### 1. Générer la couverture

```bash
# Depuis la racine du projet
make coverage

# Ou directement dans backend-symfony
cd backend-symfony
php -d pcov.enabled=1 vendor/bin/phpunit --coverage-html=coverage/html
```

### 2. Visualiser dans VS Code

1. **Ouvre un fichier PHP** (ex: `src/Service/OBD2ColumnMapper.php`)
2. **Active Coverage Gutters**:
   - Clique sur "Watch" dans la barre d'état (en bas)
   - Ou: `Ctrl+Shift+7` / `Cmd+Shift+7`
3. **Les lignes s'affichent en couleur**:
   - 🟢 **VERT** = Ligne testée
   - 🔴 **ROUGE** = Ligne non testée
   - 🟠 **ORANGE** = Ligne partiellement testée

### 3. Rapport HTML interactif

Ouvre dans ton navigateur: `backend-symfony/coverage/html/index.html`

## ⌨️ Raccourcis VS Code

| Raccourci | Action |
|-----------|--------|
| `Ctrl+Shift+7` / `Cmd+Shift+7` | Toggle Watch (activer/désactiver) |
| `Ctrl+Shift+8` / `Cmd+Shift+8` | Display Coverage Report |
| `Ctrl+Shift+9` / `Cmd+Shift+9` | Remove Coverage |

## 🎯 Priorités d'amélioration

### 1. OBD2CsvParser (15.59%)
**157 lignes non testées sur 186**

Méthodes à tester:
- `parseCsv()` - Parse complet d'un fichier CSV
- `parseRow()` - Parse d'une ligne
- `extractData()` - Extraction des données
- `handleZipFile()` - Gestion des ZIP

### 2. TripController (46.69%)
**137 lignes non testées sur 257**

Endpoints à tester:
- Error handling avancé
- Validation edge cases
- Status endpoints
- Upload avec erreurs réseau

### 3. TripDataService (46.30%)
**58 lignes non testées sur 108**

Méthodes à tester:
- Query methods complexes
- Agrégations
- Statistiques

## 💡 Workflow recommandé

```bash
# 1. Génère la couverture
make coverage

# 2. Ouvre un fichier PHP dans VS Code

# 3. Active Watch (Ctrl+Shift+7)

# 4. Identifie les lignes ROUGES

# 5. Écris des tests pour ces lignes

# 6. Lance les tests
./vendor/bin/phpunit

# 7. Regénère la couverture
make coverage

# 8. Les lignes deviennent VERTES ! 🎉
```

## 📁 Structure des fichiers

```
backend-symfony/
├── coverage/
│   ├── clover.xml          # Format XML (pour CI/CD et VS Code)
│   └── html/               # Rapport HTML interactif
│       ├── index.html      # Page principale
│       ├── dashboard.html  # Tableau de bord
│       └── [classes]/      # Détail par classe
├── phpunit.xml.dist        # Configuration PHPUnit
└── tests/                  # Tests unitaires et d'intégration
```

## 🔧 Configuration

### DevContainer
PCOV est automatiquement installé au rebuild du container.

### VS Code
L'extension **Coverage Gutters** est automatiquement installée.

### PHPUnit
Configuré dans `phpunit.xml.dist`:
```xml
<coverage>
    <report>
        <clover outputFile="coverage/clover.xml"/>
        <html outputDirectory="coverage/html"/>
        <text outputFile="php://stdout" showUncoveredFiles="true"/>
    </report>
</coverage>
```

## 📊 Commandes Makefile

```bash
make coverage          # Génère HTML + XML + affiche dans terminal
make coverage-text     # Affiche seulement dans le terminal
make coverage-html     # Génère seulement le rapport HTML
```

## 🎨 Personnalisation des couleurs

Dans `.vscode/settings.json`:
```json
{
    "coverage-gutters.highlightdark": "rgba(50, 150, 50, 0.3)",
    "coverage-gutters.noHighlightDark": "rgba(200, 50, 50, 0.2)"
}
```

## 🚫 Fichiers exclus

Configurés dans `phpunit.xml.dist`:
- `src/Entity/` - Entités Doctrine (getters/setters)
- `src/Repository/` - Repositories basiques
- `src/Kernel.php` - Kernel Symfony

## 📈 Objectif

**Cible: 80%+ de couverture** pour les services critiques:
- OBD2CsvParser
- StreamingDiagnosticAnalyzer
- TripDataService
- OBD2ColumnMapper

---

**Dernière mise à jour:** 24 octobre 2025
**Version PHPUnit:** 12.4.1
**Version PCOV:** 1.0.12
