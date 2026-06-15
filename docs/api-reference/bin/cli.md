# PHP Search CLI - Référence Technique

## Description

Interface en ligne de commande pour le moteur de recherche floue, offrant l'indexation, la recherche et la gestion complète des index JSONL.

## Installation

```bash
composer require andydefer/php-search
```

## Rôle principal

Permet d'interagir avec le moteur `SearchEngine` via le terminal pour indexer des sources JSONL, effectuer des recherches floues et gérer les index persistants.

## API / Commandes

### `php-search index <source>`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `source` | `string` | Dossier ou fichier JSONL à indexer |

**Indexe** une source (dossier ou fichier JSONL) pour permettre des recherches rapides.

**Exemple :**
```bash
# Indexer un dossier
php-search index ./docs

# Indexer un fichier unique
php-search index ./docs/artists.jsonl
```

**Sortie :**
```
📂 Indexation de: ./docs
✅ Indexation terminée: 1523 éléments indexés
```

---

### `php-search <query> [limit]`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `query` | `string` | Terme de recherche |
| `limit` | `int` | Nombre maximum de résultats (défaut: 5) |

**Recherche** dans le dernier index utilisé. La recherche est floue et supporte les fautes d'orthographe.

**Exemple :**
```bash
# Recherche simple
php-search "Leonard"

# Avec limite personnalisée
php-search "Leonerd" 10
```

**Sortie :**
```
Top 5 results for 'Leonard':
================================================================================
1. Leonard Cohen
   Source: ./docs/artists.jsonl
   Score: 57 / max: 57 - Relevance: 100%
2. Leonardo DiCaprio
   Source: ./docs/actors.jsonl
   Score: 49.88 / max: 69 - Relevance: 82.61%
```

---

### `php-search <query> [limit] --source=<path>`

| Option | Type | Description |
|--------|------|-------------|
| `--source` | `string` | Source spécifique (dossier ou fichier) |

**Recherche** dans un index spécifique, sans utiliser le dernier index.

**Exemple :**
```bash
# Rechercher uniquement dans l'index des médecins
php-search "Cardio" 5 --source=./doctors

# Rechercher dans un index de fichier unique
php-search "Einstein" 10 --source=./scientists.jsonl
```

---

### `php-search --list-indexes`

**Liste** tous les index disponibles avec leurs statistiques.

**Exemple :**
```bash
php-search --list-indexes
```

**Sortie :**
```
📊 Index disponibles:
------------------------------------------------------------
• pro_sites_mon-site.com_docs
  📁 /project/.php_search_index/pro_sites_mon-site.com_docs
  📄 Éléments: 1523
  💾 Taille: 245.67 KB

• pro_sites_mon-site.com_doctors
  📁 /project/.php_search_index/pro_sites_mon-site.com_doctors
  📄 Éléments: 89
  💾 Taille: 12.34 KB
```

---

### `php-search --stats [--source=<path>]`

| Option | Type | Description |
|--------|------|-------------|
| `--source` | `string` | Source spécifique (utilise la dernière si absente) |

**Affiche** les statistiques détaillées d'un index.

**Exemple :**
```bash
# Statistiques du dernier index
php-search --stats

# Statistiques d'un index spécifique
php-search --stats --source=./docs
```

**Sortie :**
```
📊 Statistiques de l'index:
----------------------------------------
Source: ./docs
Existe: ✅ Oui
Éléments indexés: 1523
Chemin de l'index: /project/.php_search_index/pro_sites_mon-site.com_docs
Chemin du cache: /project/.php_search_index/pro_sites_mon-site.com_docs/cache
Taille de l'index: 245.67 KB
```

---

### `php-search --delete-index [--source=<path>]`

| Option | Type | Description |
|--------|------|-------------|
| `--source` | `string` | Source à supprimer (utilise la dernière si absente) |

**Supprime** un index et son cache associé.

**Exemple :**
```bash
# Supprimer le dernier index
php-search --delete-index

# Supprimer un index spécifique
php-search --delete-index --source=./docs2
```

**Sortie :**
```
✅ Index supprimé pour la source './docs2'
```

---

### `php-search --clear-cache [--source=<path>] [--query=<query>]`

| Option | Type | Description |
|--------|------|-------------|
| `--source` | `string` | Source spécifique (utilise la dernière si absente) |
| `--query` | `string` | Requête spécifique à vider (vide tout le cache si absent) |

**Vide** le cache des résultats de recherche.

**Exemple :**
```bash
# Vider tout le cache du dernier index
php-search --clear-cache

# Vider tout le cache d'un index spécifique
php-search --clear-cache --source=./docs

# Vider le cache pour une requête spécifique
php-search --clear-cache --source=./docs --query=Leonard
```

**Sortie :**
```
✅ Cache vidé pour la source './docs'
✅ Cache vidé pour la source './docs' pour la requête 'Leonard'
```

## Cas d'utilisation

### Cas 1 : Workflow complet d'indexation et recherche

```bash
# 1. Indexer les données
php-search index ./data

# 2. Rechercher
php-search "Leonard" 10

# 3. Vérifier les statistiques
php-search --stats

# 4. Si nécessaire, vider le cache
php-search --clear-cache
```

### Cas 2 : Multi-index pour différents domaines

```bash
# Indexer différentes sources
php-search index ./doctors
php-search index ./patients
php-search index ./medicines

# Rechercher dans chaque domaine
php-search "Cardio" 5 --source=./doctors
php-search "Marie" 5 --source=./patients
php-search "Aspirin" 5 --source=./medicines

# Lister tous les index
php-search --list-indexes
```

### Cas 3 : Mise à jour des données

```bash
# 1. Supprimer l'ancien index
php-search --delete-index --source=./data

# 2. Réindexer avec les nouvelles données
php-search index ./data

# 3. Vérifier l'indexation
php-search --stats --source=./data
```

### Cas 4 : Gestion de cache pour les recherches fréquentes

```bash
# Recherches multiples (le cache s'active automatiquement)
php-search "Leonard" 10  # Cache miss → calcule
php-search "Leonard" 10  # Cache hit → instantané

# Nettoyage périodique du cache
php-search --clear-cache --source=./data
```

## Flux d'exécution

### Indexation
```
php-search index ./docs
    ↓
Validation de la source
    ↓
Création du dossier d'index
    ↓
Parcourt tous les fichiers .jsonl
    ↓
Génération des n-grams
    ↓
Sauvegarde dans index.jsonl
    ↓
Affichage du nombre d'éléments indexés
```

### Recherche
```
php-search "Leonard" 10
    ↓
Chargement de la dernière source (.last_source)
    ↓
Vérification de l'existence de l'index
    ↓
Vérification du cache des résultats
    ├── Cache hit → retour instantané
    └── Cache miss → calcul
        ↓
    Chargement de l'index
        ↓
    Calcul des scores
        ↓
    Tri des résultats
        ↓
    Sauvegarde dans le cache
    ↓
Affichage des résultats formatés
```

## Gestion des erreurs

| Situation | Message |
|-----------|---------|
| Commande sans arguments | Affiche l'aide |
| Source invalide | `Source invalide: {$source}` |
| Aucune source indexée | `Aucune source indexée. Veuillez d'abord indexer un dossier ou fichier.` |
| Index introuvable | `Aucun index trouvé pour la source: {$source}. Veuillez d'abord indexer.` |
| Exception générale | `Error: {$message}` |

## Performance

| Opération | Complexité | Notes |
|-----------|------------|-------|
| Indexation | O(n × m) | n = lignes, m = champs |
| Recherche (cache hit) | O(1) | Lecture fichier cache |
| Recherche (cache miss) | O(i) | i = items dans l'index |
| Liste des index | O(d) | d = dossiers d'index |

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.2+ | ✅ Complet |
| PHP 8.1 | ✅ Complet |

## Exemple complet

```bash
#!/bin/bash

# 1. Installation
composer require andydefer/php-search

# 2. Préparer les données
mkdir -p data
echo '{"name":"Leonard Cohen","type":"musician"}' > data/artists.jsonl
echo '{"name":"Leonardo DiCaprio","type":"actor"}' >> data/artists.jsonl

# 3. Indexer
./vendor/bin/php-search index ./data

# 4. Rechercher
./vendor/bin/php-search "Leonard" 5

# 5. Gestion
./vendor/bin/php-search --stats
./vendor/bin/php-search --list-indexes

# 6. Nettoyage (si besoin)
./vendor/bin/php-search --clear-cache
./vendor/bin/php-search --delete-index --source=./data
```
---