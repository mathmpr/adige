<?php

namespace Adige\console\controllers;

use Adige\core\Adige;
use Adige\core\database\Connection;
use Adige\core\database\Migration;
use RuntimeException;

class MigrateController extends BaseController
{
    /**
     * create a new migration file
     * @param string $name the descriptive name used in the migration filename
     * @return string
     */
    public function actionCreate(string $name): string
    {
        $normalizedName = $this->normalizeMigrationName($name);
        $migrationName = date('Y_m_d_His') . '_' . $normalizedName;
        $path = $this->migrationPath();

        if (!is_dir($path) && !mkdir($path, 0777, true) && !is_dir($path)) {
            throw new RuntimeException("Could not create migrations directory: $path");
        }

        $filePath = $path . DIRECTORY_SEPARATOR . $migrationName . '.php';
        if (file_exists($filePath)) {
            throw new RuntimeException("Migration file already exists: $filePath");
        }

        file_put_contents($filePath, $this->migrationTemplate());

        return "Created migration $migrationName at $filePath\n";
    }

    /**
     * run all pending migrations in a single new batch
     * @return string
     */
    public function actionUp(): string
    {
        $pending = $this->resolvePendingMigrations();
        if ($pending === []) {
            return "No pending migrations.\n";
        }

        $batch = $this->nextBatchNumber();
        $lines = [];
        foreach ($pending as $migrationName => $filePath) {
            $migration = $this->loadMigrationInstance($migrationName, $filePath);
            $migration->executeUp();
            $this->markMigrationApplied($migrationName, $batch);
            $lines[] = "Applied $migrationName (batch $batch)";
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * revert the latest applied migration batch or the latest N batches
     * @param int|null $steps how many latest batches should be reverted (default is 1)
     * @return string
     */
    public function actionDown(?int $steps = null): string
    {
        $steps ??= 1;
        if ($steps < 1) {
            throw new RuntimeException('Steps must be greater than zero.');
        }

        $batches = $this->latestAppliedBatches($steps);
        if ($batches === []) {
            return "No applied migrations to revert.\n";
        }

        $available = $this->availableMigrationFiles();
        $lines = [];
        foreach ($batches as $batch) {
            foreach ($this->appliedMigrationsForBatch($batch) as $migrationName) {
                if (!isset($available[$migrationName])) {
                    throw new RuntimeException("Migration '$migrationName' was applied but its file is missing.");
                }

                $migration = $this->loadMigrationInstance($migrationName, $available[$migrationName]);
                $migration->executeDown();
                $this->removeMigrationRecord($migrationName);
                $lines[] = "Reverted $migrationName (batch $batch)";
            }
        }

        return implode("\n", $lines) . "\n";
    }

    protected function migrationConfig(): array
    {
        $config = Adige::$app->{Adige::MIGRATIONS_CONFIG} ?? [];
        return is_array($config) ? $config : [];
    }

    protected function migrationPath(): string
    {
        return rtrim($this->migrationConfig()['path'] ?? ROOT . 'migrations', DIRECTORY_SEPARATOR);
    }

    /**
     * @return array<string, string>
     */
    protected function availableMigrationFiles(): array
    {
        $path = $this->migrationPath();
        if (!is_dir($path)) {
            return [];
        }

        $files = [];
        foreach (scandir($path) ?: [] as $file) {
            if (!str_ends_with($file, '.php')) {
                continue;
            }

            $files[pathinfo($file, PATHINFO_FILENAME)] = $path . DIRECTORY_SEPARATOR . $file;
        }

        ksort($files);
        return $files;
    }

    protected function loadMigrationInstance(string $migrationName, string $filePath): Migration
    {
        $migration = require $filePath;
        if (!$migration instanceof Migration) {
            throw new RuntimeException("Migration file '$filePath' must return an instance of " . Migration::class);
        }

        return $migration->setConnection($this->migrationConnection());
    }

    protected function migrationConnection(): Connection
    {
        $connection = Adige::$app->{Adige::DB_HANDLER} ?? null;
        if (!$connection instanceof Connection) {
            throw new RuntimeException('Database connection is not configured for migrations.');
        }

        return $connection;
    }

    protected function ensureMetadataTable(): void
    {
        $connection = $this->migrationConnection();
        Migration::ensureMetadataTableFor($connection);
        if ($connection->getDb()?->inTransaction()) {
            $connection->commitTransaction();
        }
    }

    /**
     * @return array<int, string>
     */
    protected function appliedMigrationNames(): array
    {
        $this->ensureMetadataTable();
        $statement = $this->migrationConnection()
            ->getDb()
            ?->query('SELECT name FROM ' . Migration::MIGRATIONS_TABLE . ' ORDER BY name ASC');

        if ($statement === false || $statement === null) {
            return [];
        }

        return array_map(
            static fn (array $row): string => (string) $row['name'],
            $statement->fetchAll(\PDO::FETCH_ASSOC)
        );
    }

    protected function latestAppliedBatches(int $steps): array
    {
        $this->ensureMetadataTable();
        $statement = $this->migrationConnection()
            ->getDb()
            ?->prepare('SELECT DISTINCT batch FROM ' . Migration::MIGRATIONS_TABLE . ' ORDER BY batch DESC LIMIT :steps');

        if ($statement === false || $statement === null) {
            return [];
        }

        $statement->bindValue(':steps', $steps, \PDO::PARAM_INT);
        $statement->execute();

        return array_map(
            static fn (array $row): int => (int) $row['batch'],
            $statement->fetchAll(\PDO::FETCH_ASSOC)
        );
    }

    protected function markMigrationApplied(string $migrationName, int $batch): void
    {
        $this->ensureMetadataTable();
        $statement = $this->migrationConnection()
            ->getDb()
            ?->prepare('INSERT INTO ' . Migration::MIGRATIONS_TABLE . ' (name, batch, created_at) VALUES (:name, :batch, :created_at)');

        if ($statement === false || $statement === null) {
            throw new RuntimeException('Could not prepare migration insert statement.');
        }

        $statement->execute([
            'name' => $migrationName,
            'batch' => $batch,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    protected function removeMigrationRecord(string $migrationName): void
    {
        $this->ensureMetadataTable();
        $statement = $this->migrationConnection()
            ->getDb()
            ?->prepare('DELETE FROM ' . Migration::MIGRATIONS_TABLE . ' WHERE name = :name');

        if ($statement === false || $statement === null) {
            throw new RuntimeException('Could not prepare migration delete statement.');
        }

        $statement->execute([
            'name' => $migrationName,
        ]);
    }

    protected function resolvePendingMigrations(): array
    {
        $available = $this->availableMigrationFiles();
        $applied = array_flip($this->appliedMigrationNames());

        return array_filter(
            $available,
            static fn (string $migrationName): bool => !isset($applied[$migrationName]),
            ARRAY_FILTER_USE_KEY
        );
    }

    protected function normalizeMigrationName(string $name): string
    {
        $normalized = strtolower(trim($name));
        $normalized = preg_replace('/[^a-z0-9]+/', '_', $normalized) ?? '';
        $normalized = trim($normalized, '_');

        if ($normalized === '') {
            throw new RuntimeException('Migration name cannot be empty.');
        }

        return $normalized;
    }

    protected function normalizeRequestedMigrationName(string $name): string
    {
        return pathinfo(trim($name), PATHINFO_FILENAME);
    }

    /**
     * @return array<int, string>
     */
    protected function appliedMigrationsForBatch(int $batch): array
    {
        $this->ensureMetadataTable();
        $statement = $this->migrationConnection()
            ->getDb()
            ?->prepare('SELECT name FROM ' . Migration::MIGRATIONS_TABLE . ' WHERE batch = :batch ORDER BY id DESC');

        if ($statement === false || $statement === null) {
            return [];
        }

        $statement->execute([
            'batch' => $batch,
        ]);

        return array_map(
            static fn (array $row): string => (string) $row['name'],
            $statement->fetchAll(\PDO::FETCH_ASSOC)
        );
    }

    protected function nextBatchNumber(): int
    {
        $this->ensureMetadataTable();
        $statement = $this->migrationConnection()
            ->getDb()
            ?->query('SELECT MAX(batch) AS batch FROM ' . Migration::MIGRATIONS_TABLE);

        $row = $statement?->fetch(\PDO::FETCH_ASSOC);
        return ((int) ($row['batch'] ?? 0)) + 1;
    }

    protected function migrationTemplate(): string
    {
        return <<<PHP
<?php

use Adige\core\database\Migration;

return new class extends Migration {
    public function up(): void
    {
    }

    public function down(): void
    {
    }
};

PHP;
    }
}
