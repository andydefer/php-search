<?php

declare(strict_types=1);

namespace AndyDefer\PhpSearch\Tests\Unit\Services;

use AndyDefer\PhpSearch\Contracts\Services\DatasetPreprocessorInterface;
use AndyDefer\PhpSearch\Tests\TestCase;

final class DatasetPreprocessorServiceTest extends TestCase
{
    private DatasetPreprocessorInterface $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->getService(DatasetPreprocessorInterface::class);
    }

    public function test_set_data_stores_raw_and_preprocessed_in_cache(): void
    {
        // Arrange
        $data = ['John Doe', 'Jane Smith'];

        // Act
        $this->service->setData($data);

        // Assert
        $rawData = $this->service->getRawData();
        $preprocessed = $this->service->getPreprocessed();

        $this->assertSame($data, $rawData);
        $this->assertCount(2, $preprocessed);
        $this->assertArrayHasKey('John Doe', $preprocessed);
        $this->assertArrayHasKey('Jane Smith', $preprocessed);
    }

    public function test_get_raw_data_returns_array_from_cache(): void
    {
        // Arrange
        $data = ['John Doe', 'Jane Smith'];
        $this->service->setData($data);

        // Act
        $result = $this->service->getRawData();

        // Assert
        $this->assertSame($data, $result);
    }

    public function test_get_raw_data_returns_empty_array_when_no_data(): void
    {
        // Act
        $result = $this->service->getRawData();

        // Assert
        $this->assertSame([], $result);
    }

    public function test_get_preprocessed_returns_array_from_cache(): void
    {
        // Arrange
        $data = ['John Doe'];
        $this->service->setData($data);

        // Act
        $result = $this->service->getPreprocessed();

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('John Doe', $result);
    }

    public function test_get_preprocessed_returns_empty_array_when_no_data(): void
    {
        // Act
        $result = $this->service->getPreprocessed();

        // Assert
        $this->assertSame([], $result);
    }

    public function test_clear_cache_deletes_both_cache_keys(): void
    {
        // Arrange
        $data = ['John Doe'];
        $this->service->setData($data);

        // Act
        $this->service->clearCache();

        // Assert
        $this->assertSame([], $this->service->getRawData());
        $this->assertSame([], $this->service->getPreprocessed());
    }

    public function test_set_data_processes_items_correctly(): void
    {
        // Arrange
        $data = ['John Doe'];

        // Act
        $this->service->setData($data);
        $preprocessed = $this->service->getPreprocessed();

        // Assert
        $this->assertIsArray($preprocessed['John Doe']);

        foreach ($preprocessed['John Doe'] as $wordData) {
            $this->assertArrayHasKey('original', $wordData);
            $this->assertArrayHasKey('normalized', $wordData);
            $this->assertArrayHasKey('max_score', $wordData);
            $this->assertArrayHasKey('ngrams', $wordData);
            $this->assertIsFloat($wordData['max_score']);
            $this->assertIsArray($wordData['ngrams']);
        }
    }

    public function test_set_data_handles_multiple_words(): void
    {
        // Arrange
        $data = ['John Doe Jean'];

        // Act
        $this->service->setData($data);
        $preprocessed = $this->service->getPreprocessed();

        // Assert
        $this->assertCount(3, $preprocessed['John Doe Jean']);
    }

    public function test_set_data_handles_special_characters(): void
    {
        // Arrange
        $data = ['Jean-Luc Mélenchon'];

        // Act
        $this->service->setData($data);
        $preprocessed = $this->service->getPreprocessed();

        // Assert
        $this->assertIsArray($preprocessed['Jean-Luc Mélenchon']);
    }

    public function test_set_data_handles_empty_string(): void
    {
        // Arrange
        $data = [''];

        // Act
        $this->service->setData($data);
        $preprocessed = $this->service->getPreprocessed();

        // Assert
        $this->assertIsArray($preprocessed['']);
    }

    public function test_set_data_overwrites_previous_data(): void
    {
        // Arrange
        $firstData = ['John Doe'];
        $secondData = ['Jane Smith'];

        // Act
        $this->service->setData($firstData);
        $this->service->setData($secondData);

        // Assert
        $this->assertSame($secondData, $this->service->getRawData());
        $this->assertArrayHasKey('Jane Smith', $this->service->getPreprocessed());
        $this->assertArrayNotHasKey('John Doe', $this->service->getPreprocessed());
    }

    public function test_set_data_handles_large_dataset(): void
    {
        // Arrange
        $data = [];
        for ($i = 0; $i < 100; $i++) {
            $data[] = "Name $i Surname $i";
        }

        // Act
        $this->service->setData($data);
        $rawData = $this->service->getRawData();
        $preprocessed = $this->service->getPreprocessed();

        // Assert
        $this->assertCount(100, $rawData);
        $this->assertCount(100, $preprocessed);
    }
}
