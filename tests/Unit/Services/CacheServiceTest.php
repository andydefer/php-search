<?php

declare(strict_types=1);

namespace AndyDefer\PhpSearch\Tests\Unit\Services;

use AndyDefer\PhpSearch\Contracts\Services\CacheInterface;
use AndyDefer\PhpSearch\Tests\TestCase;
use DateInterval;

final class CacheServiceTest extends TestCase
{
    private CacheInterface $cache;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cache = $this->getService(CacheInterface::class);
    }

    public function test_set_and_get_with_ttl(): void
    {
        // Arrange
        $key = 'key1';
        $value = 'value1';
        $ttl = 3600;

        // Act
        $this->cache->set($key, $value, $ttl);
        $result = $this->cache->get($key);

        // Assert
        $this->assertSame($value, $result);
    }

    public function test_set_with_interval(): void
    {
        // Arrange
        $key = 'key1';
        $value = 'value1';
        $interval = new DateInterval('PT1H');

        // Act
        $this->cache->setWithInterval($key, $value, $interval);
        $result = $this->cache->get($key);

        // Assert
        $this->assertSame($value, $result);
    }

    public function test_set_with_default_ttl(): void
    {
        // Arrange
        $key = 'key1';
        $value = 'value1';

        // Act
        $this->cache->setWithDefaultTtl($key, $value);
        $record = $this->cache->getRecord($key);

        // Assert
        $expectedExpiry = time() + 3600;
        $this->assertLessThanOrEqual(1, abs($record['expires'] - $expectedExpiry));
        $this->assertSame($value, $record['value']);
    }

    public function test_set_permanent(): void
    {
        // Arrange
        $key = 'permanent';
        $value = 'value1';

        // Act
        $this->cache->setPermanent($key, $value);
        $record = $this->cache->getRecord($key);

        // Assert
        $this->assertNull($record['expires']);
        $this->assertSame($value, $record['value']);
    }

    public function test_get_returns_default_when_key_not_found(): void
    {
        // Arrange
        $key = 'missing';
        $default = 'fallback';

        // Act
        $result = $this->cache->get($key, $default);

        // Assert
        $this->assertSame($default, $result);
    }

    public function test_get_returns_null_when_key_not_found_and_no_default(): void
    {
        // Arrange
        $key = 'nonexistent';

        // Act
        $result = $this->cache->get($key);

        // Assert
        $this->assertNull($result);
    }

    public function test_delete_removes_key_from_cache(): void
    {
        // Arrange
        $key = 'key1';
        $this->cache->setPermanent($key, 'value1');
        $this->assertTrue($this->cache->has($key));

        // Act
        $this->cache->delete($key);

        // Assert
        $this->assertFalse($this->cache->has($key));
        $this->assertNull($this->cache->get($key));
    }

    public function test_clear_removes_all_keys_from_cache(): void
    {
        // Arrange
        $this->cache->setPermanent('key1', 'value1');
        $this->cache->setPermanent('key2', 'value2');
        $this->assertTrue($this->cache->has('key1'));
        $this->assertTrue($this->cache->has('key2'));

        // Act
        $this->cache->clear();

        // Assert
        $this->assertFalse($this->cache->has('key1'));
        $this->assertFalse($this->cache->has('key2'));
    }

    public function test_has_returns_true_when_key_exists(): void
    {
        // Arrange
        $key = 'key1';
        $this->cache->setPermanent($key, 'value1');

        // Act
        $result = $this->cache->has($key);

        // Assert
        $this->assertTrue($result);
    }

    public function test_has_returns_false_when_key_does_not_exist(): void
    {
        // Arrange
        $key = 'key1';

        // Act
        $result = $this->cache->has($key);

        // Assert
        $this->assertFalse($result);
    }

    public function test_get_multiple_returns_values_with_default_for_missing_keys(): void
    {
        // Arrange
        $this->cache->setPermanent('key1', 'value1');
        $this->cache->setPermanent('key2', 'value2');
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

    public function test_set_multiple_stores_multiple_key_value_pairs(): void
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
        $this->cache->setPermanent('key1', 'value1');
        $this->cache->setPermanent('key2', 'value2');
        $this->cache->setPermanent('key3', 'value3');

        // Act
        $this->cache->deleteMultiple(['key1', 'key3']);

        // Assert
        $this->assertFalse($this->cache->has('key1'));
        $this->assertTrue($this->cache->has('key2'));
        $this->assertFalse($this->cache->has('key3'));
    }

    public function test_get_record_returns_array_with_value_and_expires(): void
    {
        // Arrange
        $key = 'key1';
        $value = 'value1';
        $this->cache->setPermanent($key, $value);

        // Act
        $record = $this->cache->getRecord($key);

        // Assert
        $this->assertIsArray($record);
        $this->assertArrayHasKey('value', $record);
        $this->assertArrayHasKey('expires', $record);
        $this->assertSame($value, $record['value']);
        $this->assertNull($record['expires']);
    }

    public function test_get_raw_returns_json_encoded_value(): void
    {
        // Arrange
        $key = 'key1';
        $value = 'value1';
        $this->cache->setPermanent($key, $value);

        // Act
        $raw = $this->cache->getRaw($key);

        // Assert
        $this->assertIsString($raw);
        $this->assertJson($raw);
        $this->assertSame('"value1"', $raw);
    }

    public function test_get_raw_returns_null_when_key_not_found(): void
    {
        // Arrange
        $key = 'nonexistent';

        // Act
        $raw = $this->cache->getRaw($key);

        // Assert
        $this->assertNull($raw);
    }

    public function test_expiration_with_int_ttl_removes_key_after_ttl(): void
    {
        // Arrange
        $key = 'expiring';
        $value = 'value';
        $ttl = 1;

        // Act
        $this->cache->set($key, $value, $ttl);
        $this->simulateTimeTravel(2);
        $result = $this->cache->get($key);

        // Assert
        $this->assertNull($result);
        $this->assertFalse($this->cache->has($key));
    }

    public function test_expiration_with_date_interval_ttl_removes_key_after_ttl(): void
    {
        // Arrange
        $key = 'expiring';
        $value = 'value';
        $interval = new DateInterval('PT1S');

        // Act
        $this->cache->setWithInterval($key, $value, $interval);
        $this->simulateTimeTravel(2);
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
            $this->cache->setPermanent($key, $value);
            $this->assertEquals($value, $this->cache->get($key));
        }
    }

    public function test_store_object_returns_same_object(): void
    {
        // Arrange
        $key = 'object';
        $value = new \stdClass;
        $value->property = 'test';

        // Act
        $this->cache->setPermanent($key, $value);
        $result = $this->cache->get($key);

        // Assert
        $this->assertEquals($value, $result);
        $this->assertSame($value->property, $result->property);
    }

    /**
     * Helper method to simulate time travel by manipulating the cache directly.
     */
    private function simulateTimeTravel(int $seconds): void
    {
        $reflection = new \ReflectionClass($this->cache);
        $property = $reflection->getProperty('cache');

        $cacheData = $property->getValue($this->cache);

        foreach ($cacheData as &$record) {
            if ($record['expires'] !== null) {
                $record['expires'] -= $seconds;
            }
        }

        $property->setValue($this->cache, $cacheData);
    }
}
