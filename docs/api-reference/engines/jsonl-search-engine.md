# JsonlSearchEngine - Référence Technique

## Description

Moteur de recherche légère pour fichiers JSONL qui lit les données à la volée **sans indexation préalable**, idéal pour les petits volumes ou les données volatiles.

## Hiérarchie / Implémentations

```
BaseSearchEngine (abstract)
    └── JsonlSearchEngine
    
JsonlSearchInterface
    └── JsonlSearchEngine
```

## Rôle principal

Permet de rechercher directement dans des fichiers JSONL sans avoir à les indexer. Utile pour les données temporaires, les petits volumes ou les cas où l'indexation serait trop lourde.

## API / Méthodes publiques

### `searchInDirectory(string $directory, array $fields, string $query, int $limit = 5): array`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$directory` | `string` | Dossier contenant les fichiers .jsonl |
| `$fields` | `array<string>` | Liste des champs à rechercher |
| `$query` | `string` | Terme de recherche |
| `$limit` | `int` | Nombre maximum de résultats (défaut: 5) |

**Retourne :** `array<int, array<string, mixed>>` - Résultats avec scores

**Exemple :**
```php
$results = $engine->searchInDirectory('./data', ['name', 'title'], 'Leonard', 10);
```

---

### `searchInFile(string $filePath, array $fields, string $query, int $limit = 5): array`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$filePath` | `string` | Chemin du fichier .jsonl |
| `$fields` | `array<string>` | Liste des champs à rechercher |
| `$query` | `string` | Terme de recherche |
| `$limit` | `int` | Nombre maximum de résultats |

**Retourne :** `array<int, array<string, mixed>>` - Résultats avec scores

**Exemple :**
```php
$results = $engine->searchInFile('./data/artists.jsonl', ['name'], 'Leonard', 5);
```

---

### `searchInStream($stream, array $fields, string $query, int $limit = 5): array`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$stream` | `resource` | Stream contenant les données JSONL |
| `$fields` | `array<string>` | Liste des champs à rechercher |
| `$query` | `string` | Terme de recherche |
| `$limit` | `int` | Nombre maximum de résultats |

**Retourne :** `array<int, array<string, mixed>>` - Résultats avec scores

**Exceptions :** `InvalidArgumentException` - Si le paramètre n'est pas une ressource

**Exemple :**
```php
$stream = fopen('data://text/plain,{"name":"John Doe"}', 'r');
$results = $engine->searchInStream($stream, ['name'], 'John', 5);
fclose($stream);
```

---

### `searchInIterable(iterable $iterable, array $fields, string $query, int $limit = 5): array`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$iterable` | `iterable<array<string, mixed>>` | Itérable contenant les données |
| `$fields` | `array<string>` | Liste des champs à rechercher |
| `$query` | `string` | Terme de recherche |
| `$limit` | `int` | Nombre maximum de résultats |

**Retourne :** `array<int, array<string, mixed>>` - Résultats avec scores

**Exemple :**
```php
$data = [
    ['name' => 'John Doe'],
    ['name' => 'Jane Smith'],
];
$results = $engine->searchInIterable($data, ['name'], 'John', 5);
```

---

### `searchInIndex(string $indexPath, string $query, int $limit = 5): array`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$indexPath` | `string` | Chemin vers le fichier d'index JSONL |
| `$query` | `string` | Terme de recherche |
| `$limit` | `int` | Nombre maximum de résultats |

**Retourne :** `array<int, array<string, mixed>>` - Résultats avec scores

**Exceptions :** `InvalidArgumentException` - Si le fichier d'index n'existe pas

**Exemple :**
```php
$results = $engine->searchInIndex('./.php_search_index/index.jsonl', 'Leonard', 10);
```

## Cas d'utilisation

### Cas 1 : Recherche simple dans un fichier unique

```php
$filePath = './logs/2026-01-15.jsonl';
$results = $engine->searchInFile($filePath, ['level', 'message'], 'error', 20);

foreach ($results as $result) {
    echo "Ligne {$result['line']}: {$result['data']['message']}\n";
}
```

### Cas 2 : Recherche dans plusieurs fichiers

```php
$results = $engine->searchInDirectory('./logs', ['level'], 'error', 50);

foreach ($results as $result) {
    echo basename($result['file']) . " (ligne {$result['line']}): {$result['data']['message']}\n";
}
```

### Cas 3 : Recherche dans un stream (fichier compressé)

```php
$stream = gzopen('./logs/archive.jsonl.gz', 'r');
$results = $engine->searchInStream($stream, ['user_id', 'action'], 'login', 100);
gzclose($stream);

foreach ($results as $result) {
    echo "Utilisateur {$result['data']['user_id']} s'est connecté\n";
}
```

### Cas 4 : Recherche dans une collection Laravel

```php
$users = User::cursor();
$results = $engine->searchInIterable($users, ['name', 'email'], 'John', 10);

foreach ($results as $result) {
    echo "Trouvé: {$result['data']['name']}\n";
}
```

### Cas 5 : Champs imbriqués et tableaux

```php
// Recherche dans des champs imbriqués (notation pointée)
$results = $engine->searchInDirectory('./data', ['user.profile.nickname'], 'johnny', 5);

// Recherche dans des tableaux
$results = $engine->searchInDirectory('./data', ['songs'], 'Hallelujah', 5);
```

## Flux d'exécution

```
searchInDirectory()
    ↓
findAllJsonlFiles() → liste des fichiers .jsonl
    ↓
Pour chaque fichier:
    searchInFile()
        ↓
    JsonlService::readLineByLine()
        ↓
    Pour chaque ligne:
        passesPreFilter() → filtre rapide
            ↓
        computeLineScore() → calcul de similarité
            ↓
        Agrégation des résultats
    ↓
sortResults() → tri par pertinence
    ↓
Retour limité
```

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Stream invalide | `InvalidArgumentException` | `Le paramètre doit être une ressource de type stream` |
| Index introuvable | `InvalidArgumentException` | `Fichier d'index introuvable: {$indexPath}` |
| Fichier inexistant | - | Retourne `[]` (silencieux) |

## Intégration

`JsonlSearchEngine` s'intègre avec :
- `JsonlService` - Lecture des fichiers JSONL ligne par ligne
- `FileSystemInterface` - Vérification d'existence des fichiers
- `QueryProcessorInterface` - Traitement des requêtes (hérité)
- `PreFilterInterface` - Filtrage rapide (hérité)

## Performance

| Opération | Complexité | Notes |
|-----------|------------|-------|
| `searchInFile()` | O(l) | l = nombre de lignes du fichier |
| `searchInDirectory()` | O(f × l) | f = fichiers, l = lignes par fichier |
| `searchInStream()` | O(l) | l = lignes dans le stream |
| `searchInIterable()` | O(n) | n = nombre d'éléments |

**Limitations :**
- Lit tous les fichiers à chaque recherche
- Pas de cache
- Déconseillé pour les gros volumes (> 100 MB)

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.2+ | ✅ Complet |
| PHP 8.1 | ✅ Complet |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\PhpJsonl\Contexts\JsonlContext;
use AndyDefer\PhpJsonl\JsonlService;
use AndyDefer\PhpSearch\Engines\JsonlSearchEngine;
use AndyDefer\PhpServices\Services\FileSystemService;

// 1. Initialisation
$fileSystem = new FileSystemService();
$strategy = new SearchPathStrategy('/tmp');
$context = new JsonlContext();
$jsonlService = new JsonlService($strategy, $fileSystem, $context);

$queryProcessor = $container->get(QueryProcessorInterface::class);
$preFilter = $container->get(PreFilterInterface::class);

$engine = new JsonlSearchEngine(
    $jsonlService,
    $fileSystem,
    $queryProcessor,
    $preFilter
);

// 2. Recherche simple
$results = $engine->searchInDirectory('./logs', ['level'], 'error', 10);

// 3. Affichage des résultats
foreach ($results as $result) {
    echo "Fichier: " . basename($result['file']) . "\n";
    echo "Ligne: {$result['line']}\n";
    echo "Données: " . json_encode($result['data']) . "\n";
    echo "Score: {$result['percentage']}%\n";
    echo "---\n";
}

// 4. Recherche dans un fichier unique
$fileResults = $engine->searchInFile('./logs/2026-01-15.jsonl', ['message'], 'timeout', 5);

// 5. Recherche dans une collection
$collection = [
    ['id' => 1, 'name' => 'John Doe'],
    ['id' => 2, 'name' => 'Jane Smith'],
];
$collectionResults = $engine->searchInIterable($collection, ['name'], 'John', 10);
```

## Notes sur l'API

Toutes les méthodes de `JsonlSearchEngine` acceptent les champs sous forme de **tableau** (`array<string>`) pour une API cohérente et typée en PHP. La méthode `searchInDirectory()` ne fait pas exception : elle prend un tableau de champs, comme les autres méthodes.

```php
// ✅ Toutes les méthodes prennent un tableau
$engine->searchInDirectory('./data', ['name', 'title'], 'Leonard', 10);
$engine->searchInFile('./file.jsonl', ['name', 'title'], 'Leonard', 10);
$engine->searchInStream($stream, ['name', 'title'], 'Leonard', 10);
$engine->searchInIterable($iterable, ['name', 'title'], 'Leonard', 10);
```