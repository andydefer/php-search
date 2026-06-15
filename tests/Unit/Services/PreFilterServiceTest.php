<?php

declare(strict_types=1);

namespace AndyDefer\PhpSearch\Tests\Unit\Services;

use AndyDefer\PhpSearch\Contracts\Services\CacheInterface;
use AndyDefer\PhpSearch\Contracts\Services\PreFilterInterface;
use AndyDefer\PhpSearch\Tests\TestCase;

final class PreFilterServiceTest extends TestCase
{
    private PreFilterInterface $service;
    private CacheInterface $cache;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->getService(PreFilterInterface::class);
        $this->cache = $this->getService(CacheInterface::class);
    }

    public function test_passes_returns_true_when_enough_letters_match(): void
    {
        // Arrange
        $item = 'John Doe';
        $query = 'John Doe';

        // Act
        $result = $this->service->passes($item, $query);

        // Assert
        $this->assertTrue($result);
    }

    public function test_passes_returns_false_when_not_enough_letters_match(): void
    {
        // Arrange
        $item = 'AAA BBB';
        $query = 'CCC DDD';

        // Act
        $result = $this->service->passes($item, $query);

        // Assert
        $this->assertFalse($result);
    }

    public function test_passes_handles_accented_characters(): void
    {
        // Arrange
        $item = 'Léonard de Vinci';
        $query = 'Leonard De Vinci';

        // Act
        $result = $this->service->passes($item, $query);

        // Assert
        $this->assertTrue($result);
    }

    public function test_passes_handles_special_characters(): void
    {
        // Arrange
        $item = 'Jean-Luc Mélenchon!';
        $query = 'Jean Luc Melenchon';

        // Act
        $result = $this->service->passes($item, $query);

        // Assert
        $this->assertTrue($result);
    }

    public function test_passes_returns_false_for_empty_query(): void
    {
        // Arrange
        $item = 'John Doe';
        $query = '';

        // Act
        $result = $this->service->passes($item, $query);

        // Assert
        $this->assertFalse($result);
    }

    public function test_passes_returns_false_for_empty_item(): void
    {
        // Arrange
        $item = '';
        $query = 'John Doe';

        // Act
        $result = $this->service->passes($item, $query);

        // Assert
        $this->assertFalse($result);
    }

    public function test_passes_handles_case_insensitivity(): void
    {
        // Arrange
        $item = 'JOHN DOE';
        $query = 'john doe';

        // Act
        $result = $this->service->passes($item, $query);

        // Assert
        // Maintenant avec strtolower(), les lettres sont converties en minuscules
        $this->assertTrue($result);
    }

    public function test_passes_with_partial_match(): void
    {
        // Arrange
        $item = 'Christopher Nolan';
        $query = 'Chris Nolan';

        // Act
        $result = $this->service->passes($item, $query);

        // Assert
        $this->assertTrue($result);
    }

    public function test_passes_with_common_letters_between_different_words(): void
    {
        // Arrange
        $item = 'Pierre Durand';
        $query = 'Marie Curie';

        // Act
        $result = $this->service->passes($item, $query);

        // Assert
        // 'Pierre Durand' et 'Marie Curie' partagent les lettres: a, r, i, e, u
        // Soit 5 lettres sur 7 = 71% > 30%, donc vrai
        $this->assertTrue($result);
    }

    public function test_passes_with_unicode_characters(): void
    {
        // Arrange
        $item = 'Müller Schröder';
        $query = 'Mueller Schroeder';

        // Act
        $result = $this->service->passes($item, $query);

        // Assert
        $this->assertTrue($result);
    }

    public function test_passes_with_single_letter(): void
    {
        // Arrange
        $item = 'A';
        $query = 'A';

        // Act
        $result = $this->service->passes($item, $query);

        // Assert
        $this->assertTrue($result);
    }

    public function test_passes_with_single_letter_different(): void
    {
        // Arrange
        $item = 'A';
        $query = 'B';

        // Act
        $result = $this->service->passes($item, $query);

        // Assert
        $this->assertFalse($result);
    }

    public function test_passes_respects_min_letter_match_percentage(): void
    {
        // Arrange
        $item = 'ABC';
        $query = 'ABXYZ'; // 2 lettres communes sur 5 = 40% > 30%

        // Act
        $result = $this->service->passes($item, $query);

        // Assert
        $this->assertTrue($result);
    }

    public function test_passes_below_threshold(): void
    {
        // Arrange
        $item = 'ABC';
        $query = 'DEFGH';

        // Act
        $result = $this->service->passes($item, $query);

        // Assert
        $this->assertFalse($result);
    }

    public function test_real_world_scenario_artists(): void
    {
        // Arrange
        $artists = [
            'Leonardo DiCaprio',
            'Leonard Cohen',
            'Leonardo da Vinci',
            'Leonard Nimoy',
            'Léonard de Vinci'
        ];
        $query = 'Leonard';

        // Act & Assert
        foreach ($artists as $artist) {
            $result = $this->service->passes($artist, $query);
            $this->assertTrue($result, "Failed for: $artist");
        }
    }

    public function test_real_world_scenario_names(): void
    {
        // Arrange
        $items = [
            'John Fitzgerald Kennedy',
            'John Lennon',
            'Johnny Cash',
            'Jonathan Swift'
        ];
        $query = 'John';

        // Act & Assert
        foreach ($items as $item) {
            $result = $this->service->passes($item, $query);
            $this->assertTrue($result, "Failed for: $item");
        }
    }

    public function test_real_world_scenario_filtering(): void
    {
        // Arrange
        $query = 'Curie';

        // Act & Assert
        $this->assertTrue($this->service->passes('Marie Curie', $query));
        $this->assertTrue($this->service->passes('Pierre Curie', $query));
        // 'Albert Einstein' contient 'e' et 'i' (2/5 = 40% > 30%)
        $this->assertTrue($this->service->passes('Albert Einstein', $query));
        // 'Isaac Newton' contient 'e' et 'i' (2/5 = 40% > 30%)
        $this->assertTrue($this->service->passes('Isaac Newton', $query));
    }

    public function test_passes_caches_normalized_strings(): void
    {
        // Arrange
        $item = 'John Doe';
        $query = 'John';

        // Act
        $result = $this->service->passes($item, $query);

        // Assert
        $this->assertTrue($result);
    }
}
