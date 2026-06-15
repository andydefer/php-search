# PHP Search - Documentation

## 📦 Présentation

**PHP Search** est une bibliothèque PHP moderne pour la recherche floue dans des fichiers JSONL. Elle offre une solution complète avec indexation persistante, cache des résultats et support multi-index.

### Caractéristiques principales

- ✅ **Recherche floue** basée sur l'analyse de n-grams (2, 3 et 4)
- ✅ **Indexation persistante** pour des recherches ultra-rapides
- ✅ **Cache automatique** des résultats (TTL 24h)
- ✅ **Multi-index** : gérez plusieurs sources indépendantes
- ✅ **Streaming** : lisez ligne par ligne sans surcharge mémoire
- ✅ **Support multi-format** : fichiers, dossiers, streams, itérables
- ✅ **CLI complet** pour l'indexation et la recherche
- ✅ **Architecture modulaire** avec injection de dépendances
- ✅ **Support PHP 8.2+** avec types stricts

## 🚀 Installation

```bash
composer require andydefer/php-search
```

## 🎯 Utilisation rapide

### En ligne de commande

```bash
# Indexer un dossier de fichiers JSONL
./vendor/bin/php-search index ./data

# Rechercher (utilise le dernier index)
./vendor/bin/php-search "Leonard" 10

# Rechercher dans un index spécifique
./vendor/bin/php-search "Cardio" 5 --source=./doctors
```

### En PHP

```php
<?php

declare(strict_types=1);

use AndyDefer\PhpSearch\Engines\SearchEngine;
use AndyDefer\PhpSearch\Configs\EngineConfig;
use AndyDefer\PhpServices\Services\FileSystemService;

// Initialisation
$fileSystem = new FileSystemService();
$config = new EngineConfig(getcwd());
$queryProcessor = $container->get(QueryProcessorInterface::class);
$preFilter = $container->get(PreFilterInterface::class);

$engine = new SearchEngine($queryProcessor, $fileSystem, $config, $preFilter);

// Indexation
$engine->index('./data');

// Recherche
$results = $engine->search('Leonard', 10);

foreach ($results as $result) {
    echo $result['name'] . ' - ' . $result['percentage'] . "%\n";
}
```

## 📚 Architecture

### Moteurs de recherche

| Moteur | Description | Usage |
|--------|-------------|-------|
| `SearchEngine` | Moteur principal avec indexation persistante | Production, gros volumes |
| `JsonlSearchEngine` | Recherche directe sans index | Petits volumes, tests |

### Composants principaux

```
SearchEngine (principal)
    ├── QueryProcessorInterface → Traitement des requêtes
    ├── PreFilterInterface → Filtrage rapide
    ├── FileSystemInterface → Opérations fichiers
    └── EngineConfig → Configuration

JsonlSearchEngine (direct)
    ├── JsonlService → Lecture JSONL
    ├── QueryProcessorInterface → Traitement des requêtes
    └── PreFilterInterface → Filtrage rapide
```

## 📝 API principale

### SearchEngine

| Méthode | Description |
|---------|-------------|
| `index(string $source): int` | Indexe une source (dossier/fichier) |
| `search(string $query, int $limit = 5, ?string $source = null): array` | Recherche dans l'index |
| `hasIndex(?string $source = null): bool` | Vérifie l'existence d'un index |
| `getIndexStats(?string $source = null): array` | Statistiques de l'index |
| `deleteIndex(?string $source = null): void` | Supprime un index |
| `clearCache(?string $source = null, ?string $query = null): void` | Vide le cache |
| `listIndexes(): array` | Liste tous les index |

### JsonlSearchEngine

| Méthode | Description |
|---------|-------------|
| `searchInDirectory(string $directory, array $fields, string $query, int $limit = 5): array` | Recherche dans un dossier |
| `searchInFile(string $filePath, array $fields, string $query, int $limit = 5): array` | Recherche dans un fichier |
| `searchInStream($stream, array $fields, string $query, int $limit = 5): array` | Recherche dans un stream |
| `searchInIterable(iterable $iterable, array $fields, string $query, int $limit = 5): array` | Recherche dans un itérable |
| `searchInIndex(string $indexPath, string $query, int $limit = 5): array` | Recherche dans un index |

## 💡 Cas d'utilisation

### 1. Indexation de logs JSONL

```php
$engine->index('./logs');

// Recherche d'erreurs
$errors = $engine->search('error', 50);

foreach ($errors as $error) {
    echo $error['name'] . "\n";
    echo "Source: {$error['source']}\n";
}
```

### 2. Cache de résultats de recherche

```php
// Première recherche (cache miss)
$results = $engine->search('Leonard', 10);

// Deuxième recherche (cache hit - instantané)
$results = $engine->search('Leonard', 10);
```

### 3. Multi-index pour différents domaines

```php
$engine->index('./doctors');
$engine->index('./patients');

$doctors = $engine->search('Cardio', 5, './doctors');
$patients = $engine->search('Marie', 5, './patients');
```

### 4. Recherche directe sans index

```php
use AndyDefer\PhpSearch\Engines\JsonlSearchEngine;

$directEngine = new JsonlSearchEngine($jsonlService, $fileSystem, $queryProcessor, $preFilter);

// Recherche directe dans un fichier
$results = $directEngine->searchInFile('./data.jsonl', ['name'], 'Leonard', 10);
```

## 🔧 CLI - Commandes complètes

```bash
# Indexation
./vendor/bin/php-search index ./docs
./vendor/bin/php-search index ./docs/artists.jsonl

# Recherche (dernier index)
./vendor/bin/php-search "Leonard" 10

# Recherche dans un index spécifique
./vendor/bin/php-search "Doctor" 5 --source=./docs2

# Gestion des index
./vendor/bin/php-search --list-indexes
./vendor/bin/php-search --stats --source=./docs
./vendor/bin/php-search --delete-index --source=./docs
./vendor/bin/php-search --clear-cache --source=./docs
./vendor/bin/php-search --clear-cache --source=./docs --query=Leonard
```

## 📊 Performance

| Opération | Complexité | Notes |
|-----------|------------|-------|
| Indexation | O(n × m) | n = lignes, m = champs |
| Recherche (cache hit) | O(1) | Lecture fichier cache |
| Recherche (cache miss) | O(i) | i = items dans l'index |
| Recherche directe | O(f × l) | f = fichiers, l = lignes |

## 🔗 Dépendances

- `PHP ^8.2` - Langage requis
- `andydefer/php-jsonl` - Gestion des fichiers JSONL
- `andydefer/php-services` - Services de base
- `andydefer/php-vo` - Value objects
- `andydefer/domain-structures` - Structures de domaine

## 📜 License

MIT License

## 👨‍💻 Auteur

**Andy Kani** - [andykanidimbu@gmail.com](mailto:andykanidimbu@gmail.com)

## 🙏 Contributions

Les contributions sont les bienvenues !

---

## 📖 Résumé rapide

```bash
# CLI
./vendor/bin/php-search index ./data
./vendor/bin/php-search "Leonard" 10
./vendor/bin/php-search --stats
./vendor/bin/php-search --list-indexes
```

```php
// PHP
$engine->index('./data');
$results = $engine->search('Leonard', 10);

foreach ($results as $result) {
    echo $result['name'] . "\n";
}
```
---