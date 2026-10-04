<?php

namespace Core\Console\Commands;

use Core\Console\Commands\BaseCommand;

class DatabaseCommand extends BaseCommand
{
    protected $migrationsPath = 'app/Database/migrations';
    protected $seedersPath = 'app/Database/Seeders';
    protected $migrationTable = 'migrations';

    public function handle(array $argv)
    {
        $command = $argv[1] ?? '';

        // Show help if requested
        if ($this->hasOption($argv, 'help') || $this->hasOption($argv, 'h')) {
            $this->showDatabaseHelp($command);
            return;
        }

        switch ($command) {
            case 'db:migrate':
                $this->runMigrations($argv);
                break;
            case 'db:rollback':
                $this->rollbackMigrations($argv);
                break;
            case 'db:seed':
                $this->runSeeders($argv);
                break;
            case 'db:reset':
                $this->resetDatabase($argv);
                break;
            case 'db:status':
                $this->showMigrationStatus();
                break;
            case 'db:fresh':
                $this->freshDatabase($argv);
                break;
            case 'db:backup':
                $this->backupDatabase($argv);
                break;
            default:
                $this->error("Unknown database command: {$command}");
                $this->showDatabaseHelp('');
        }
    }

    protected function runMigrations($argv)
    {
        $this->info("🗃️  Running database migrations...");
        echo str_repeat('─', 60) . "\n";

        if (!is_dir($this->migrationsPath)) {
            $this->error("Migrations directory not found: {$this->migrationsPath}");
            return;
        }

        // Create migrations table if not exists
        $this->createMigrationsTable();

        // Get all migration files
        $migrations = $this->getAllMigrations();

        if (empty($migrations)) {
            $this->warning("No migration files found.");
            return;
        }

        // Get already run migrations
        $ranMigrations = $this->getRanMigrations();

        // Filter pending migrations
        $pendingMigrations = array_filter($migrations, function ($migration) use ($ranMigrations) {
            return !in_array($migration['name'], $ranMigrations);
        });

        if (empty($pendingMigrations)) {
            $this->info("✅ Nothing to migrate. All migrations are up to date.");
            return;
        }

        $this->info("Found " . count($pendingMigrations) . " pending migration(s).\n");

        $batch = $this->getNextBatchNumber();
        $migrated = 0;

        $pdo = $this->getConnection();
        // SQLite and PostgreSQL support transactional DDL: a failing migration leaves no half-applied schema.
        $transactional = in_array($this->driverName($pdo), ['sqlite', 'pgsql'], true);

        foreach ($pendingMigrations as $migration) {
            try {
                $this->info("Migrating: {$migration['name']}");

                if ($transactional) {
                    $pdo->beginTransaction();
                }

                $this->executeMigration($migration['path'], 'up');
                $this->recordMigration($migration['name'], $batch);

                if ($transactional && $pdo->inTransaction()) {
                    $pdo->commit();
                }

                $this->success("✓ Migrated: {$migration['name']}");
                $migrated++;
            } catch (\Throwable $e) {
                if ($transactional && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $this->error("✗ Failed: {$migration['name']}");
                $this->error("Error: " . $e->getMessage());

                if ($this->hasOption($argv, 'force')) {
                    $this->warning("Continuing due to --force flag...");
                    continue;
                }

                $this->warning("Migration stopped. Use --force to continue on errors.");
                break;
            }
        }

        echo str_repeat('─', 60) . "\n";
        if ($this->failed) {
            $this->error("Migration finished with errors ({$migrated} migrated).");
        } else {
            $this->success("✅ Migration completed! ({$migrated} migrations)");
        }
    }

    protected function rollbackMigrations($argv)
    {
        $step = (int) ($this->getOptionValue($argv, 'step') ?? 1);

        $this->info("🔄 Rolling back database migrations...");
        echo str_repeat('─', 60) . "\n";

        // Get migrations to rollback
        $migrations = $this->getMigrationsToRollback($step);

        if (empty($migrations)) {
            $this->warning("Nothing to rollback.");
            return;
        }

        $this->info("Rolling back {$step} batch(es): " . count($migrations) . " migration(s)\n");

        $rolledBack = 0;

        foreach ($migrations as $migration) {
            try {
                $this->info("Rolling back: {$migration['migration']}");

                // Find migration file
                $migrationFile = $this->findMigrationFile($migration['migration']);

                if (!$migrationFile) {
                    $this->warning("Migration file not found: {$migration['migration']}");
                    continue;
                }

                // Run rollback
                $this->executeMigration($migrationFile, 'down');

                // Remove from migrations table
                $this->removeMigrationRecord($migration['id']);

                $this->success("✓ Rolled back: {$migration['migration']}");
                $rolledBack++;
            } catch (\Throwable $e) {
                $this->error("✗ Failed to rollback: {$migration['migration']}");
                $this->error("Error: " . $e->getMessage());
                break;
            }
        }

        echo str_repeat('─', 60) . "\n";
        if ($this->failed) {
            $this->error("Rollback finished with errors ({$rolledBack} rolled back).");
        } else {
            $this->success("✅ Rollback completed! ({$rolledBack} migrations)");
        }
    }

    protected function runSeeders($argv)
    {
        // Check for --list option
        if ($this->hasOption($argv, 'list')) {
            $this->listSeeders();
            return;
        }

        // Check for --rm option
        if ($this->hasOption($argv, 'rm')) {
            // Get the seeder name from either --rm=value or next argument
            $parser = new \Core\Console\Support\ArgumentParser($argv);
            $seederName = $parser->getOption('rm');

            // If no value from option, try to get from remaining arguments
            if (!$seederName) {
                $args = $parser->getArguments();
                $seederName = !empty($args) ? $args[0] : null;
            }

            if ($seederName) {
                $this->removeSeeder($seederName);
            } else {
                $this->error("Please specify seeder name: php fany db:seed --rm=SeederName");
            }
            return;
        }

        $class = $this->getOptionValue($argv, 'class');

        $this->info("🌱 Running database seeders...");
        echo str_repeat('─', 60) . "\n";

        if (!is_dir($this->seedersPath)) {
            $this->error("Seeders directory not found: {$this->seedersPath}");
            return;
        }

        $ok = $class ? $this->runSpecificSeeder($class) : $this->runDatabaseSeeder();

        echo str_repeat('─', 60) . "\n";
        if ($ok) {
            $this->success("✅ Database seeding completed!");
        } else {
            $this->error("Database seeding failed.");
        }
    }

    /**
     * Resolve a seeder class name: App\Database\Seeders\{Name} first, global class as fallback.
     */
    protected function resolveSeederClass(string $class, string $file): ?string
    {
        $namespaced = 'App\\Database\\Seeders\\' . $class;

        if (!class_exists($namespaced) && file_exists($file)) {
            require_once $file;
        }

        if (class_exists($namespaced)) {
            return $namespaced;
        }

        return class_exists($class, false) ? $class : null;
    }

    protected function runDatabaseSeeder(): bool
    {
        $databaseSeeder = "{$this->seedersPath}/DatabaseSeeder.php";

        if (!file_exists($databaseSeeder)) {
            $this->warning("DatabaseSeeder not found. Running all seeders...");
            return $this->runAllSeeders();
        }

        $class = $this->resolveSeederClass('DatabaseSeeder', $databaseSeeder);

        if ($class === null) {
            $this->error("DatabaseSeeder class not found.");
            return false;
        }

        try {
            $seeder = new $class();

            if (!method_exists($seeder, 'run')) {
                $this->error("DatabaseSeeder must have a run() method.");
                return false;
            }

            $this->info("Running DatabaseSeeder...\n");
            $seeder->run();
            return true;
        } catch (\Throwable $e) {
            $this->error("Failed to run DatabaseSeeder: " . $e->getMessage());
            return false;
        }
    }

    protected function runSpecificSeeder($class): bool
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', (string) $class)) {
            $this->error("Invalid seeder name: {$class}");
            return false;
        }

        if (!str_ends_with($class, 'Seeder')) {
            $class .= 'Seeder';
        }

        $seederFile = "{$this->seedersPath}/{$class}.php";

        if (!file_exists($seederFile)) {
            $this->error("Seeder not found: {$class}");
            return false;
        }

        $fqcn = $this->resolveSeederClass($class, $seederFile);

        if ($fqcn === null) {
            $this->error("Seeder class not found in {$seederFile}: {$class}");
            return false;
        }

        try {
            $this->info("Running {$class}...\n");

            $seeder = new $fqcn();

            if (!method_exists($seeder, 'run')) {
                $this->error("{$class} must have a run() method.");
                return false;
            }

            $seeder->run();
            $this->success("✓ {$class} completed");
            return true;
        } catch (\Throwable $e) {
            $this->error("Failed to run {$class}: " . $e->getMessage());
            return false;
        }
    }

    protected function runAllSeeders(): bool
    {
        $seeders = glob("{$this->seedersPath}/*Seeder.php");

        if (empty($seeders)) {
            $this->warning("No seeder files found.");
            return true;
        }

        $ok = true;

        foreach ($seeders as $seederFile) {
            $class = basename($seederFile, '.php');

            if ($class === 'DatabaseSeeder') {
                continue;
            }

            $ok = $this->runSpecificSeeder($class) && $ok;
        }

        return $ok;
    }

    /**
     * Ask before destructive commands. --force skips the prompt; in
     * production the command refuses to run without --force.
     */
    protected function confirmDestructive($argv, string $what): bool
    {
        if ($this->hasOption($argv, 'force')) {
            return true;
        }

        if (\Core\Support\Env::raw('APP_ENV', 'production') === 'production') {
            $this->error("Refusing to {$what} in production without --force.");
            return false;
        }

        $this->warning("⚠️  This will DROP ALL TABLES in your database!");
        echo "Database: " . $this->getDatabaseName() . "\n";

        if (!$this->confirm("Are you sure you want to continue?", false)) {
            $this->info("Operation cancelled.");
            return false;
        }

        return true;
    }

    protected function resetDatabase($argv)
    {
        $this->rebuildDatabase($argv, 'reset');
    }

    protected function freshDatabase($argv)
    {
        $this->rebuildDatabase($argv, 'fresh');
    }

    /**
     * Shared implementation of db:reset and db:fresh: drop all tables, migrate, optionally seed.
     */
    protected function rebuildDatabase($argv, string $mode): void
    {
        if (!$this->confirmDestructive($argv, "run db:{$mode}")) {
            return;
        }

        $this->info("\n🔄 " . ($mode === 'reset' ? 'Resetting' : 'Rebuilding') . " database...");
        echo str_repeat('─', 60) . "\n";

        try {
            $this->info("Step 1: Dropping all tables...");
            $this->dropAllTables();
            $this->success("✓ All tables dropped");

            $this->info("\nStep 2: Running migrations...");
            $this->runMigrations($argv);

            if ($this->failed) {
                $this->error("Migrations failed; database left in a partial state.");
                return;
            }

            if ($this->hasOption($argv, 'seed')) {
                $this->info("\nStep 3: Running seeders...");
                $this->runSeeders($argv);
            }

            if ($this->failed) {
                return;
            }

            echo str_repeat('─', 60) . "\n";
            $this->success("✅ Database " . ($mode === 'reset' ? 'reset' : 'rebuilt') . " successfully!");
        } catch (\Throwable $e) {
            $this->error("Database {$mode} failed: " . $e->getMessage());
        }
    }

    protected function backupDatabase($argv)
    {
        $this->info("💾 Database Backup");
        echo str_repeat('═', 60) . "\n\n";

        try {
            $type = $this->getOptionValue($argv, 'type') ?? 'full';
            $path = $this->getOptionValue($argv, 'path') ?? 'database/backups';
            $compress = $this->hasOption($argv, 'compress');

            if (!in_array($type, ['full', 'structure', 'data'], true)) {
                $this->error("Invalid backup type. Use: full, structure, or data");
                return;
            }

            $pdo = $this->getConnection();
            $driver = $this->driverName($pdo);
            $database = (string) \Core\Support\Env::raw('DB_DATABASE', '');

            if ($driver === 'pgsql' && $type !== 'data') {
                $this->error("Structure backups are not supported for PostgreSQL (use pg_dump). Use --type=data.");
                return;
            }

            if (!is_dir($path) && !mkdir($path, 0755, true) && !is_dir($path)) {
                throw new \Exception("Cannot create backup directory: {$path}");
            }

            $label = $driver === 'sqlite' ? pathinfo($database ?: 'memory', PATHINFO_FILENAME) : $database;
            $label = preg_replace('/[^A-Za-z0-9_\-]/', '_', $label);
            $filepath = rtrim($path, '/') . "/{$label}_{$type}_" . date('Y-m-d_His') . '.sql';

            $this->info("Database: {$database} ({$driver})");
            $this->info("Backup Type: " . strtoupper($type));
            $this->info("Output File: {$filepath}\n");
            $this->info("⏳ Creating backup...");

            $handle = fopen($filepath, 'wb');
            if ($handle === false) {
                throw new \Exception("Cannot write backup file: {$filepath}");
            }

            try {
                $this->writeBackup($pdo, $driver, $database, $type, $handle);
            } finally {
                fclose($handle);
            }

            $this->success("✅ Backup created successfully!");
            $this->info("File: {$filepath}");
            $this->info("Size: " . $this->formatBytes((int) filesize($filepath)));

            if ($compress) {
                $this->info("\n⏳ Compressing backup...");
                $this->compressBackup($filepath);
            }

            echo str_repeat('═', 60) . "\n";
            $this->success("✅ Database backup completed!");
        } catch (\Throwable $e) {
            $this->error("Backup failed: " . $e->getMessage());
        }
    }

    protected function driverName(\PDO $pdo): string
    {
        return (string) $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
    }

    protected function quoteIdentifier(string $driver, string $name): string
    {
        return $driver === 'mysql'
            ? '`' . str_replace('`', '``', $name) . '`'
            : '"' . str_replace('"', '""', $name) . '"';
    }

    /**
     * Table names of the current database (MySQL, SQLite and PostgreSQL).
     */
    protected function listTables(\PDO $pdo): array
    {
        switch ($this->driverName($pdo)) {
            case 'sqlite':
                $sql = "SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'";
                break;
            case 'pgsql':
                $sql = "SELECT tablename FROM pg_tables WHERE schemaname = current_schema()";
                break;
            default:
                $sql = "SHOW TABLES";
        }

        return $pdo->query($sql)->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * Stream a SQL dump to $handle (rows are written in batches to keep memory flat).
     */
    protected function writeBackup(\PDO $pdo, string $driver, string $database, string $type, $handle): void
    {
        $w = static fn(string $text) => fwrite($handle, $text);

        $w("-- ============================================\n");
        $w("-- Database Backup\n-- Database: {$database} ({$driver})\n-- Type: " . strtoupper($type) . "\n");
        $w("-- Created: " . date('Y-m-d H:i:s') . "\n-- Generator: FANY CLI\n");
        $w("-- ============================================\n\n");

        if ($driver === 'mysql') {
            $w("SET FOREIGN_KEY_CHECKS=0;\nSET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\nSET time_zone = \"+00:00\";\n\n");
        } elseif ($driver === 'sqlite') {
            $w("PRAGMA foreign_keys=OFF;\nBEGIN TRANSACTION;\n\n");
        }

        foreach ($this->listTables($pdo) as $table) {
            $q = $this->quoteIdentifier($driver, $table);
            $w("-- Table: {$table}\n");

            if ($type === 'structure' || $type === 'full') {
                $w("DROP TABLE IF EXISTS {$q};\n");

                if ($driver === 'mysql') {
                    $row = $pdo->query("SHOW CREATE TABLE {$q}")->fetch(\PDO::FETCH_ASSOC);
                    $w($row['Create Table'] . ";\n\n");
                } else {
                    $stmt = $pdo->prepare("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = ?");
                    $stmt->execute([$table]);
                    $w($stmt->fetchColumn() . ";\n\n");
                }
            }

            if ($type === 'data' || $type === 'full') {
                $stmt = $pdo->query("SELECT * FROM {$q}");
                $batch = [];
                $columns = null;

                while (($row = $stmt->fetch(\PDO::FETCH_ASSOC)) !== false) {
                    $columns ??= implode(', ', array_map(fn($c) => $this->quoteIdentifier($driver, $c), array_keys($row)));
                    $batch[] = '(' . implode(', ', array_map(
                        fn($v) => $v === null ? 'NULL' : $pdo->quote((string) $v),
                        array_values($row)
                    )) . ')';

                    if (count($batch) >= 200) {
                        $w("INSERT INTO {$q} ({$columns}) VALUES\n" . implode(",\n", $batch) . ";\n");
                        $batch = [];
                    }
                }

                if ($batch) {
                    $w("INSERT INTO {$q} ({$columns}) VALUES\n" . implode(",\n", $batch) . ";\n");
                }
                $w("\n");
            }
        }

        if ($driver === 'mysql') {
            $w("SET FOREIGN_KEY_CHECKS=1;\n");
        } elseif ($driver === 'sqlite') {
            $w("COMMIT;\nPRAGMA foreign_keys=ON;\n");
        }
    }

    /**
     * Compress backup file
     */
    protected function compressBackup($filepath)
    {
        if (function_exists('gzencode')) {
            $content = file_get_contents($filepath);
            $compressed = gzencode($content, 9);
            $gzFilepath = $filepath . '.gz';

            file_put_contents($gzFilepath, $compressed);

            $originalSize = filesize($filepath);
            $compressedSize = filesize($gzFilepath);
            $ratio = round((1 - ($compressedSize / $originalSize)) * 100, 2);

            $this->success("✅ Compressed: {$gzFilepath}");
            $this->info("Original: " . $this->formatBytes($originalSize));
            $this->info("Compressed: " . $this->formatBytes($compressedSize));
            $this->info("Ratio: {$ratio}% reduction");

            // Optionally delete original
            if ($this->confirm("\nDelete original SQL file?", false)) {
                unlink($filepath);
                $this->info("Original file deleted");
            }
        } else {
            $this->warning("⚠️  Compression not available (gzencode not found)");
        }
    }

    /**
     * Format bytes to human readable
     */
    protected function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }

    protected function showMigrationStatus()
    {
        $this->info("📊 Migration Status");
        echo str_repeat('═', 60) . "\n\n";

        // Get all migrations
        $allMigrations = $this->getAllMigrations();
        $ranMigrations = $this->getRanMigrationsWithDetails();

        if (empty($allMigrations)) {
            $this->warning("No migration files found.");
            return;
        }

        $headers = ['Migration', 'Batch', 'Status'];
        $rows = [];

        foreach ($allMigrations as $migration) {
            $ran = null;
            foreach ($ranMigrations as $ranMigration) {
                if ($ranMigration['migration'] === $migration['name']) {
                    $ran = $ranMigration;
                    break;
                }
            }

            $rows[] = [
                $migration['name'],
                $ran ? $ran['batch'] : '-',
                $ran ? "\e[32mRan\e[0m" : "\e[33mPending\e[0m"
            ];
        }

        $this->displayTable($headers, $rows);

        // Summary
        $ranCount = count($ranMigrations);
        $pendingCount = count($allMigrations) - $ranCount;

        echo "\n\e[1;32mSummary:\e[0m\n";
        echo "  Total migrations: " . count($allMigrations) . "\n";
        echo "  Ran: \e[32m{$ranCount}\e[0m\n";
        echo "  Pending: \e[33m{$pendingCount}\e[0m\n";

        if ($pendingCount > 0) {
            echo "\n💡 Run \e[36mphp fany db:migrate\e[0m to execute pending migrations.\n";
        }

        echo "\n";
    }

    protected function executeMigration($path, $method)
    {
        require_once $path;

        // Get class name from file
        $content = file_get_contents($path);
        preg_match('/class\s+(\w+)/', $content, $matches);

        if (!isset($matches[1])) {
            throw new \Exception("Could not find class in migration file");
        }

        $className = $matches[1];

        if (!class_exists($className)) {
            throw new \Exception("Migration class {$className} not found");
        }

        $migration = new $className();

        if (!method_exists($migration, $method)) {
            throw new \Exception("Method {$method}() not found in migration");
        }

        $migration->$method();
    }

    protected function createMigrationsTable()
    {
        try {
            $pdo = $this->getConnection();
            $driver = $this->driverName($pdo);

            if ($driver === 'sqlite') {
                $sql = "CREATE TABLE IF NOT EXISTS {$this->migrationTable} (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    migration VARCHAR(255) NOT NULL,
                    batch INT NOT NULL,
                    migrated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )";
            } elseif ($driver === 'pgsql') {
                $sql = "CREATE TABLE IF NOT EXISTS {$this->migrationTable} (
                    id SERIAL PRIMARY KEY,
                    migration VARCHAR(255) NOT NULL,
                    batch INT NOT NULL,
                    migrated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )";
            } else {
                $sql = "CREATE TABLE IF NOT EXISTS {$this->migrationTable} (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    migration VARCHAR(255) NOT NULL,
                    batch INT NOT NULL,
                    migrated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )";
            }

            $pdo->exec($sql);
        } catch (\Throwable $e) {
            throw new \Exception("Failed to create migrations table: " . $e->getMessage(), 0, $e);
        }
    }

    protected function getAllMigrations()
    {
        $files = glob("{$this->migrationsPath}/*.php");
        $migrations = [];

        foreach ($files as $file) {
            $migrations[] = [
                'name' => basename($file, '.php'),
                'path' => $file
            ];
        }

        // Sort by filename (timestamp)
        usort($migrations, function ($a, $b) {
            return strcmp($a['name'], $b['name']);
        });

        return $migrations;
    }

    protected function getRanMigrations()
    {
        try {
            $pdo = $this->getConnection();
            $stmt = $pdo->query("SELECT migration FROM {$this->migrationTable} ORDER BY batch, id");
            return $stmt->fetchAll(\PDO::FETCH_COLUMN);
        } catch (\Throwable $e) {
            return [];
        }
    }

    protected function getRanMigrationsWithDetails()
    {
        try {
            $pdo = $this->getConnection();
            $stmt = $pdo->query("SELECT * FROM {$this->migrationTable} ORDER BY batch DESC, id DESC");
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return [];
        }
    }

    protected function getMigrationsToRollback($step)
    {
        try {
            $pdo = $this->getConnection();

            // Get distinct batches
            $stmt = $pdo->query("SELECT DISTINCT batch FROM {$this->migrationTable} ORDER BY batch DESC LIMIT {$step}");
            $batches = $stmt->fetchAll(\PDO::FETCH_COLUMN);

            if (empty($batches)) {
                return [];
            }

            $batchList = implode(',', $batches);
            $stmt = $pdo->query("SELECT * FROM {$this->migrationTable} WHERE batch IN ({$batchList}) ORDER BY id DESC");

            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return [];
        }
    }

    protected function findMigrationFile($name)
    {
        $path = "{$this->migrationsPath}/{$name}.php";
        return file_exists($path) ? $path : null;
    }

    protected function recordMigration($name, $batch)
    {
        try {
            $pdo = $this->getConnection();
            $stmt = $pdo->prepare("INSERT INTO {$this->migrationTable} (migration, batch) VALUES (?, ?)");
            $stmt->execute([$name, $batch]);
        } catch (\Throwable $e) {
            throw new \Exception("Failed to record migration: " . $e->getMessage());
        }
    }

    protected function removeMigrationRecord($id)
    {
        try {
            $pdo = $this->getConnection();
            $stmt = $pdo->prepare("DELETE FROM {$this->migrationTable} WHERE id = ?");
            $stmt->execute([$id]);
        } catch (\Throwable $e) {
            throw new \Exception("Failed to remove migration record: " . $e->getMessage());
        }
    }

    protected function getNextBatchNumber()
    {
        try {
            $pdo = $this->getConnection();
            $stmt = $pdo->query("SELECT MAX(batch) as max_batch FROM {$this->migrationTable}");
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            return ($result['max_batch'] ?? 0) + 1;
        } catch (\Throwable $e) {
            return 1;
        }
    }

    protected function dropAllTables()
    {
        try {
            $pdo = $this->getConnection();
            $driver = $this->driverName($pdo);

            if ($driver === 'mysql') {
                $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
            } elseif ($driver === 'sqlite') {
                $pdo->exec("PRAGMA foreign_keys = OFF");
            }

            try {
                foreach ($this->listTables($pdo) as $table) {
                    $this->info("Dropping table: {$table}");
                    $pdo->exec("DROP TABLE IF EXISTS " . $this->quoteIdentifier($driver, $table) . ($driver === 'pgsql' ? ' CASCADE' : ''));
                }
            } finally {
                if ($driver === 'mysql') {
                    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
                } elseif ($driver === 'sqlite') {
                    $pdo->exec("PRAGMA foreign_keys = ON");
                }
            }
        } catch (\Throwable $e) {
            throw new \Exception("Failed to drop tables: " . $e->getMessage(), 0, $e);
        }
    }

    protected function getConnection()
    {
        // Same connection the application (DB / Schema / Model) uses.
        return \Core\Foundation\Database\Database::getInstance()->getConnection();
    }

    protected function getDatabaseName()
    {
        return \Core\Support\Env::raw('DB_DATABASE', 'unknown');
    }

    protected function displayTable($headers, $rows)
    {
        // Calculate column widths
        $widths = [];
        foreach ($headers as $i => $header) {
            $widths[$i] = strlen($header);
        }

        foreach ($rows as $row) {
            foreach ($row as $i => $cell) {
                // Strip ANSI codes for width calculation
                $cleanCell = preg_replace('/\e\[[0-9;]*m/', '', $cell);
                $widths[$i] = max($widths[$i], strlen($cleanCell));
            }
        }

        // Display header
        foreach ($headers as $i => $header) {
            echo str_pad($header, $widths[$i] + 3);
        }
        echo "\n";

        // Display separator
        foreach ($widths as $width) {
            echo str_repeat('─', $width + 3);
        }
        echo "\n";

        // Display rows
        foreach ($rows as $row) {
            foreach ($row as $i => $cell) {
                $cleanCell = preg_replace('/\e\[[0-9;]*m/', '', $cell);
                $padding = $widths[$i] - strlen($cleanCell);
                echo $cell . str_repeat(' ', $padding + 3);
            }
            echo "\n";
        }
    }

    protected function showDatabaseHelp($command)
    {
        echo "\n\e[1;33m🗄️  Database Commands\e[0m\n";
        echo str_repeat('═', 70) . "\n\n";

        echo "\e[1;32mAVAILABLE COMMANDS:\e[0m\n\n";

        echo "  \e[36mdb:migrate [--force]\e[0m\n";
        echo "    Run pending database migrations\n";
        echo "    --force    Continue on errors\n\n";

        echo "  \e[36mdb:rollback [--step=N]\e[0m\n";
        echo "    Rollback the last database migration\n";
        echo "    --step=N   Number of batches to rollback (default: 1)\n\n";

        echo "  \e[36mdb:seed [--class=SeederClass]\e[0m\n";
        echo "    Seed the database with records\n";
        echo "    --class    Specific seeder class to run\n";
        echo "    --list     Show all available seeders\n";
        echo "    --rm       Remove a seeder (example: --rm UserSeeder)\n\n";

        echo "  \e[36mdb:reset [--seed] [--force]\e[0m\n";
        echo "    Drop all tables and re-run migrations\n";
        echo "    --seed     Also run seeders after migration\n";
        echo "    --force    Skip the confirmation (required in production)\n\n";

        echo "  \e[36mdb:fresh [--seed] [--force]\e[0m\n";
        echo "    Drop all tables and re-run migrations (alias for reset)\n\n";

        echo "  \e[36mdb:status\e[0m\n";
        echo "    Show the status of each migration\n\n";

        echo "  \e[36mdb:backup [--type=TYPE] [--path=PATH] [--compress]\e[0m\n";
        echo "    Backup database to SQL file\n";
        echo "    --type=TYPE    Backup type: full, structure, or data (default: full)\n";
        echo "    --path=PATH    Output directory (default: database/backups)\n";
        echo "    --compress     Compress backup with gzip\n\n";

        echo "\e[1;32mEXAMPLES:\e[0m\n";
        echo "  \e[2mphp fany db:migrate\e[0m\n";
        echo "  \e[2mphp fany db:rollback --step=2\e[0m\n";
        echo "  \e[2mphp fany db:seed --class=UserSeeder\e[0m\n";
        echo "  \e[2mphp fany db:reset --seed\e[0m\n";
        echo "  \e[2mphp fany db:status\e[0m\n\n";
    }

    /**
     * List all available seeders
     */
    protected function listSeeders()
    {
        $this->info("📋 Available Seeders:");
        echo str_repeat('─', 60) . "\n";

        if (!is_dir($this->seedersPath)) {
            $this->error("Seeders directory not found: {$this->seedersPath}");
            return;
        }

        $files = scandir($this->seedersPath);
        $seeders = [];

        foreach ($files as $file) {
            if (str_ends_with($file, '.php') && $file !== 'DatabaseSeeder.php') {
                $seeders[] = str_replace('.php', '', $file);
            }
        }

        if (empty($seeders)) {
            $this->warning("No seeders found.");
            echo str_repeat('─', 60) . "\n";
            return;
        }

        // Load DatabaseSeeder to see which ones are registered
        $registeredSeeders = $this->getRegisteredSeeders();

        foreach ($seeders as $seeder) {
            $status = in_array($seeder, $registeredSeeders) ? '✅' : '⚠️ ';
            echo "  {$status} {$seeder}\n";
        }

        echo str_repeat('─', 60) . "\n";
        echo "  ✅ = Registered in DatabaseSeeder\n";
        echo "  ⚠️  = Not registered (won't run with 'php fany db:seed')\n\n";
    }

    /**
     * Get list of registered seeders from DatabaseSeeder
     */
    protected function getRegisteredSeeders(): array
    {
        $databaseSeeder = "{$this->seedersPath}/DatabaseSeeder.php";

        if (!file_exists($databaseSeeder)) {
            return [];
        }

        $content = file_get_contents($databaseSeeder);
        $registered = [];

        // Find all seeder class names in the call array
        if (preg_match_all('/([A-Za-z0-9_]+Seeder)::class/', $content, $matches)) {
            $registered = $matches[1];
        }

        return $registered;
    }

    /**
     * Remove a seeder and unregister from DatabaseSeeder
     */
    protected function removeSeeder($seederName)
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', (string) $seederName)) {
            $this->error("Invalid seeder name: {$seederName}");
            return;
        }

        // Ensure seeder name ends with Seeder
        if (!str_ends_with($seederName, 'Seeder')) {
            $seederName .= 'Seeder';
        }

        $seederFile = "{$this->seedersPath}/{$seederName}.php";

        // Check if file exists
        if (!file_exists($seederFile)) {
            $this->error("Seeder not found: {$seederName}");
            return;
        }

        $this->info("🗑️  Removing seeder: {$seederName}");

        // Remove from DatabaseSeeder.php
        $databaseSeeder = "{$this->seedersPath}/DatabaseSeeder.php";
        if (file_exists($databaseSeeder)) {
            $content = file_get_contents($databaseSeeder);

            // Remove the line that lists this seeder ("    NameSeeder::class,")
            $newContent = preg_replace('/^[ \t]*' . preg_quote($seederName, '/') . '::class,?[ \t]*\r?\n/m', '', $content);

            file_put_contents($databaseSeeder, $newContent);
            $this->success("✅ Removed from DatabaseSeeder.php");
        }

        // Delete the seeder file
        if (unlink($seederFile)) {
            $this->success("✅ Deleted file: {$seederFile}");
        } else {
            $this->error("Failed to delete file: {$seederFile}");
            return;
        }

        echo "\n";
        $this->success("✅ Seeder '{$seederName}' has been removed successfully!");
    }
}
