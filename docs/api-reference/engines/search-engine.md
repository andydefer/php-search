# SearchEngine - Référence Technique

## Description

Moteur de recherche unifié offrant l'indexation persistante, le cache des résultats et le support multi-index pour les fichiers JSONL.

## Hiérarchie / Implémentations

```
BaseSearchEngine (abstract)
    └── SearchEngine (final)
    
SearchEngineInterface
    └── SearchEngine
```

## Rôle principal

Centralise toutes les opérations de recherche floue sur des fichiers JSONL. Il permet d'indexer une source (dossier ou fichier), de rechercher dans l'index avec mise en cache des résultats, et de gérer plusieurs index indépendants.

## API / Méthodes publiques

### `index(string $source): int`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$source` | `string` | Dossier ou fichier JSONL à indexer |

**Retourne :** `int` - Nombre d'éléments indexés

**Exceptions :** `InvalidArgumentException` - Si la source n'existe pas ou n'est pas valide

**Exemple :**
```php
$engine->index('./docs');
$engine->index('./docs/artists.jsonl');
```

---

### `search(string $query, int $limit = 5, ?string $source = null): array`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$query` | `string` | Terme de recherche |
| `$limit` | `int` | Nombre maximum de résultats (défaut: 5) |
| `$source` | `string|null` | Source spécifique ou null pour utiliser la dernière source |

**Retourne :** `array<int, array<string, mixed>>` - Résultats avec scores

**Exceptions :** `RuntimeException` - Si aucune source n'est indexée ou si l'index est introuvable

**Exemple :**
```php
$results = $engine->search('Leonard', 10);
$results = $engine->search('Doctor', 5, './docs2');
```

---

### `hasIndex(?string $source = null): bool`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$source` | `string|null` | Source spécifique ou null pour la dernière source |

**Retourne :** `bool` - True si l'index existe

**Exemple :**
```php
if ($engine->hasIndex('./docs')) {
    $results = $engine->search('Leonard', 5, './docs');
}
```

---

### `getIndexStats(?string $source = null): array`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$source` | `string|null` | Source spécifique ou null pour la dernière source |

**Retourne :** `array<string, mixed>` - Statistiques de l'index

**Exemple :**
```php
$stats = $engine->getIndexStats('./docs');
// [
//     'exists' => true,
//     'total_items' => 1523,
//     'index_path' => '/project/.php_search_index/...',
//     'cache_path' => '/project/.php_search_index/.../cache',
//     'index_size_bytes' => 245678
// ]
```

---

### `deleteIndex(?string $source = null): void`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$source` | `string|null` | Source spécifique ou null pour la dernière source |

**Exemple :**
```php
$engine->deleteIndex('./docs');
```

---

### `clearCache(?string $source = null, ?string $query = null): void`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$source` | `string|null` | Source spécifique ou null pour la dernière source |
| `$query` | `string|null` | Requête spécifique ou null pour tout vider |

**Exemple :**
```php
$engine->clearCache('./docs');           // Vide tout le cache de ./docs
$engine->clearCache('./docs', 'Leonard'); // Vide uniquement le cache pour 'Leonard'
```

---

### `listIndexes(): array`

**Retourne :** `array<string, array<string, mixed>>` - Liste de tous les index disponibles

**Exemple :**
```php
$indexes = $engine->listIndexes();
foreach ($indexes as $name => $info) {
    echo "{$name}: {$info['total_items']} éléments\n";
}
```

## Cas d'utilisation

### Cas 1 : Indexation et recherche simple

```php
// 1. Indexer un dossier
$engine->index('./data');

// 2. Rechercher (utilise la dernière source)
$results = $engine->search('Leonard', 10);

foreach ($results as $result) {
    echo $result['name'] . ' - ' . $result['percentage'] . "%\n";
}
```

### Cas 2 : Multi-index

```php
// Indexer plusieurs sources
$engine->index('./doctors');
$engine->index('./patients');
$engine->index('./medicines');

// Rechercher dans des index spécifiques
$doctors = $engine->search('Cardio', 5, './doctors');
$patients = $engine->search('Marie', 5, './patients');
$medicines = $engine->search('Aspirin', 5, './medicines');
```

### Cas 3 : Gestion des index

```php
// Lister tous les index
$indexes = $engine->listIndexes();
foreach ($indexes as $name => $info) {
    echo "Index: {$name}\n";
    echo "  Éléments: {$info['total_items']}\n";
    echo "  Taille: " . round($info['size_bytes'] / 1024, 2) . " KB\n";
}

// Supprimer un index obsolète
$engine->deleteIndex('./old_data');

// Vider le cache d'un index
$engine->clearCache('./frequent_searches');
```

## Flux d'exécution

### Indexation

```
Source (dossier/fichier)
    ↓
findAllJsonlFiles() / indexSingleFile()
    ↓
Pour chaque ligne JSONL:
    ↓
extractFieldValues() → valeurs textuelles
    ↓
QueryProcessor::process() → normalisation + n-grams
    ↓
createIndexItem() → structure d'index
    ↓
saveIndex() → écriture dans index.jsonl
```

### Recherche

```
Query
    ↓
getCacheKey() → clé de cache
    ↓
getCachedResult() → cache hit ? → retour résultats
    ↓ (cache miss)
QueryProcessor::process() → normalisation + n-grams
    ↓
loadIndex() → chargement index.jsonl
    ↓
Pour chaque item:
    QueryProcessor::computeScore() → calcul similarité
    ↓
sortResults() → tri par pertinence
    ↓
cacheResult() → stockage dans cache
    ↓
Retour résultats
```

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Source invalide | `InvalidArgumentException` | `Source invalide: {$source}` |
| Aucune source indexée | `RuntimeException` | `Aucune source indexée. Veuillez d'abord indexer un dossier ou fichier.` |
| Index introuvable | `RuntimeException` | `Aucun index trouvé pour la source: {$source}. Veuillez d'abord indexer.` |

## Intégration

`SearchEngine` s'intègre avec :
- `QueryProcessorInterface` - Traitement des requêtes et calcul des scores
- `FileSystemInterface` - Opérations sur les fichiers
- `PreFilterInterface` - Filtrage rapide (hérité de BaseSearchEngine)
- `EngineConfig` - Configuration du moteur

## Performance

| Opération | Complexité | Notes |
|-----------|------------|-------|
| `index()` | O(n × m) | n = lignes, m = champs |
| `search()` (cache hit) | O(1) | Lecture fichier cache |
| `search()` (cache miss) | O(i) | i = items dans l'index |
| `listIndexes()` | O(d) | d = dossiers d'index |
| `deleteIndex()` | O(f) | f = fichiers à supprimer |

**Optimisations :**
- Cache persistant des résultats (TTL 24h)
- Index pré-calculé (n-grams stockés)
- Recherche directe dans l'index sans relecture des sources

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.2+ | ✅ Complet |
| PHP 8.1 | ✅ Complet (readonly properties) |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\PhpSearch\Engines\SearchEngine;
use AndyDefer\PhpSearch\Configs\EngineConfig;
use AndyDefer\PhpServices\Services\FileSystemService;
use AndyDefer\PhpSearch\Services\QueryProcessorService;
use AndyDefer\PhpSearch\Services\PreFilterService;

// 1. Initialisation
$fileSystem = new FileSystemService();
$config = new EngineConfig(getcwd());
$queryProcessor = new QueryProcessorService(/* ... */);
$preFilter = new PreFilterService(/* ... */);

$engine = new SearchEngine($queryProcessor, $fileSystem, $config, $preFilter);

// 2. Indexation
echo "Indexation...\n";
$count = $engine->index('./data');
echo "{$count} éléments indexés\n";

// 3. Recherche
echo "\nRecherche 'Leonard':\n";
$results = $engine->search('Leonard', 5);

foreach ($results as $result) {
    echo "- {$result['name']} ({$result['percentage']}%)\n";
    echo "  Source: {$result['source']}\n";
}

// 4. Statistiques
$stats = $engine->getIndexStats();
echo "\nStatistiques:\n";
echo "Index: {$stats['index_path']}\n";
echo "Cache: {$stats['cache_path']}\n";

// 5. Nettoyage
$engine->clearCache();
```
---