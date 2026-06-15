<?php

declare(strict_types=1);

namespace AndyDefer\PhpSearch\Contracts\Services;

/**
 * Interface for cached fuzzy search operations.
 *
 * @author Andy Defer
 */
interface CachedSearchInterface
{
    /**
     * Recherche avec mise en cache des résultats.
     *
     * @param  string  $directory  Dossier contenant les fichiers JSONL
     * @param  string  $fields  Champs à rechercher (séparés par |)
     * @param  string  $query  Terme de recherche
     * @param  int  $limit  Nombre maximum de résultats
     * @param  int  $cacheTtl  Durée de vie du cache en secondes (défaut: 3600)
     * @return array<int, array<string, mixed>> Résultats avec scores
     */
    public function search(string $directory, string $fields, string $query, int $limit = 5, int $cacheTtl = 3600): array;

    /**
     * Vide le cache pour une requête spécifique ou tout le cache.
     *
     * @param  string|null  $query  Requête spécifique à vider (null = tout vider)
     */
    public function clearCache(?string $query = null): void;
}
