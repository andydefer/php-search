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
    public function searchInDirectory(string $directory, string $fields, string $query, int $limit = 5): array;

    /**
     * Recherche dans un seul fichier JSONL.
     *
     * @param  string  $filePath  Chemin du fichier .jsonl
     * @param  array<string>  $fields  Liste des champs à rechercher
     * @param  string  $query  Terme de recherche
     * @param  int  $limit  Nombre maximum de résultats
     * @return array<int, array<string, mixed>> Résultats avec scores
     */
    public function searchInFile(string $filePath, array $fields, string $query, int $limit = 5): array;

    /**
     * Recherche à partir d'un stream (resource).
     *
     * @param  resource  $stream  Stream contenant les données JSONL
     * @param  array<string>  $fields  Liste des champs à rechercher
     * @param  string  $query  Terme de recherche
     * @param  int  $limit  Nombre maximum de résultats
     * @return array<int, array<string, mixed>> Résultats avec scores
     */
    public function searchInStream($stream, array $fields, string $query, int $limit = 5): array;

    /**
     * Recherche à partir d'un iterable (collection, tableau, etc.).
     *
     * @param  iterable<array<string, mixed>>  $iterable  Itérable contenant les données
     * @param  array<string>  $fields  Liste des champs à rechercher
     * @param  string  $query  Terme de recherche
     * @param  int  $limit  Nombre maximum de résultats
     * @return array<int, array<string, mixed>> Résultats avec scores
     */
    public function searchInIterable(iterable $iterable, array $fields, string $query, int $limit = 5): array;

    /**
     * Recherche directement dans un index de n-grams pré-calculés.
     *
     * @param  string  $indexPath  Chemin vers le fichier d'index JSONL
     * @param  string  $query  Terme de recherche
     * @param  int  $limit  Nombre maximum de résultats
     * @return array<int, array<string, mixed>> Résultats avec scores
     */
    public function searchInIndex(string $indexPath, string $query, int $limit = 5): array;
}
