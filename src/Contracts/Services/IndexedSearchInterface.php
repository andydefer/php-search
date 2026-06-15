<?php

declare(strict_types=1);

namespace AndyDefer\PhpSearch\Contracts\Services;

/**
 * Interface for indexed fuzzy search operations.
 *
 * @author Andy Defer
 */
interface IndexedSearchInterface
{
    /**
     * Indexe un fichier JSON ou un dossier de fichiers JSONL.
     *
     * @param  string  $source  Fichier JSON ou dossier contenant des fichiers JSONL
     * @return int Nombre d'entités indexées
     */
    public function index(string $source): int;

    /**
     * Recherche dans l'index pré-calculé.
     *
     * @param  string  $query  Terme de recherche
     * @param  int  $limit  Nombre maximum de résultats
     * @return array<int, array<string, mixed>> Résultats avec scores
     */
    public function search(string $query, int $limit = 5): array;

    /**
     * Retourne les statistiques de l'index.
     *
     * @return array<string, mixed>
     */
    public function getIndexStats(): array;
}
