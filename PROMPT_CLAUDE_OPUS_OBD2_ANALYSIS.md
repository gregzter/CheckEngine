gregzter: # Analyse des données OBD2 Torque Pro - Normalisation pour diagnostics Toyota Prius+

## Contexte du projet

Je développe une application de diagnostic automobile pour Toyota Prius+ (2012-2021, génération XW40, moteur 2ZR-FXE hybride). L'application analyse des logs OBD2 provenant de Torque Pro au format CSV.

**Contraintes techniques:**
- Les logs Torque Pro sont configurables → chaque log peut contenir des colonnes différentes
- Certaines colonnes peuvent être présentes mais vides
- Il faut valider la présence ET le contenu de chaque colonne avant analyse
- Format: fichier ZIP contenant `data/trackLog-*.csv`
- Volume: variable selon la durée du trajet (exemple: ~20 000 lignes pour un trajet)
- Fréquence d'échantillonnage: 0.1 mesure/seconde (1 ligne toutes les 10 secondes) au maximum dans Torque Pro
- **Approche flexible:** Le système doit détecter automatiquement quels diagnostics sont possibles selon les colonnes disponibles

## Colonnes disponibles dans l'exemple (63 colonnes)

### Données temporelles et GPS (1-12)
1. GPS Time
2. Device Time
3. Longitude
4. Latitude
5. GPS Speed (Meters/second)
6. Horizontal Dilution of Precision
7. Altitude
8. Bearing
9. G(x)
10. G(y)
11. G(z)
12. G(calibrated)

### Sondes Lambda et air/carburant (13-14, 33-38, 50-54, 56)
13. [PRIUS]AF Lambda B1S1
14. [PRIUS]AFS Voltage B1S1(V)
33. Fuel Trim Bank 1 Short Term(%)
34. Fuel Trim Bank 1 Long Term(%)
37. Air Fuel Ratio(Measured)(:1)
38. Air Fuel Ratio(Commanded)(:1)
50. O2 Sensor1 Wide Range Current(mA)
51. O2 Bank 1 Sensor 1 Voltage(V)
52. O2 Bank 1 Sensor 1 Wide Range Equivalence Ratio(λ)
53. O2 Bank 1 Sensor 1 Wide Range Voltage(V)
54. O2 Bank 1 Sensor 2 Voltage(V)
56. Commanded Equivalence Ratio(lambda)

### Températures moteur et catalyseur (16-19, 27-28, 39, 41, 44, 58-61)
16. [PRIUS]Coolant Temperature_7C0(°C)
17. [PRIUS]Coolant Temperature_7E0(°C)
18. [PRIUS]Engine Coolant Temp_7C4(°C)
19. [PRIUS]Engine Coolant Temperature_7E2(°C)
27. [PRIUS]Intake Air Temperature_7E0(°C)
28. [PRIUS]Intake Air Temperature_7E2(°C)
39. ECU(7EA): Ambient air temp(°C)
41. ECU(7EA): Engine Coolant Temperature(°C)
44. ECU(7EA): Intake Air Temperature(°C)
58. Intake Air Temperature(°C)
59. Engine Coolant Temperature(°C)
60. Catalyst Temperature (Bank 1 Sensor 1)(°C)
61. Catalyst Temperature (Bank 1 Sensor 2)(°C)

### Régime et vitesse moteur (20-26, 30-32, 43, 45, 55, 62-63)
20. [PRIUS]Engine Speed of Cyl #1 (51199 rpm: Active Test not performed)(RPM)
21. [PRIUS]Engine Speed of Cyl #2 (51199 rpm: Active Test not performed)(RPM)
22. [PRIUS]Engine Speed of Cyl #3 (51199 rpm: Active Test not performed)(RPM)
23. [PRIUS]Engine Speed of Cyl #4 (51199 rpm: Active Test not performed)(RPM)
24. [PRIUS]Engine Speed_7E0(RPM)
25. [PRIUS]Engine Speed_7E2(RPM)
26. [PRIUS]FR Wheel Speed(km/h)
30. [PRIUS]Vehicle Speed_7B0(km/h)
31. [PRIUS]Vehicle Speed_7E0(km/h)
32. [PRIUS]Vehicle Speed_7E2(km/h)
43. ECU(7EA): Engine RPM(rpm)
45. ECU(7EA): Speed (OBD)(km/h)
55. Engine RPM(rpm)
62. GPS vs OBD Speed difference(km/h)
63. Speed (OBD)(km/h)

### Charge moteur et admission d'air (15, 29, 35-36, 40, 42, 46, 57)
15. [PRIUS]All Cylinders Misfire Count
29. [PRIUS]Mass Air Flow(gm/sec)
35. Engine Load(%)
36. Engine Load(Absolute)(%)
40. ECU(7EA): Barometric pressure (from vehicle)(psi)
42. ECU(7EA): Engine Load(%)
46. ECU(7EA): Throttle Position(Manifold)(%)
57. Mass Air Flow Rate(g/s)

### GPS/Navigation (47-49)
47. GPS Satellites
48. GPS Bearing(°)
49. GPS Accuracy(m)

## Objectifs de diagnostic

**Principe général:** Le système doit être **flexible et opportuniste**. Il analyse d'abord les colonnes disponibles dans le log, puis détermine automatiquement quels diagnostics sont réalisables. Pas de priorité fixe.

### 1. Diagnostic des sondes Lambda
**Objectif:** Détecter les sondes défectueuses renvoyant des valeurs aberrantes

**Questions:**
- Quelles colonnes sont essentielles pour ce diagnostic?
- Quelles plages de valeurs normales pour chaque paramètre?
- Quels patterns indiquent une sonde défaillante?
- Comment détecter une sonde "figée" ou oscillant anormalement?

### 2. Diagnostic du catalyseur
**Objectif:** Évaluer le niveau d'usure du catalyseur et prédire son remplacement

**Questions:**
- Quelles colonnes sont essentielles?
- Comment calculer l'efficacité du catalyseur avec les données disponibles?
- Quels critères définissent un catalyseur "bon" / "moyen" / "à remplacer"?
- Corrélation entre températures catalyseur et performances?

### 3. Diagnostic moteur général
**Objectif:** Détecter anomalies moteur (ratés, problèmes admission, surcharge, etc.)

**Questions:**
- Quels diagnostics moteur peut-on faire avec ces données OBD2?
- Quelles colonnes pour chaque type de diagnostic?
- Comment détecter ratés d'allumage, problèmes injection, encrassement?

### 4. Analyse du type de conduite
**Objectif:** Classifier les portions de trajet (ville, route, autoroute) pour contextualiser les diagnostics

**Questions:**
- Comment détecter automatiquement les phases: ville (< 50 km/h), route (50-90 km/h), autoroute (> 90 km/h)?
- Quelles colonnes utiliser (vitesse GPS, OBD, accélérations)?
- Faut-il analyser séparément chaque type de conduite?
- Impact du type de route sur l'interprétation des valeurs Lambda/catalyseur?

### 5. Gestion du mode hybride (Prius+)
**Objectif:** Gérer les phases où le moteur thermique est arrêté (mode électrique pur)

**Questions:**
- Comment détecter les phases "moteur thermique arrêté"?
- Faut-il filtrer ces phases ou les analyser séparément?
- Impact sur les diagnostics (sondes inactives, températures en baisse)?
- Colonnes indicatrices du mode de fonctionnement hybride?

## Tâches demandées

### 1. Classification des colonnes par diagnostic

Pour chaque diagnostic (Lambda, Catalyseur, Moteur), lister:
- **Colonnes OBLIGATOIRES** (sans lesquelles le diagnostic est impossible)
- **Colonnes RECOMMANDÉES** (améliorent la précision)
- **Colonnes OPTIONNELLES** (contexte supplémentaire)

### 2. Stratégie de stockage

**Principe:** Stocker TOUTES les données utiles aux diagnostics détectés (pas d'agrégation pour l'instant).

Questions:
- Pour chaque diagnostic, quelles colonnes doivent être stockées en base de données?
- Format de stockage optimal (table `trip_data` avec colonnes: `trip_id`, `timestamp`, `pid_name`, `value`, `unit`)?
- Faut-il un prétraitement (conversion d'unités, nettoyage de valeurs aberrantes)?
- Comment gérer les valeurs manquantes ou invalides (NULL, 0, "-", valeurs hors plage)?

### 3. Critères de validation

Pour chaque colonne importante:
- Plage de valeurs normales (min/max)
- Valeurs indiquant une erreur de lecture
- Conditions de cohérence entre colonnes liées

### 4. Algorithmes de diagnostic et restitution

**Objectif:** Fournir une analyse complète et visuelle

Proposer des algorithmes pour générer:

**Scores (0-100%):**
- Efficacité catalyseur
- État sondes Lambda
- Santé moteur globale
- Qualité de conduite (éco-conduite)

**Graphiques temporels:**
- Quelles courbes afficher (températures, Lambda, AFR, charge moteur)?
- Sur quel axe temporel (durée trajet, distance parcourue)?
- Comment visualiser les phases (ville/route/autoroute)?

**Alertes et recommandations:**
- Seuils déclenchant des alertes (ex: "Catalyseur < 60% → remplacement recommandé")
- Messages textuels pour chaque anomalie détectée
- Priorisation des actions (urgent / à surveiller / information)

## Format de réponse attendu

Pour chaque type de diagnostic, structurer ainsi:

```markdown
# 1. DIAGNOSTIC CATALYSEUR

## Colonnes requises
- **OBLIGATOIRES** (sans ces colonnes, diagnostic impossible):
  - Colonne N° [numéro]: [nom] - [justification]
- **RECOMMANDÉES** (améliore précision):
  - Colonne N° [numéro]: [nom] - [justification]
- **OPTIONNELLES** (contexte supplémentaire):
  - Colonne N° [numéro]: [nom] - [justification]

## Plages de valeurs normales
Pour chaque colonne obligatoire/recommandée:
- Colonne N° [X] - [Nom]: 
  - Normale: [min-max] [unité]
  - Suspect: [plage qui inquiète]
  - Invalide/ignoré: [valeurs à filtrer]

## Algorithme de calcul du score (0-100%)
[Pseudocode ou formule mathématique]
- Étape 1: [calcul]
- Étape 2: [calcul]
- Score final: [formule]

## Données à stocker en base (table trip_data)
Liste des PIDs à sauvegarder:
- `pid_name`: [nom exact de la colonne CSV]
- Justification: [pourquoi cette donnée]
- Traitement: [conversion, nettoyage nécessaire]

## Graphiques à générer
- Graphique 1: [type] - Axe X: [temps/distance], Axe Y: [valeur]
- Graphique 2: [type] - ...

## Alertes et seuils
- Score > 80%: "Catalyseur en bon état" ✅
- Score 60-80%: "Surveillance recommandée" ⚠️
- Score < 60%: "Remplacement nécessaire" ❌

---

# 2. DIAGNOSTIC SONDES LAMBDA
[même structure complète]

# 3. DIAGNOSTIC MOTEUR GÉNÉRAL
[même structure complète]

# 4. ANALYSE TYPE DE CONDUITE
[même structure complète]

# 5. GESTION MODE HYBRIDE
[même structure complète]
```

## Questions spécifiques

1. **Diagnostics supplémentaires:** Y a-t-il d'autres diagnostics OBD2 pertinents que j'oublie? (ex: EGR, turbo pour véhicules équipés, transmission?)

2. **Spécificités hybride:** Pour la Toyota Prius+ hybride, quelles particularités doivent être prises en compte dans l'analyse?

3. **Mode électrique:** Comment détecter et gérer les phases où le moteur thermique est arrêté? Faut-il filtrer, analyser séparément, ou ignorer ces périodes?

4. **Fréquence d'échantillonnage:** Avec 0.1 mesure/seconde (1 toutes les 10s), est-ce suffisant pour tous les diagnostics? Certaines analyses nécessitent-elles une fréquence plus élevée?

5. **Flexibilité des logs:** Quelle stratégie pour gérer les colonnes manquantes ou vides d'un log à l'autre? Comment adapter automatiquement les diagnostics disponibles?

6. **Corrélations inter-diagnostics:** Y a-t-il des dépendances entre diagnostics? (ex: sonde Lambda défectueuse fausse le diagnostic catalyseur?)

7. **Durée minimale de trajet:** Quelle durée/distance minimale pour obtenir des diagnostics fiables?

---

## Objectif final du système

Créer un **système de parsing intelligent et flexible** qui:

1. ✅ **Détecte automatiquement** les colonnes disponibles dans chaque log uploadé
2. ✅ **Identifie les diagnostics réalisables** en fonction des colonnes présentes et non-vides
3. ✅ **Stocke toutes les données utiles** aux diagnostics détectés (pas d'agrégation)
4. ✅ **Génère un rapport complet** avec:
   - Scores (0-100%) pour chaque diagnostic
   - Graphiques temporels des paramètres clés
   - Alertes priorisées (urgent/à surveiller/info)
   - Recommandations d'actions concrètes
5. ✅ **Contextualise les résultats** selon le type de conduite (ville/route/autoroute)
6. ✅ **Gère le mode hybride** (détection phases électriques/thermiques)


Workspace: Collecte des informations sur l’espace de travailJe vais analyser en détail les besoins pour créer un système de diagnostic OBD2 intelligent et flexible pour votre Toyota Prius+. Voici mon analyse complète structurée selon vos besoins.

# 1. DIAGNOSTIC CATALYSEUR

## Colonnes requises
- **OBLIGATOIRES** (sans ces colonnes, diagnostic impossible):
  - Colonne N°54: `O2 Bank 1 Sensor 2 Voltage(V)` - Sonde aval essentielle pour mesurer l'efficacité
  - Colonne N°51 ou 53: `O2 Bank 1 Sensor 1 Voltage(V)` ou `Wide Range Voltage(V)` - Sonde amont pour comparaison
  - Colonne N°55 ou 24/25: `Engine RPM(rpm)` - Pour filtrer les phases moteur arrêté

- **RECOMMANDÉES** (améliore précision):
  - Colonne N°60/61: `Catalyst Temperature (Bank 1 Sensor 1/2)(°C)` - Température opérationnelle du catalyseur
  - Colonne N°63: `Speed (OBD)(km/h)` - Pour contextualiser (ville/route)
  - Colonne N°35: `Engine Load(%)` - Charge moteur pour conditions de test
  - Colonne N°59: `Engine Coolant Temperature(°C)` - Moteur chaud uniquement

- **OPTIONNELLES** (contexte supplémentaire):
  - Colonne N°33/34: `Fuel Trim Bank 1 Short/Long Term(%)` - Détection problèmes connexes
  - Colonne N°57: `Mass Air Flow Rate(g/s)` - Volume de gaz traités

## Plages de valeurs normales
- Colonne N°54 - O2 Sensor 2 Voltage:
  - Normale: 0.6-0.9V (stable, peu d'oscillations)
  - Suspect: <0.6V ou oscillations >0.2V d'amplitude
  - Invalide: <0V ou >1.1V, NULL, "-"

- Colonne N°51/53 - O2 Sensor 1 Voltage:
  - Normale: 0.1-0.9V (oscillations rapides)
  - Suspect: Valeur figée >2s
  - Invalide: <0V ou >5V, NULL

- Colonne N°60/61 - Catalyst Temperature:
  - Normale: 300-800°C en fonctionnement
  - Suspect: <250°C ou >900°C
  - Invalide: <0°C ou >1200°C

- Colonne N°55 - Engine RPM:
  - Normale: 0-6000 RPM
  - Invalide: <0 ou >10000 RPM

## Algorithme de calcul du score (0-100%)
```python
def calculate_catalyst_efficiency():
    # Filtrer données: RPM > 0, Coolant > 80°C, Speed > 20 km/h
    
    # Étape 1: Calculer ratio d'oscillation
    upstream_oscillations = count_zero_crossings(o2_sensor1_voltage, threshold=0.45V)
    downstream_oscillations = count_zero_crossings(o2_sensor2_voltage, threshold=0.7V)
    oscillation_ratio = downstream_oscillations / upstream_oscillations
    
    # Étape 2: Calculer amplitude moyenne
    upstream_amplitude = std_dev(o2_sensor1_voltage)
    downstream_amplitude = std_dev(o2_sensor2_voltage)
    amplitude_ratio = downstream_amplitude / upstream_amplitude
    
    # Étape 3: Score basé sur les ratios
    oscillation_score = max(0, 100 - (oscillation_ratio * 200))  # 0% oscillation = 100 score
    amplitude_score = max(0, 100 - (amplitude_ratio * 150))
    
    # Score final pondéré
    efficiency = (oscillation_score * 0.6) + (amplitude_score * 0.4)
    
    # Bonus/malus température
    if 400 < avg(catalyst_temp) < 700:
        efficiency += 5
    elif catalyst_temp < 300 or catalyst_temp > 800:
        efficiency -= 10
        
    return min(100, max(0, efficiency))
```

## Données à stocker en base (table trip_data)
```sql
-- PIDs essentiels pour diagnostic catalyseur
- pid_name: 'o2_b1s1_voltage' -- Colonne 51
- pid_name: 'o2_b1s2_voltage' -- Colonne 54
- pid_name: 'catalyst_temp_1' -- Colonne 60
- pid_name: 'catalyst_temp_2' -- Colonne 61
- pid_name: 'engine_rpm' -- Colonne 55
- pid_name: 'vehicle_speed' -- Colonne 63
- pid_name: 'engine_load' -- Colonne 35
- pid_name: 'coolant_temp' -- Colonne 59
- pid_name: 'stft' -- Colonne 33
- pid_name: 'ltft' -- Colonne 34
```

## Graphiques à générer
- **Graphique 1**: Courbe temporelle - Axe X: temps, Axe Y: Tensions O2 (S1 vs S2)
- **Graphique 2**: Histogramme - Distribution des oscillations par minute
- **Graphique 3**: Courbe - Température catalyseur vs efficacité calculée
- **Graphique 4**: Scatter plot - Efficacité vs vitesse (contextualisation)

## Alertes et seuils
- Score > 85%: "Catalyseur en excellent état" ✅
- Score 70-85%: "Catalyseur en bon état" ✅
- Score 50-70%: "Début d'usure, surveillance recommandée" ⚠️
- Score 30-50%: "Usure avancée, prévoir remplacement" ⚠️
- Score < 30%: "Catalyseur inefficace, remplacement urgent (risque P0420)" ❌

---

# 2. DIAGNOSTIC SONDES LAMBDA

## Colonnes requises
- **OBLIGATOIRES**:
  - Colonne N°51: `O2 Bank 1 Sensor 1 Voltage(V)` - Sonde amont principale
  - Colonne N°54: `O2 Bank 1 Sensor 2 Voltage(V)` - Sonde aval
  - Colonne N°55: `Engine RPM(rpm)` - Moteur en marche

- **RECOMMANDÉES**:
  - Colonne N°52: `O2 Bank 1 Sensor 1 Wide Range Equivalence Ratio(λ)` - Lambda précis
  - Colonne N°33/34: `Fuel Trim Short/Long Term(%)` - Compensation ECU
  - Colonne N°37/38: `Air Fuel Ratio Measured/Commanded` - Cohérence AFR
  - Colonne N°13/14: `[PRIUS]AF Lambda/AFS Voltage` - Spécifique Prius

- **OPTIONNELLES**:
  - Colonne N°50: `O2 Sensor1 Wide Range Current(mA)` - Courant de pompage
  - Colonne N°56: `Commanded Equivalence Ratio(lambda)` - Consigne ECU

## Plages de valeurs normales
- O2 Voltage (narrowband):
  - Normale: 0.1-0.9V avec oscillations >1Hz
  - Suspect: Figée >3s, oscillations <0.5Hz
  - Invalide: <0V ou >1.1V

- Lambda (wideband):
  - Normale: 0.95-1.05 (oscillant autour de 1.0)
  - Suspect: <0.85 ou >1.15 constant
  - Invalide: <0.5 ou >2.0

- Fuel Trims:
  - Normale: -10% à +10%
  - Suspect: -25% à -10% ou +10% à +25%
  - Invalide: <-50% ou >+50%

## Algorithme de calcul du score
```python
def evaluate_o2_sensors():
    scores = {}
    
    # Sonde amont (S1)
    s1_response_time = calculate_response_time(o2_s1_voltage)  # ms
    s1_oscillation_freq = calculate_frequency(o2_s1_voltage)  # Hz
    s1_voltage_range = max(o2_s1_voltage) - min(o2_s1_voltage)
    
    s1_score = 100
    if s1_response_time > 100:  # >100ms = lent
        s1_score -= (s1_response_time - 100) * 0.3
    if s1_oscillation_freq < 0.5:  # <0.5Hz = paresseux
        s1_score -= (0.5 - s1_oscillation_freq) * 40
    if s1_voltage_range < 0.6:  # Amplitude faible
        s1_score -= (0.6 - s1_voltage_range) * 50
        
    # Sonde aval (S2)
    s2_stability = std_dev(o2_s2_voltage)
    s2_mean = mean(o2_s2_voltage)
    
    s2_score = 100
    if s2_stability > 0.1:  # Trop d'oscillations
        s2_score -= s2_stability * 100
    if s2_mean < 0.6 or s2_mean > 0.8:  # Hors plage
        s2_score -= abs(0.7 - s2_mean) * 50
        
    return {
        'upstream': max(0, min(100, s1_score)),
        'downstream': max(0, min(100, s2_score)),
        'global': (s1_score * 0.7 + s2_score * 0.3)
    }
```

## Données à stocker
```sql
- pid_name: 'o2_b1s1_voltage'
- pid_name: 'o2_b1s2_voltage' 
- pid_name: 'o2_b1s1_lambda'
- pid_name: 'stft'
- pid_name: 'ltft'
- pid_name: 'afr_measured'
- pid_name: 'afr_commanded'
- pid_name: 'prius_af_lambda'
- pid_name: 'prius_afs_voltage'
```

## Graphiques
- **Graphique 1**: Oscilloscope O2 - Tensions S1 et S2 superposées
- **Graphique 2**: FFT - Analyse fréquentielle des oscillations
- **Graphique 3**: Histogramme - Temps de réponse par cycle
- **Graphique 4**: Corrélation - Fuel trims vs Lambda

## Alertes
- Score > 90%: "Sondes O2 en parfait état" ✅
- Score 75-90%: "Sondes fonctionnelles" ✅
- Score 50-75%: "Vieillissement détecté, surveiller" ⚠️
- Score 25-50%: "Sondes dégradées, prévoir remplacement" ⚠️
- Score < 25%: "Sondes défectueuses, remplacer" ❌

---

# 3. DIAGNOSTIC MOTEUR GÉNÉRAL

## Colonnes requises
- **OBLIGATOIRES**:
  - Colonne N°15: `[PRIUS]All Cylinders Misfire Count` - Ratés d'allumage
  - Colonne N°55: `Engine RPM(rpm)` - Régime moteur
  - Colonne N°35: `Engine Load(%)` - Charge moteur

- **RECOMMANDÉES**:
  - Colonne N°57: `Mass Air Flow Rate(g/s)` - Débit d'air
  - Colonne N°59: `Engine Coolant Temperature(°C)` - Température
  - Colonne N°33/34: `Fuel Trim ST/LT(%)` - Corrections carburant
  - Colonne N°46: `Throttle Position(%)` - Position papillon

## Algorithme
```python
def engine_health_score():
    score = 100
    
    # Ratés d'allumage
    misfire_rate = misfire_count / total_samples
    if misfire_rate > 0.001:  # >0.1%
        score -= misfire_rate * 1000
        
    # MAF vs charge cohérence
    expected_maf = engine_load * rpm * 0.0012  # Formule empirique
    maf_deviation = abs(maf - expected_maf) / expected_maf
    if maf_deviation > 0.15:
        score -= maf_deviation * 30
        
    # Fuel trims excessifs
    if abs(ltft) > 10:
        score -= abs(ltft) - 10
        
    return max(0, min(100, score))
```

---

# 4. ANALYSE TYPE DE CONDUITE

## Colonnes requises
- **OBLIGATOIRES**:
  - Colonne N°63: `Speed (OBD)(km/h)` - Vitesse véhicule
  - Colonne N°2: `Device Time` - Horodatage

- **RECOMMANDÉES**:
  - Colonne N°35: `Engine Load(%)` - Charge moteur
  - Colonne N°9-11: `G(x/y/z)` - Accélérations
  - Colonne N°55: `Engine RPM(rpm)` - Régime

## Algorithme
```python
def classify_driving_phases():
    phases = []
    for window in sliding_window(data, 30_seconds):
        avg_speed = mean(window.speed)
        speed_variance = variance(window.speed)
        
        if avg_speed < 30:
            phase = 'city'
        elif 30 <= avg_speed < 70:
            phase = 'suburban'
        elif 70 <= avg_speed < 110:
            phase = 'highway'
        else:
            phase = 'motorway'
            
        # Ajustement selon variance
        if speed_variance > 100:  # Stop & go
            phase = 'city'
            
        phases.append(phase)
    return phases
```

---

# 5. GESTION MODE HYBRIDE

## Colonnes requises
- **OBLIGATOIRES**:
  - Colonne N°55 ou 24/25: `Engine RPM` - Détection moteur arrêté
  - Colonne N°63: `Speed (OBD)(km/h)` - Véhicule en mouvement

- **RECOMMANDÉES**:
  - Colonnes N°13/14: `[PRIUS]AF Lambda/Voltage` - Spécifique hybride
  - Colonne N°57: `Mass Air Flow` - Zéro = moteur arrêté

## Algorithme
```python
def detect_hybrid_mode():
    if rpm == 0 and speed > 0:
        return 'electric'
    elif rpm > 0:
        return 'thermal'
    elif rpm == 0 and speed == 0:
        return 'stopped'
    
# Filtrage pour analyses
def filter_for_diagnosis(data):
    # Exclure mode électrique pour diagnostics O2/catalyseur
    return data[data.rpm > 0 & data.coolant_temp > 80]
```

## Recommandations finales

### Stratégie de parsing adaptative

````python
class AdaptiveDiagnosticParser:
    def __init__(self, csv_columns):
        self.available_columns = csv_columns
        self.possible_diagnostics = []
        
    def detect_possible_diagnostics(self):
        # Vérifier catalyseur
        if self.has_columns(['O2 Bank 1 Sensor 1', 'O2 Bank 1 Sensor 2', 'Engine RPM']):
            self.possible_diagnostics.append('catalyst')
            
        # Vérifier sondes O2
        if self.has_columns(['O2 Bank 1 Sensor 1', 'Fuel Trim']):
            self.possible_diagnostics.append('o2_sensors')
            
        # Vérifier moteur
        if self.has_columns(['Misfire Count', 'Engine Load', 'RPM']):
            self.possible_diagnostics.append('engine')
            
        return self.possible_diagnostics
````

### Durée minimale recommandée
- **Minimum absolu**: 10 minutes avec moteur chaud
- **Idéal**: 30+ minutes incluant ville/route/autoroute
- **Conditions**: Coolant > 80°C pour diagnostics fiables

Cette architecture flexible permet d'adapter automatiquement les diagnostics selon les données disponibles tout en maintenant une précision maximale pour votre Toyota Prius+.

gregzter: Peux tu me lister l'ensemble des données que tu souhaite, met des nom claire (ceux que je vais mettre en BDD) puis tu met le nom qu'on peut trouver dans torque (ici dans mon exemple mais aussi de ta mémoire) L'idéee est de pouvoir accepter des fichiers un peu different

GitHub Copilot: Je vais créer une liste complète de mapping entre les noms normalisés pour la base de données et toutes les variantes possibles qu'on peut rencontrer dans Torque Pro. Cela permettra d'accepter différents formats de logs.

````python
"""
Mapping complet des colonnes OBD2 pour diagnostic Prius+
Structure: nom_bdd -> [variantes possibles dans Torque Pro]
"""

OBD2_COLUMN_MAPPING = {
    # ========== DONNÉES TEMPORELLES ==========
    "timestamp_gps": [
        "GPS Time",
        "GPS Timestamp",
        "gps_time"
    ],
    
    "timestamp_device": [
        "Device Time", 
        "Phone Time",
        "System Time",
        "device_time"
    ],
    
    # ========== DONNÉES GPS/POSITION ==========
    "longitude": [
        "Longitude",
        "GPS Longitude",
        "Lon",
        "longitude"
    ],
    
    "latitude": [
        "Latitude",
        "GPS Latitude", 
        "Lat",
        "latitude"
    ],
    
    "gps_speed_ms": [
        "GPS Speed (Meters/second)",
        "GPS Speed(m/s)",
        "GPS Speed",
        "gps_speed"
    ],
    
    "gps_altitude": [
        "Altitude",
        "GPS Altitude",
        "Elevation",
        "altitude"
    ],
    
    "gps_bearing": [
        "Bearing",
        "GPS Bearing",
        "GPS Bearing(°)",
        "Heading"
    ],
    
    "gps_accuracy": [
        "GPS Accuracy(m)",
        "GPS Accuracy",
        "Horizontal Accuracy",
        "accuracy"
    ],
    
    "gps_satellites": [
        "GPS Satellites",
        "Satellites",
        "Sat Count",
        "satellites"
    ],
    
    # ========== ACCÉLÉROMÈTRE ==========
    "accel_x": [
        "G(x)",
        "Acceleration X",
        "Accel X(g)",
        "g_x"
    ],
    
    "accel_y": [
        "G(y)",
        "Acceleration Y",
        "Accel Y(g)",
        "g_y"
    ],
    
    "accel_z": [
        "G(z)",
        "Acceleration Z",
        "Accel Z(g)",
        "g_z"
    ],
    
    "accel_total": [
        "G(calibrated)",
        "Total G Force",
        "G Total",
        "g_total"
    ],
    
    # ========== SONDES LAMBDA / O2 AMONT (Sensor 1) ==========
    "o2_b1s1_voltage": [
        "O2 Bank 1 Sensor 1 Voltage(V)",
        "O2 Sensor Bank 1 - Sensor 1(V)",
        "O2 B1S1(V)",
        "O2 Volts Bank 1 sensor 1(V)",
        "o2_b1s1_v"
    ],
    
    "o2_b1s1_voltage_wide": [
        "O2 Bank 1 Sensor 1 Wide Range Voltage(V)",
        "O2 B1S1 Wide Range(V)",
        "Wide Range O2 B1S1(V)",
        "o2_b1s1_wide_v"
    ],
    
    "o2_b1s1_lambda": [
        "O2 Bank 1 Sensor 1 Wide Range Equivalence Ratio(λ)",
        "O2 B1S1 Lambda",
        "Lambda B1S1",
        "Wide Range Lambda B1S1",
        "o2_b1s1_lambda"
    ],
    
    "o2_b1s1_current": [
        "O2 Sensor1 Wide Range Current(mA)",
        "O2 B1S1 Current(mA)",
        "Wide Range O2 Current B1S1(mA)",
        "o2_b1s1_ma"
    ],
    
    # ========== SONDE O2 AVAL (Sensor 2) ==========
    "o2_b1s2_voltage": [
        "O2 Bank 1 Sensor 2 Voltage(V)",
        "O2 Sensor Bank 1 - Sensor 2(V)",
        "O2 B1S2(V)",
        "O2 Volts Bank 1 sensor 2(V)",
        "o2_b1s2_v"
    ],
    
    # ========== AIR/FUEL RATIO ==========
    "afr_measured": [
        "Air Fuel Ratio(Measured)(:1)",
        "AFR Measured(:1)",
        "Measured AFR",
        "Air/Fuel Ratio (Measured)",
        "afr_measured"
    ],
    
    "afr_commanded": [
        "Air Fuel Ratio(Commanded)(:1)",
        "AFR Commanded(:1)",
        "Commanded AFR",
        "Air/Fuel Ratio (Commanded)",
        "afr_commanded"
    ],
    
    "lambda_commanded": [
        "Commanded Equivalence Ratio(lambda)",
        "Commanded Lambda",
        "Lambda Commanded",
        "commanded_lambda"
    ],
    
    # ========== FUEL TRIM ==========
    "stft_b1": [
        "Fuel Trim Bank 1 Short Term(%)",
        "Short Term Fuel Trim Bank 1(%)",
        "STFT B1(%)",
        "Short term fuel trim—Bank 1(%)",
        "stft_b1"
    ],
    
    "ltft_b1": [
        "Fuel Trim Bank 1 Long Term(%)",
        "Long Term Fuel Trim Bank 1(%)",
        "LTFT B1(%)",
        "Long term fuel trim—Bank 1(%)",
        "ltft_b1"
    ],
    
    # ========== SPÉCIFIQUE PRIUS ==========
    "prius_af_lambda": [
        "[PRIUS]AF Lambda B1S1",
        "PRIUS AF Lambda",
        "Prius Lambda B1S1",
        "prius_lambda"
    ],
    
    "prius_afs_voltage": [
        "[PRIUS]AFS Voltage B1S1(V)",
        "PRIUS AFS Voltage(V)",
        "Prius AFS B1S1(V)",
        "prius_afs_v"
    ],
    
    "prius_misfire_count": [
        "[PRIUS]All Cylinders Misfire Count",
        "PRIUS Misfire Count",
        "Prius Total Misfires",
        "prius_misfires"
    ],
    
    "prius_maf": [
        "[PRIUS]Mass Air Flow(gm/sec)",
        "PRIUS MAF(g/s)",
        "Prius MAF",
        "prius_maf"
    ],
    
    # ========== TEMPÉRATURES MOTEUR ==========
    "coolant_temp": [
        "Engine Coolant Temperature(°C)",
        "Coolant Temperature(°C)",
        "Engine Coolant Temp(°C)",
        "Coolant Temp(°C)",
        "ECT(°C)",
        "coolant_temp_c"
    ],
    
    "prius_coolant_7c0": [
        "[PRIUS]Coolant Temperature_7C0(°C)",
        "PRIUS Coolant 7C0(°C)",
        "prius_coolant_7c0"
    ],
    
    "prius_coolant_7e0": [
        "[PRIUS]Coolant Temperature_7E0(°C)",
        "PRIUS Coolant 7E0(°C)",
        "prius_coolant_7e0"
    ],
    
    "prius_coolant_7c4": [
        "[PRIUS]Engine Coolant Temp_7C4(°C)",
        "PRIUS Coolant 7C4(°C)",
        "prius_coolant_7c4"
    ],
    
    "prius_coolant_7e2": [
        "[PRIUS]Engine Coolant Temperature_7E2(°C)",
        "PRIUS Coolant 7E2(°C)",
        "prius_coolant_7e2"
    ],
    
    "intake_air_temp": [
        "Intake Air Temperature(°C)",
        "IAT(°C)",
        "Intake Air Temp(°C)",
        "Air Intake Temperature(°C)",
        "intake_temp_c"
    ],
    
    "prius_iat_7e0": [
        "[PRIUS]Intake Air Temperature_7E0(°C)",
        "PRIUS IAT 7E0(°C)",
        "prius_iat_7e0"
    ],
    
    "prius_iat_7e2": [
        "[PRIUS]Intake Air Temperature_7E2(°C)",
        "PRIUS IAT 7E2(°C)",
        "prius_iat_7e2"
    ],
    
    "ambient_temp": [
        "ECU(7EA): Ambient air temp(°C)",
        "Ambient Air Temperature(°C)",
        "Outside Temperature(°C)",
        "Ambient Temp(°C)",
        "ambient_temp_c"
    ],
    
    # ========== TEMPÉRATURES CATALYSEUR ==========
    "catalyst_temp_b1s1": [
        "Catalyst Temperature (Bank 1 Sensor 1)(°C)",
        "Catalyst Temp B1S1(°C)",
        "CAT Temp Bank 1 - Sensor 1(°C)",
        "catalyst_b1s1_c"
    ],
    
    "catalyst_temp_b1s2": [
        "Catalyst Temperature (Bank 1 Sensor 2)(°C)",
        "Catalyst Temp B1S2(°C)",
        "CAT Temp Bank 1 - Sensor 2(°C)",
        "catalyst_b1s2_c"
    ],
    
    # ========== RÉGIME MOTEUR ==========
    "engine_rpm": [
        "Engine RPM(rpm)",
        "Engine Speed(rpm)",
        "RPM",
        "Engine RPM",
        "rpm"
    ],
    
    "prius_rpm_7e0": [
        "[PRIUS]Engine Speed_7E0(RPM)",
        "PRIUS RPM 7E0",
        "prius_rpm_7e0"
    ],
    
    "prius_rpm_7e2": [
        "[PRIUS]Engine Speed_7E2(RPM)",
        "PRIUS RPM 7E2",
        "prius_rpm_7e2"
    ],
    
    "prius_rpm_cyl1": [
        "[PRIUS]Engine Speed of Cyl #1 (51199 rpm: Active Test not performed)(RPM)",
        "PRIUS Cyl 1 RPM",
        "prius_cyl1_rpm"
    ],
    
    "prius_rpm_cyl2": [
        "[PRIUS]Engine Speed of Cyl #2 (51199 rpm: Active Test not performed)(RPM)",
        "PRIUS Cyl 2 RPM",
        "prius_cyl2_rpm"
    ],
    
    "prius_rpm_cyl3": [
        "[PRIUS]Engine Speed of Cyl #3 (51199 rpm: Active Test not performed)(RPM)",
        "PRIUS Cyl 3 RPM",
        "prius_cyl3_rpm"
    ],
    
    "prius_rpm_cyl4": [
        "[PRIUS]Engine Speed of Cyl #4 (51199 rpm: Active Test not performed)(RPM)",
        "PRIUS Cyl 4 RPM",
        "prius_cyl4_rpm"
    ],
    
    # ========== VITESSE VÉHICULE ==========
    "vehicle_speed": [
        "Speed (OBD)(km/h)",
        "Vehicle Speed(km/h)",
        "Speed(km/h)",
        "VSS(km/h)",
        "vehicle_speed_kmh"
    ],
    
    "prius_speed_7b0": [
        "[PRIUS]Vehicle Speed_7B0(km/h)",
        "PRIUS Speed 7B0(km/h)",
        "prius_speed_7b0"
    ],
    
    "prius_speed_7e0": [
        "[PRIUS]Vehicle Speed_7E0(km/h)",
        "PRIUS Speed 7E0(km/h)",
        "prius_speed_7e0"
    ],
    
    "prius_speed_7e2": [
        "[PRIUS]Vehicle Speed_7E2(km/h)",
        "PRIUS Speed 7E2(km/h)",
        "prius_speed_7e2"
    ],
    
    "prius_wheel_speed_fr": [
        "[PRIUS]FR Wheel Speed(km/h)",
        "PRIUS Front Right Wheel Speed(km/h)",
        "prius_fr_wheel_speed"
    ],
    
    "speed_difference": [
        "GPS vs OBD Speed difference(km/h)",
        "Speed Diff GPS-OBD(km/h)",
        "speed_diff_kmh"
    ],
    
    # ========== CHARGE MOTEUR ==========
    "engine_load": [
        "Engine Load(%)",
        "Calculated Engine Load(%)",
        "Load(%)",
        "engine_load_pct"
    ],
    
    "engine_load_absolute": [
        "Engine Load(Absolute)(%)",
        "Absolute Load Value(%)",
        "Absolute Engine Load(%)",
        "abs_load_pct"
    ],
    
    "throttle_position": [
        "ECU(7EA): Throttle Position(Manifold)(%)",
        "Throttle Position(%)",
        "TPS(%)",
        "Throttle Pos(%)",
        "throttle_pct"
    ],
    
    # ========== DÉBIT D'AIR ==========
    "maf_rate": [
        "Mass Air Flow Rate(g/s)",
        "MAF(g/s)",
        "Mass Air Flow(g/s)",
        "MAF air flow rate(g/s)",
        "maf_gs"
    ],
    
    # ========== PRESSION ==========
    "barometric_pressure": [
        "ECU(7EA): Barometric pressure (from vehicle)(psi)",
        "Barometric Pressure(psi)",
        "Baro Pressure(psi)",
        "baro_psi"
    ],
    
    # ========== DIAGNOSTICS ADDITIONNELS ==========
    "distance_since_dtc_clear": [
        "Distance since codes cleared(km)",
        "Distance Since DTC Clear(km)",
        "distance_dtc_clear_km"
    ],
    
    "runtime_since_engine_start": [
        "Run time since engine start(s)",
        "Engine Runtime(s)",
        "runtime_s"
    ],
    
    "fuel_level": [
        "Fuel Level (From Engine ECU)(%)",
        "Fuel Level(%)",
        "Fuel Tank Level(%)",
        "fuel_level_pct"
    ],
    
    "fuel_rate": [
        "Fuel flow rate/hour(l/hr)",
        "Fuel Rate(L/h)",
        "Fuel Consumption(l/hr)",
        "fuel_rate_lh"
    ]
}

# Dictionnaire inversé pour recherche rapide
COLUMN_LOOKUP = {}
for db_name, variants in OBD2_COLUMN_MAPPING.items():
    for variant in variants:
        COLUMN_LOOKUP[variant.lower()] = db_name

def normalize_column_name(torque_column_name):
    """
    Normalise un nom de colonne Torque vers le nom BDD standard
    
    Args:
        torque_column_name: Nom de colonne tel qu'il apparaît dans le CSV
        
    Returns:
        str: Nom normalisé pour la BDD ou None si non reconnu
    """
    return COLUMN_LOOKUP.get(torque_column_name.lower())

def get_required_columns_for_diagnostic(diagnostic_type):
    """
    Retourne les colonnes nécessaires pour un type de diagnostic
    
    Args:
        diagnostic_type: 'catalyst', 'o2_sensors', 'engine', 'driving', 'hybrid'
        
    Returns:
        dict: {
            'mandatory': [liste des colonnes obligatoires],
            'recommended': [liste des colonnes recommandées],
            'optional': [liste des colonnes optionnelles]
        }
    """
    diagnostics = {
        'catalyst': {
            'mandatory': [
                'o2_b1s1_voltage',
                'o2_b1s2_voltage', 
                'engine_rpm'
            ],
            'recommended': [
                'catalyst_temp_b1s1',
                'catalyst_temp_b1s2',
                'vehicle_speed',
                'engine_load',
                'coolant_temp'
            ],
            'optional': [
                'stft_b1',
                'ltft_b1',
                'maf_rate'
            ]
        },
        'o2_sensors': {
            'mandatory': [
                'o2_b1s1_voltage',
                'o2_b1s2_voltage',
                'engine_rpm'
            ],
            'recommended': [
                'o2_b1s1_lambda',
                'stft_b1',
                'ltft_b1',
                'afr_measured',
                'afr_commanded',
                'prius_af_lambda',
                'prius_afs_voltage'
            ],
            'optional': [
                'o2_b1s1_current',
                'lambda_commanded'
            ]
        },
        'engine': {
            'mandatory': [
                'engine_rpm',
                'engine_load'
            ],
            'recommended': [
                'prius_misfire_count',
                'maf_rate',
                'coolant_temp',
                'stft_b1',
                'ltft_b1',
                'throttle_position'
            ],
            'optional': [
                'intake_air_temp',
                'barometric_pressure'
            ]
        },
        'driving': {
            'mandatory': [
                'vehicle_speed',
                'timestamp_device'
            ],
            'recommended': [
                'engine_load',
                'accel_x',
                'accel_y',
                'accel_z',
                'engine_rpm'
            ],
            'optional': [
                'gps_speed_ms',
                'throttle_position'
            ]
        },
        'hybrid': {
            'mandatory': [
                'engine_rpm',
                'vehicle_speed'
            ],
            'recommended': [
                'prius_af_lambda',
                'prius_afs_voltage',
                'maf_rate'
            ],
            'optional': [
                'prius_rpm_7e0',
                'prius_rpm_7e2'
            ]
        }
    }
    
    return diagnostics.get(diagnostic_type, {})

def detect_available_diagnostics(available_columns):
    """
    Détecte quels diagnostics sont possibles avec les colonnes disponibles
    
    Args:
        available_columns: Liste des noms de colonnes normalisés disponibles
        
    Returns:
        dict: {
            'diagnostic_name': {
                'available': bool,
                'completeness': float (0-1),
                'missing_mandatory': [],
                'missing_recommended': []
            }
        }
    """
    available_set = set(available_columns)
    results = {}
    
    for diag_type in ['catalyst', 'o2_sensors', 'engine', 'driving', 'hybrid']:
        required = get_required_columns_for_diagnostic(diag_type)
        
        mandatory_set = set(required.get('mandatory', []))
        recommended_set = set(required.get('recommended', []))
        
        missing_mandatory = mandatory_set - available_set
        missing_recommended = recommended_set - available_set
        
        # Calcul du taux de complétude
        total_important = len(mandatory_set) + len(recommended_set) * 0.5
        available_important = len(mandatory_set - missing_mandatory) + \
                             len(recommended_set - missing_recommended) * 0.5
        
        completeness = available_important / total_important if total_important > 0 else 0
        
        results[diag_type] = {
            'available': len(missing_mandatory) == 0,
            'completeness': completeness,
            'missing_mandatory': list(missing_mandatory),
            'missing_recommended': list(missing_recommended)
        }
    
    return results

# Exemple d'utilisation
if __name__ == "__main__":
    # Test de normalisation
    test_columns = [
        "GPS Time",
        "O2 Bank 1 Sensor 1 Voltage(V)",
        "Engine RPM(rpm)",
        "[PRIUS]AF Lambda B1S1"
    ]
    
    print("=== Test de normalisation ===")
    for col in test_columns:
        normalized = normalize_column_name(col)
        print(f"{col} -> {normalized}")
    
    # Test de détection de diagnostics
    print("\n=== Test de détection de diagnostics ===")
    available = ['o2_b1s1_voltage', 'o2_b1s2_voltage', 'engine_rpm', 'coolant_temp']
    diagnostics = detect_available_diagnostics(available)
    
    for diag, info in diagnostics.items():
        print(f"\n{diag.upper()}:")
        print(f"  Disponible: {info['available']}")
        print(f"  Complétude: {info['completeness']:.1%}")
        if info['missing_mandatory']:
            print(f"  Colonnes obligatoires manquantes: {info['missing_mandatory']}")
````

Ce mapping complet permet:

1. **Flexibilité maximale**: Accepte toutes les variantes connues de noms de colonnes
2. **Normalisation**: Convertit vers des noms standards en base de données
3. **Détection automatique**: Identifie quels diagnostics sont possibles
4. **Validation**: Vérifie la complétude des données pour chaque diagnostic

Les noms en BDD sont:
- Courts et clairs
- En snake_case
- Sans caractères spéciaux
- Groupés par catégorie logique

Voulez-vous que j'ajoute d'autres variantes de noms ou que je crée le module de parsing qui utilise ce mapping?