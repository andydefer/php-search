<?php

declare(strict_types=1);

namespace AndyDefer\PhpSearch\Tests\Functional;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class SearchEngineCliTest extends TestCase
{
    private string $binPath;

    private string $tempDir;

    private string $dataDir;

    private string $testDataPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->binPath = __DIR__.'/../../bin/php-search';
        $this->tempDir = sys_get_temp_dir().'/search_test_'.uniqid();
        $this->dataDir = $this->tempDir.'/data';
        $this->testDataPath = $this->tempDir.'/test_data.jsonl';

        mkdir($this->tempDir, 0777, true);
        mkdir($this->dataDir, 0777, true);
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

    private function createJsonlFile(string $filePath, array $lines): void
    {
        $content = '';
        foreach ($lines as $line) {
            $content .= json_encode($line)."\n";
        }
        file_put_contents($filePath, $content);
    }

    private function runCommand(array $arguments): Process
    {
        $command = array_merge(['php', $this->binPath], $arguments);
        $process = new Process($command);
        $process->setWorkingDirectory($this->tempDir);
        $process->run();

        return $process;
    }

    // ============================================================
    // Tests d'indexation
    // ============================================================

    public function test_index_directory(): void
    {
        // Arrange
        $this->createJsonlFile($this->dataDir.'/artists.jsonl', [
            ['name' => 'Leonard Cohen', 'id' => 1],
            ['name' => 'Leonardo DiCaprio', 'id' => 2],
            ['name' => 'Bob Dylan', 'id' => 3],
        ]);

        // Act
        $process = $this->runCommand(['index', $this->dataDir]);

        // Assert
        $this->assertEquals(0, $process->getExitCode());
        $this->assertStringContainsString('Indexation de:', $process->getOutput());
        $this->assertStringContainsString('✅ Indexation terminée:', $process->getOutput());
    }

    public function test_index_single_file(): void
    {
        // Arrange
        $filePath = $this->dataDir.'/data.jsonl';
        $this->createJsonlFile($filePath, [
            ['name' => 'John Doe', 'id' => 1],
            ['name' => 'Jane Smith', 'id' => 2],
        ]);

        // Act
        $process = $this->runCommand(['index', $filePath]);

        // Assert
        $this->assertEquals(0, $process->getExitCode());
        $this->assertStringContainsString('Indexation de:', $process->getOutput());
        $this->assertStringContainsString('✅ Indexation terminée:', $process->getOutput());
    }

    public function test_index_fails_with_invalid_source(): void
    {
        // Act
        $process = $this->runCommand(['index', '/nonexistent/path']);

        // Assert - Le message est sur STDOUT, pas STDERR
        $this->assertEquals(1, $process->getExitCode());
        $this->assertStringContainsString('Source invalide:', $process->getOutput());
    }

    // ============================================================
    // Tests de recherche
    // ============================================================

    public function test_search_returns_results(): void
    {
        // Arrange
        $this->createJsonlFile($this->dataDir.'/data.jsonl', [
            ['name' => 'Leonard Cohen'],
            ['name' => 'Leonardo DiCaprio'],
            ['name' => 'Bob Dylan'],
        ]);

        $this->runCommand(['index', $this->dataDir]);

        // Act
        $process = $this->runCommand(['Leonard', '5']);

        // Assert
        $this->assertEquals(0, $process->getExitCode());
        $this->assertStringContainsString('Top', $process->getOutput());
        $this->assertStringContainsString('Leonard', $process->getOutput());
    }

    public function test_search_with_custom_limit(): void
    {
        // Arrange
        $this->createJsonlFile($this->dataDir.'/data.jsonl', [
            ['name' => 'Leonard Cohen'],
            ['name' => 'Leonardo DiCaprio'],
            ['name' => 'Leonard Nimoy'],
            ['name' => 'Leonard Bernstein'],
        ]);

        $this->runCommand(['index', $this->dataDir]);

        // Act
        $process = $this->runCommand(['Leonard', '2']);

        // Assert
        $this->assertEquals(0, $process->getExitCode());
        $this->assertStringContainsString('Top 2 results', $process->getOutput());
    }

    public function test_search_with_source_option(): void
    {
        // Arrange
        $this->createJsonlFile($this->dataDir.'/artists.jsonl', [
            ['name' => 'Leonard Cohen'],
            ['name' => 'Bob Dylan'],
        ]);

        $this->runCommand(['index', $this->dataDir]);

        // Act
        $process = $this->runCommand(['Leonard', '5', "--source={$this->dataDir}"]);

        // Assert
        $this->assertEquals(0, $process->getExitCode());
        $this->assertStringContainsString('Leonard', $process->getOutput());
    }

    public function test_search_without_index_returns_error(): void
    {
        // Act
        $process = $this->runCommand(['Leonard', '5']);

        // Assert - Le message d'erreur est sur STDOUT
        $this->assertEquals(1, $process->getExitCode());
        $this->assertStringContainsString('Error: Aucune source indexée', $process->getOutput());
    }

    public function test_search_with_typo_returns_results(): void
    {
        // Arrange
        $this->createJsonlFile($this->dataDir.'/data.jsonl', [
            ['name' => 'Leonard Cohen'],
            ['name' => 'Lenny Kravitz'],
        ]);

        $this->runCommand(['index', $this->dataDir]);

        // Act
        $process = $this->runCommand(['Leonerd', '5']);

        // Assert
        $this->assertEquals(0, $process->getExitCode());
        $this->assertStringContainsString('Leonard Cohen', $process->getOutput());
    }

    // ============================================================
    // Tests de gestion des index
    // ============================================================

    public function test_list_indexes(): void
    {
        // Arrange
        $this->createJsonlFile($this->dataDir.'/data.jsonl', [
            ['name' => 'Test'],
        ]);
        $this->runCommand(['index', $this->dataDir]);

        // Act
        $process = $this->runCommand(['--list-indexes']);

        // Assert
        $this->assertEquals(0, $process->getExitCode());
        $this->assertStringContainsString('Index disponibles:', $process->getOutput());
    }

    public function test_list_indexes_when_no_index(): void
    {
        // Act
        $process = $this->runCommand(['--list-indexes']);

        // Assert
        $this->assertEquals(0, $process->getExitCode());
        $this->assertStringContainsString('❌ Aucun index trouvé', $process->getOutput());
    }

    public function test_get_stats(): void
    {
        // Arrange
        $this->createJsonlFile($this->dataDir.'/data.jsonl', [
            ['name' => 'Test 1'],
            ['name' => 'Test 2'],
        ]);
        $this->runCommand(['index', $this->dataDir]);

        // Act
        $process = $this->runCommand(['--stats']);

        // Assert
        $this->assertEquals(0, $process->getExitCode());
        $this->assertStringContainsString('Statistiques de l\'index:', $process->getOutput());
        $this->assertStringContainsString('Éléments indexés: 2', $process->getOutput());
    }

    public function test_get_stats_with_source_option(): void
    {
        // Arrange
        $this->createJsonlFile($this->dataDir.'/data.jsonl', [
            ['name' => 'Test'],
        ]);
        $this->runCommand(['index', $this->dataDir]);

        // Act
        $process = $this->runCommand(['--stats', "--source={$this->dataDir}"]);

        // Assert
        $this->assertEquals(0, $process->getExitCode());
        $this->assertStringContainsString('Statistiques de l\'index:', $process->getOutput());
    }

    public function test_delete_index(): void
    {
        // Arrange
        $this->createJsonlFile($this->dataDir.'/data.jsonl', [['name' => 'Test']]);
        $this->runCommand(['index', $this->dataDir]);

        // Vérifier que l'index existe AVEC --source explicite
        $stats = $this->runCommand(['--stats', "--source={$this->dataDir}"]);
        $this->assertStringContainsString('Existe: ✅ Oui', $stats->getOutput());

        // Act
        $process = $this->runCommand(['--delete-index', "--source={$this->dataDir}"]);

        // Assert
        $this->assertEquals(0, $process->getExitCode());
        $this->assertStringContainsString('Index supprimé', $process->getOutput());

        // Vérifier que l'index a été supprimé AVEC --source explicite
        $statsAfter = $this->runCommand(['--stats', "--source={$this->dataDir}"]);
        $this->assertStringContainsString('Existe: ❌ Non', $statsAfter->getOutput());
    }

    public function test_delete_index_with_source_option(): void
    {
        // Arrange
        $this->createJsonlFile($this->dataDir.'/data.jsonl', [
            ['name' => 'Test'],
        ]);
        $this->runCommand(['index', $this->dataDir]);

        // Act
        $process = $this->runCommand(['--delete-index', "--source={$this->dataDir}"]);

        // Assert
        $this->assertEquals(0, $process->getExitCode());
        $this->assertStringContainsString("Index supprimé pour la source '{$this->dataDir}'", $process->getOutput());
    }

    public function test_clear_cache(): void
    {
        // Arrange
        $this->createJsonlFile($this->dataDir.'/data.jsonl', [
            ['name' => 'Leonard Cohen'],
        ]);
        $this->runCommand(['index', $this->dataDir]);

        // Première recherche (remplit le cache)
        $this->runCommand(['Leonard', '5']);

        // Act
        $process = $this->runCommand(['--clear-cache']);

        // Assert
        $this->assertEquals(0, $process->getExitCode());
        $this->assertStringContainsString('Cache vidé', $process->getOutput());
    }

    public function test_clear_cache_with_source_option(): void
    {
        // Arrange
        $this->createJsonlFile($this->dataDir.'/data.jsonl', [
            ['name' => 'Leonard Cohen'],
        ]);
        $this->runCommand(['index', $this->dataDir]);

        // Act
        $process = $this->runCommand(['--clear-cache', "--source={$this->dataDir}"]);

        // Assert
        $this->assertEquals(0, $process->getExitCode());
        $this->assertStringContainsString("Cache vidé pour la source '{$this->dataDir}'", $process->getOutput());
    }

    public function test_clear_cache_with_query_option(): void
    {
        // Arrange
        $this->createJsonlFile($this->dataDir.'/data.jsonl', [
            ['name' => 'Leonard Cohen'],
            ['name' => 'Bob Dylan'],
        ]);
        $this->runCommand(['index', $this->dataDir]);

        // Première recherche (remplit le cache)
        $this->runCommand(['Leonard', '5']);

        // Act
        $process = $this->runCommand(['--clear-cache', '--query=Leonard']);

        // Assert
        $this->assertEquals(0, $process->getExitCode());
        $this->assertStringContainsString('Cache vidé pour la requête', $process->getOutput());
    }

    // ============================================================
    // Tests d'intégration
    // ============================================================

    public function test_full_workflow(): void
    {
        // 1. Créer les données
        $this->createJsonlFile($this->dataDir.'/artists.jsonl', [
            ['name' => 'Leonard Cohen', 'id' => 1],
            ['name' => 'Leonardo DiCaprio', 'id' => 2],
            ['name' => 'Bob Dylan', 'id' => 3],
        ]);

        // 2. Indexer
        $indexProcess = $this->runCommand(['index', $this->dataDir]);
        $this->assertEquals(0, $indexProcess->getExitCode());
        $this->assertStringContainsString('Indexation terminée: 3 éléments', $indexProcess->getOutput());

        // 3. Lister les index
        $listProcess = $this->runCommand(['--list-indexes']);
        $this->assertEquals(0, $listProcess->getExitCode());
        $this->assertStringContainsString('Index disponibles', $listProcess->getOutput());

        // 4. Rechercher
        $searchProcess = $this->runCommand(['Leonard', '5']);
        $this->assertEquals(0, $searchProcess->getExitCode());
        $this->assertStringContainsString('Leonard Cohen', $searchProcess->getOutput());

        // 5. Voir les stats
        $statsProcess = $this->runCommand(['--stats']);
        $this->assertEquals(0, $statsProcess->getExitCode());
        $this->assertStringContainsString('Éléments indexés: 3', $statsProcess->getOutput());

        // 6. Nettoyer
        $clearProcess = $this->runCommand(['--clear-cache']);
        $this->assertEquals(0, $clearProcess->getExitCode());

        // 7. Supprimer l'index
        $deleteProcess = $this->runCommand(['--delete-index']);
        $this->assertEquals(0, $deleteProcess->getExitCode());
    }

    public function test_multiple_indexes(): void
    {
        // Arrange - Premier index
        $artistsDir = $this->dataDir.'/artists';
        mkdir($artistsDir);
        $this->createJsonlFile($artistsDir.'/artists.jsonl', [
            ['name' => 'Leonard Cohen'],
            ['name' => 'Bob Dylan'],
        ]);

        // Deuxième index
        $actorsDir = $this->dataDir.'/actors';
        mkdir($actorsDir);
        $this->createJsonlFile($actorsDir.'/actors.jsonl', [
            ['name' => 'Leonardo DiCaprio'],
            ['name' => 'Brad Pitt'],
        ]);

        // Act
        $this->runCommand(['index', $artistsDir]);
        $this->runCommand(['index', $actorsDir]);

        // Assert - Recherche dans chaque index
        $artistsSearch = $this->runCommand(['Leonard', '5', "--source={$artistsDir}"]);
        $actorsSearch = $this->runCommand(['Leonardo', '5', "--source={$actorsDir}"]);

        $this->assertStringContainsString('Leonard Cohen', $artistsSearch->getOutput());
        $this->assertStringContainsString('Leonardo DiCaprio', $actorsSearch->getOutput());
    }

    // ============================================================
    // Tests d'aide
    // ============================================================

    public function test_help_is_displayed_when_no_arguments(): void
    {
        // Act
        $process = $this->runCommand([]);

        // Assert
        $this->assertEquals(1, $process->getExitCode());
        $this->assertStringContainsString('PHP Fuzzy Search Engine - CLI Tool', $process->getOutput());
    }

    public function test_help_is_displayed_when_only_one_argument(): void
    {
        // Act
        $process = $this->runCommand(['invalid']);

        // Assert
        $this->assertEquals(1, $process->getExitCode());
        // Soit l'aide, soit un message d'erreur
        $this->assertTrue(
            str_contains($process->getOutput(), 'PHP Fuzzy Search Engine - CLI Tool') ||
            str_contains($process->getOutput(), 'Error:')
        );
    }
}
