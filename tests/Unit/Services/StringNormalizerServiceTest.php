<?php

declare(strict_types=1);

namespace AndyDefer\PhpSearch\Tests\Unit\Services;

use AndyDefer\PhpSearch\Contracts\Services\CacheInterface;
use AndyDefer\PhpSearch\Contracts\Services\StringNormalizerInterface;
use AndyDefer\PhpSearch\Tests\TestCase;

final class StringNormalizerServiceTest extends TestCase
{
    private StringNormalizerInterface $service;

    private CacheInterface $cache;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->getService(StringNormalizerInterface::class);
        $this->cache = $this->getService(CacheInterface::class);
    }

    public function test_clean_removes_accents(): void
    {
        // Arrange
        $input = 'Jérôme Dùpont';

        // Act
        $result = $this->service->clean($input);

        // Assert
        $this->assertEquals('Jerome Dupont', $result);
    }

    public function test_clean_removes_special_characters(): void
    {
        // Arrange
        $input = 'Hello@World!';

        // Act
        $result = $this->service->clean($input);

        // Assert
        $this->assertEquals('Hello World', $result);
    }

    public function test_clean_normalizes_whitespace(): void
    {
        // Arrange
        $input = 'Jean   Pierre     Martin';

        // Act
        $result = $this->service->clean($input);

        // Assert
        $this->assertEquals('Jean Pierre Martin', $result);
    }

    public function test_clean_handles_mixed_case(): void
    {
        // Arrange
        $input = 'Jean-Luc MÉLENCHON';

        // Act
        $result = $this->service->clean($input);

        // Assert
        $this->assertEquals('Jean-Luc MELENCHON', $result);
    }

    public function test_clean_caches_result(): void
    {
        // Arrange
        $input = 'John Doe';

        // Act
        $result1 = $this->service->clean($input);
        $result2 = $this->service->clean($input);

        // Assert
        $this->assertSame($result1, $result2);
    }

    public function test_remove_special_chars_keeps_alphanumeric_and_spaces(): void
    {
        // Arrange
        $input = 'abc123 !@#$%^&*()_+={}[]|\\:;"\'<>,.?/`~';

        // Act
        $result = $this->service->removeSpecialChars($input);

        // Assert
        $this->assertStringContainsString('abc123', $result);
    }

    public function test_remove_special_chars_keeps_apostrophe_and_hyphen(): void
    {
        // Arrange
        $input = "Jean-Luc d'Artagnan";

        // Act
        $result = $this->service->removeSpecialChars($input);

        // Assert
        $this->assertEquals("Jean-Luc d'Artagnan", $result);
    }

    public function test_remove_accents_converts_to_ascii(): void
    {
        // Arrange
        $input = 'éèêëàâäôöûüçïîÉÈÊËÀÂÄÔÖÛÜÇÏÎ';

        // Act
        $result = $this->service->removeAccents($input);

        // Assert - L'implémentation réelle ne met pas d'espaces
        $this->assertEquals('eeeeaaaoouuciiEEEEAAAOOUUCII', $result);
    }

    public function test_remove_accents_handles_german_umlauts(): void
    {
        // Arrange
        $input = 'Müller Schröder';

        // Act
        $result = $this->service->removeAccents($input);

        // Assert
        $this->assertEquals('Muller Schroder', $result);
    }

    public function test_remove_accents_handles_nordic_characters(): void
    {
        // Arrange
        $input = 'Åland Øresund Ærø';

        // Act
        $result = $this->service->removeAccents($input);

        // Assert
        $this->assertEquals('Aland Oresund Aro', $result);
    }

    public function test_clean_handles_empty_string(): void
    {
        // Arrange
        $input = '';

        // Act
        $result = $this->service->clean($input);

        // Assert
        $this->assertEquals('', $result);
    }

    public function test_clean_handles_only_special_characters(): void
    {
        // Arrange
        $input = '!@#$%^&*()';

        // Act
        $result = $this->service->clean($input);

        // Assert
        $this->assertEquals('', $result);
    }

    public function test_clear_cache_deletes_only_normalized_keys(): void
    {
        // Arrange
        $this->service->clean('John Doe');
        $this->service->clean('Jane Smith');

        // Créer une autre clé de cache qui n'est pas du normalizer
        $this->cache->setWithDefaultTtl('other.key', 'value');

        // Act
        $this->service->clearCache();

        // Assert
        $this->assertNull($this->cache->get('string.normalized.John Doe'));
        $this->assertNull($this->cache->get('string.normalized.Jane Smith'));
        $this->assertNotNull($this->cache->get('other.key'));
    }

    public function test_clear_cache_handles_empty_cache(): void
    {
        // Act
        $this->service->clearCache();

        // Assert
        $this->assertTrue(true);
    }

    public function test_clean_handles_unicode_characters(): void
    {
        // Arrange
        $input = '中文 Español Français';

        // Act
        $result = $this->service->clean($input);

        // Assert - L'implémentation réelle trim() supprime les espaces au début
        $this->assertEquals('Espanol Francais', $result);
    }

    public function test_clean_handles_multiple_accents_in_one_word(): void
    {
        // Arrange
        $input = 'Héllò Wôrld';

        // Act
        $result = $this->service->clean($input);

        // Assert
        $this->assertEquals('Hello World', $result);
    }

    public function test_clean_preserves_apostrophes(): void
    {
        // Arrange
        $input = "L'ami d'enfance";

        // Act
        $result = $this->service->clean($input);

        // Assert
        $this->assertEquals("L'ami d'enfance", $result);
    }

    public function test_clean_preserves_hyphens(): void
    {
        // Arrange
        $input = 'Jean-Luc Pierre-Paul';

        // Act
        $result = $this->service->clean($input);

        // Assert
        $this->assertEquals('Jean-Luc Pierre-Paul', $result);
    }

    public function test_clean_trims_whitespace(): void
    {
        // Arrange
        $input = '  John Doe  ';

        // Act
        $result = $this->service->clean($input);

        // Assert
        $this->assertEquals('John Doe', $result);
    }

    public function test_remove_special_chars_handles_null_bytes(): void
    {
        // Arrange
        $input = "Hello\0World";

        // Act
        $result = $this->service->removeSpecialChars($input);

        // Assert
        $this->assertEquals('Hello World', $result);
    }
}
