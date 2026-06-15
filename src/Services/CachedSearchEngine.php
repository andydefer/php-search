<?php

declare(strict_types=1);

namespace AndyDefer\PhpSearch\Services;

use AndyDefer\PhpSearch\Contracts\Services\CachedSearchInterface;
use AndyDefer\PhpSearch\Contracts\Services\CacheInterface;
use AndyDefer\PhpSearch\Contracts\Services\JsonlSearchInterface;

/**
 * Moteur de recherche avec mise en cache des résultats.
 *
 * Encapsule un moteur de recherche et met en cache les résultats
 * pour les requêtes identiques.
 *
 * @author Andy Defer
 */
final class CachedSearchEngine implements CachedSearchInterface
{
    private const CACHE_KEY_PREFIX = 'search_result_';

    /** @var array<string, string> Cache des clés pour suppression par préfixe */
    private array $trackedKeys = [];

    public function __construct(
        private readonly JsonlSearchInterface $searchEngine,
        private readonly CacheInterface $cache,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function search(string $directory, string $fields, string $query, int $limit = 5, int $cacheTtl = 3600): array
    {
        $cacheKey = $this->getCacheKey($directory, $fields, $query, $limit);

        // Suivre la clé pour pouvoir la supprimer plus tard
        $this->trackKey($cacheKey);

        // Tentative de lecture du cache
        $cached = $this->cache->get($cacheKey);

        if ($cached !== null && is_array($cached)) {
            return $cached;
        }

        // Cache miss - effectuer la recherche
        $results = $this->searchEngine->search($directory, $fields, $query, $limit);

        // Stocker dans le cache
        $this->cache->set($cacheKey, $results, $cacheTtl);

        return $results;
    }

    /**
     * {@inheritDoc}
     */
    public function clearCache(?string $query = null): void
    {
        if ($query === null) {
            // Vider tout le cache : supprimer toutes les clés suivies
            foreach ($this->trackedKeys as $cacheKey => $originalKey) {
                $this->cache->delete($cacheKey);
            }
            $this->trackedKeys = [];

            return;
        }

        // Supprimer le cache pour une requête spécifique
        // On cherche parmi les clés suivies celle qui correspond
        $keysToDelete = [];

        foreach ($this->trackedKeys as $cacheKey => $originalKey) {
            // Extraire le query de la clé originale
            if (str_contains($originalKey, $query)) {
                $keysToDelete[] = $cacheKey;
            }
        }

        foreach ($keysToDelete as $cacheKey) {
            $this->cache->delete($cacheKey);
            unset($this->trackedKeys[$cacheKey]);
        }
    }

    /**
     * Génère une clé de cache unique pour une requête.
     */
    private function getCacheKey(string $directory, string $fields, string $query, int $limit): string
    {
        $data = $directory.'|'.$fields.'|'.$query.'|'.$limit;

        return self::CACHE_KEY_PREFIX.md5($data);
    }

    /**
     * Suit une clé de cache pour pouvoir la supprimer plus tard.
     */
    private function trackKey(string $cacheKey): void
    {
        if (! isset($this->trackedKeys[$cacheKey])) {
            $this->trackedKeys[$cacheKey] = $cacheKey;
        }
    }
}
