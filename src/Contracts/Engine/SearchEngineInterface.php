<?php

declare(strict_types=1);

namespace AndyDefer\PhpSearch\Contracts\Engine;

/**
 * Interface unifiée pour le moteur de recherche.
 *
 * @author Andy Defer
 */
interface SearchEngineInterface
{
    /**
     * Indexe une source (dossier ou fichier JSONL).
     *
     * @param  string  $source  Dossier ou fichier JSONL à indexer
     * @return int Nombre d'éléments indexés
     */
    public function index(string $source): int;

    /**
     * Recherche dans un index spécifique.
     *
     * @param  string  $query  Terme de recherche
     * @param  int  $limit  Nombre maximum de résultats
     * @param  string|null  $source  Source spécifique ou null pour utiliser la dernière source
     * @return array<int, array<string, mixed>> Résultats avec scores
     */
    public function search(string $query, int $limit = 5, ?string $source = null): array;

    /**
     * Vérifie si un index existe pour une source donnée.
     */
    public function hasIndex(?string $source = null): bool;

    /**
     * Retourne les statistiques de l'index pour une source donnée.
     *
     * @return array<string, mixed>
     */
    public function getIndexStats(?string $source = null): array;

    /**
     * Supprime l'index pour une source donnée.
     */
    public function deleteIndex(?string $source = null): void;

    /**
     * Vide le cache pour une source donnée.
     *
     * @param  string|null  $source  Source spécifique
     * @param  string|null  $query  Requête spécifique ou null pour tout vider
     */
    public function clearCache(?string $source = null, ?string $query = null): void;

    /**
     * Liste tous les index disponibles.
     *
     * @return array<string, array<string, mixed>>
     */
    public function listIndexes(): array;
}
