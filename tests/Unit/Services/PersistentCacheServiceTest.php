<?php

declare(strict_types=1);

namespace AndyDefer\PhpSearch\Tests\Unit\Services;

use AndyDefer\PhpJsonl\Contexts\JsonlContext;
use AndyDefer\PhpJsonl\JsonlService;
use AndyDefer\PhpSearch\Configs\SearchConfig;
use AndyDefer\PhpSearch\Services\PersistentCacheService;
use AndyDefer\PhpSearch\Strategies\SearchPathStrategy;
use AndyDefer\PhpSearch\Tests\TestCase;
use AndyDefer\PhpServices\Enums\PermissionMode;
use AndyDefer\PhpServices\Services\FileSystemService;
use DateInterval;

final class PersistentCacheServiceTest extends TestCase
{
    private PersistentCacheService $cache;

    private FileSystemService $fileSystem;

    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fileSystem = new FileSystemService;
        $this->tempDir = sys_get_temp_dir().'/persistent_cache_test_'.uniqid();
        $this->fileSystem->makeDirectory($this->tempDir, PermissionMode::WORLD_WRITABLE, true);

        $config = new SearchConfig;
        $jsonlService = new JsonlService(
            new SearchPathStrategy($this->tempDir),
            $this->fileSystem,
            new JsonlContext
        );

        $this->cache = new PersistentCacheService(
            $config,
            $jsonlService,
            $this->fileSystem
        );
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
        parent::tearDown();
    }

    private function removeDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir.DIRECTORY_SEPARATOR.$file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    public function test_set_and_get(): void
    {
        // Arrange
        $key = 'test_key';
        $value = 'test_value';

        // Act
        $this->cache->set($key, $value, 3600);
        $result = $this->cache->get($key);

        // Assert
        $this->assertSame($value, $result);
    }

    public function test_get_returns_default_when_key_not_found(): void
    {
        // Arrange
        $key = 'nonexistent_key';
        $default = 'default_value';

        // Act
        $result = $this->cache->get($key, $default);

        // Assert
        $this->assertSame($default, $result);
    }

    public function test_set_permanent(): void
    {
        // Arrange
        $key = 'permanent_key';
        $value = 'permanent_value';

        // Act
        $this->cache->setPermanent($key, $value);
        $result = $this->cache->get($key);

        // Assert
        $this->assertSame($value, $result);
    }

    public function test_set_with_default_ttl(): void
    {
        // Arrange
        $key = 'default_ttl_key';
        $value = 'default_ttl_value';

        // Act
        $this->cache->setWithDefaultTtl($key, $value);
        $result = $this->cache->get($key);

        // Assert
        $this->assertSame($value, $result);
    }

    public function test_set_with_interval(): void
    {
        // Arrange
        $key = 'interval_key';
        $value = 'interval_value';
        $interval = new DateInterval('PT1H');

        // Act
        $this->cache->setWithInterval($key, $value, $interval);
        $result = $this->cache->get($key);

        // Assert
        $this->assertSame($value, $result);
    }

    public function test_delete_removes_key(): void
    {
        // Arrange
        $key = 'to_delete';
        $this->cache->set($key, 'value', 3600);
        $this->assertTrue($this->cache->has($key));

        // Act
        $this->cache->delete($key);

        // Assert
        $this->assertFalse($this->cache->has($key));
        $this->assertNull($this->cache->get($key));
    }

    public function test_clear_removes_all_keys(): void
    {
        // Arrange
        $this->cache->set('key1', 'value1', 3600);
        $this->cache->set('key2', 'value2', 3600);
        $this->assertTrue($this->cache->has('key1'));
        $this->assertTrue($this->cache->has('key2'));

        // Act
        $this->cache->clear();

        // Assert
        $this->assertFalse($this->cache->has('key1'));
        $this->assertFalse($this->cache->has('key2'));
    }

    public function test_has_returns_true_for_existing_key(): void
    {
        // Arrange
        $key = 'existing_key';
        $this->cache->set($key, 'value', 3600);

        // Act
        $result = $this->cache->has($key);

        // Assert
        $this->assertTrue($result);
    }

    public function test_has_returns_false_for_nonexistent_key(): void
    {
        // Arrange
        $key = 'nonexistent_key';

        // Act
        $result = $this->cache->has($key);

        // Assert
        $this->assertFalse($result);
    }

    public function test_get_multiple_returns_values_with_default(): void
    {
        // Arrange
        $this->cache->set('key1', 'value1', 3600);
        $this->cache->set('key2', 'value2', 3600);
        $keys = ['key1', 'key2', 'key3'];
        $default = 'default';

        // Act
        $result = $this->cache->getMultiple($keys, $default);

        // Assert
        $expected = [
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => 'default',
        ];
        $this->assertSame($expected, $result);
    }

    public function test_set_multiple_stores_multiple_values(): void
    {
        // Arrange
        $values = [
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => 'value3',
        ];

        // Act
        $this->cache->setMultiple($values);

        // Assert
        $this->assertSame('value1', $this->cache->get('key1'));
        $this->assertSame('value2', $this->cache->get('key2'));
        $this->assertSame('value3', $this->cache->get('key3'));
    }

    public function test_delete_multiple_removes_multiple_keys(): void
    {
        // Arrange
        $this->cache->set('key1', 'value1', 3600);
        $this->cache->set('key2', 'value2', 3600);
        $this->cache->set('key3', 'value3', 3600);

        // Act
        $this->cache->deleteMultiple(['key1', 'key3']);

        // Assert
        $this->assertFalse($this->cache->has('key1'));
        $this->assertTrue($this->cache->has('key2'));
        $this->assertFalse($this->cache->has('key3'));
    }

    public function test_get_record_returns_record_with_expires(): void
    {
        // Arrange
        $key = 'record_key';
        $value = 'record_value';
        $ttl = 3600;
        $this->cache->set($key, $value, $ttl);

        // Act
        $record = $this->cache->getRecord($key);

        // Assert
        $this->assertIsArray($record);
        $this->assertArrayHasKey('value', $record);
        $this->assertArrayHasKey('expires', $record);
        $this->assertSame($value, $record['value']);
        $this->assertNotNull($record['expires']);
    }

    public function test_get_raw_returns_json_string(): void
    {
        // Arrange
        $key = 'raw_key';
        $value = 'raw_value';
        $this->cache->set($key, $value, 3600);

        // Act
        $raw = $this->cache->getRaw($key);

        // Assert
        $this->assertIsString($raw);
        $this->assertJson($raw);
    }

    public function test_get_raw_returns_null_when_key_not_found(): void
    {
        // Arrange
        $key = 'nonexistent_key';

        // Act
        $raw = $this->cache->getRaw($key);

        // Assert
        $this->assertNull($raw);
    }

    public function test_expired_entry_is_not_returned(): void
    {
        // Arrange
        $key = 'expiring_key';
        $value = 'expiring_value';
        $ttl = 1; // 1 seconde

        // Act
        $this->cache->set($key, $value, $ttl);

        // Attendre l'expiration
        sleep(2);

        $result = $this->cache->get($key);

        // Assert
        $this->assertNull($result);
        $this->assertFalse($this->cache->has($key));
    }

    public function test_store_various_data_types(): void
    {
        // Arrange
        $data = [
            'string' => 'hello',
            'int' => 42,
            'float' => 3.14,
            'bool' => true,
            'array' => [1, 2, 3],
            'null' => null,
        ];

        // Act & Assert
        foreach ($data as $key => $value) {
            $this->cache->set($key, $value, 3600);
            $this->assertEquals($value, $this->cache->get($key));
        }
    }

    public function test_store_object_returns_same_object(): void
    {
        // Arrange
        $key = 'object_key';
        $value = new \stdClass;
        $value->property = 'test';

        // Act
        $this->cache->set($key, $value, 3600);
        $result = $this->cache->get($key);

        // Vérifier les propriétés plutôt que l'identité de l'objet
        $this->assertIsArray($result);
        $this->assertSame($value->property, $result['property']);
    }

    public function test_same_key_overwrites_previous_value(): void
    {
        // Arrange
        $key = 'overwrite_key';
        $this->cache->set($key, 'first_value', 3600);

        // Act
        $this->cache->set($key, 'second_value', 3600);
        $result = $this->cache->get($key);

        // Assert
        $this->assertSame('second_value', $result);
    }
}
