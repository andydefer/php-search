<?php

declare(strict_types=1);

namespace AndyDefer\PhpSearch\Contracts\Services;

/**
 * Interface for JSONL fuzzy search operations.
 *
 * @author Andy Defer
 */
interface JsonlSearchInterface
{
    /**
     * Recherche dans tous les fichiers JSONL d'un dossier.
     *
     * @param  string  $directory  Dossier contenant les fichiers .jsonl
     * @param  string  $fields  Champs à rechercher (séparés par |, ex: "name|title")
     * @param  string  $query  Terme de recherche
     * @param  int  $limit  Nombre maximum de résultats
     * @return array<int, array<string, mixed>> Résultats avec scores
     */
    public function search(string $directory, string $fields, string $query, int $limit = 5): array;

    /**
     * Recherche dans un seul fichier JSONL.
     *
     * @param  string  $filePath  Chemin du fichier .jsonl
     * @param  array<string>  $fields  Liste des champs à rechercher
     * @param  string  $query  Terme de recherche
     * @param  int  $limit  Nombre maximum de résultats
     * @return array<int, array<string, mixed>> Résultats avec scores
     */
    public function searchFile(string $filePath, array $fields, string $query, int $limit = 5): array;
}
