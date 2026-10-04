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

        foreach ($pendingMigrations as $migration) {
            try {
                $this->info("Migrating: {$migration['name']}");

                // Run migration
                $this->executeMigration($migration['path'], 'up');

                // Record in migrations table
                $this->recordMigration($migration['name'], $batch);

                $this->success("✓ Migrated: {$migration['name']}");
                $migrated++;
            } catch (\Exception $e) {
                $this->error("✗ Failed: {$migration['name']}");
                $this->error("Error: " . $e->getMessage());

                if ($this->hasOption($argv, 'force')) {
                    $this->warning("Continuing due to --force flag...");
                    continue;
                } else {
                    $this->warning("Migration stopped. Use --force to continue on errors.");
                    break;
                }
            }
        }

        echo str_repeat('─', 60) . "\n";
        $this->success("✅ Migration completed! ({$migrated} migrations)");
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
            } catch (\Exception $e) {
                $this->error("✗ Failed to rollback: {$migration['migration']}");
                $this->error("Error: " . $e->getMessage());
                break;
            }
        }

        echo str_repeat('─', 60) . "\n";
        $this->success("✅ Rollback completed! ({$rolledBack} migrations)");
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

        if ($class) {
            // Run specific seeder
            $this->runSpecificSeeder($class);
        } else {
            // Run DatabaseSeeder
            $this->runDatabaseSeeder();
        }

        echo str_repeat('─', 60) . "\n";
        $this->success("✅ Database seeding completed!");
    }

    protected function runDatabaseSeeder()
    {
        $databaseSeeder = "{$this->seedersPath}/DatabaseSeeder.php";

        if (!file_exists($databaseSeeder)) {
            $this->warning("DatabaseSeeder not found. Running all seeders...");
            $this->runAllSeeders();
            return;
        }

        try {
            require_once $databaseSeeder;

            $class = 'App\Database\Seeders\DatabaseSeeder';

            if (!class_exists($class)) {
                $this->error("DatabaseSeeder class not found.");
                return;
            }

            $seeder = new $class();

            if (method_exists($seeder, 'run')) {
                $this->info("Running DatabaseSeeder...\n");
                $seeder->run();
            } else {
                $this->error("DatabaseSeeder must have a run() method.");
            }
        } catch (\Exception $e) {
            $this->error("Failed to run DatabaseSeeder: " . $e->getMessage());
        }
    }

    protected function runSpecificSeeder($class)
    {
        // Add Seeder suffix if not present
        if (!str_ends_with($class, 'Seeder')) {
            $class .= 'Seeder';
        }

        $seederFile = "{$this->seedersPath}/{$class}.php";

        if (!file_exists($seederFile)) {
            $this->error("Seeder not found: {$class}");
            return;
        }

        try {
            require_once $seederFile;

            if (!class_exists($class)) {
                $this->error("Seeder class not found: {$class}");
                return;
            }

            $this->info("Running {$class}...\n");

            $seeder = new $class();

            if (method_exists($seeder, 'run')) {
                $seeder->run();
                $this->success("✓ {$class} completed");
            } else {
                $this->error("{$class} must have a run() method.");
            }
        } catch (\Exception $e) {
            $this->error("Failed to run {$class}: " . $e->getMessage());
        }
    }

    protected function runAllSeeders()
    {
        $seeders = glob("{$this->seedersPath}/*Seeder.php");

        if (empty($seeders)) {
            $this->warning("No seeder files found.");
            return;
        }

        foreach ($seeders as $seederFile) {
            $class = basename($seederFile, '.php');

            // Skip DatabaseSeeder
            if ($class === 'DatabaseSeeder') {
                continue;
            }

            try {
                require_once $seederFile;

                if (class_exists($class)) {
                    $this->info("Running {$class}...");
                    $seeder = new $class();

                    if (method_exists($seeder, 'run')) {
                        $seeder->run();
                        $this->success("✓ {$class} completed");
                    }
                }
            } catch (\Exception $e) {
                $this->error("Failed to run {$class}: " . $e->getMessage());
            }
        }
    }

    protected function resetDatabase($argv)
    {
        $this->warning("⚠️  This will DROP ALL TABLES in your database!");
        echo "Database: " . $this->getDatabaseName() . "\n";

        if (!$this->confirm("Are you sure you want to continue?", false)) {
            $this->info("Operation cancelled.");
            return;
        }

        $this->info("\n🔄 Resetting database...");
        echo str_repeat('─', 60) . "\n";

        try {
            // Step 1: Drop all tables
            $this->info("Step 1: Dropping all tables...");
            $this->dropAllTables();
            $this->success("✓ All tables dropped");

            // Step 2: Run migrations
            $this->info("\nStep 2: Running migrations...");
            $this->runMigrations($argv);

            // Step 3: Run seeders if --seed flag present
            if ($this->hasOption($argv, 'seed')) {
                $this->info("\nStep 3: Running seeders...");
                $this->runSeeders($argv);
            }

            echo str_repeat('─', 60) . "\n";
            $this->success("✅ Database reset completed successfully!");
        } catch (\Exception $e) {
            $this->error("Database reset failed: " . $e->getMessage());
        }
    }

    protected function freshDatabase($argv)
    {
        $this->info("🔄 Creating fresh database...");

        try {
            // Drop all tables
            $this->dropAllTables();

            // Run migrations
            $this->runMigrations($argv);

            // Run seeders if --seed flag present
            if ($this->hasOption($argv, 'seed')) {
                $this->runSeeders($argv);
            }

            $this->success("✅ Fresh database created!");
        } catch (\Exception $e) {
            $this->error("Failed to create fresh database: " . $e->getMessage());
        }
    }

    protected function backupDatabase($argv)
    {
        $this->info("💾 Database Backup");
        echo str_repeat('═', 60) . "\n\n";

        try {
            // Get backup options
            $type = $this->getOptionValue($argv, 'type') ?? 'full';
            $path = $this->getOptionValue($argv, 'path') ?? 'database/backups';
            $compress = $this->hasOption($argv, 'compress');

            // Validate type
            if (!in_array($type, ['full', 'structure', 'data'])) {
                $this->error("Invalid backup type. Use: full, structure, or data");
                return;
            }

            // Load environment
            $this->loadEnv();

            $host = $_ENV['DB_HOST'] ?? '127.0.0.1';
            $port = $_ENV['DB_PORT'] ?? '3306';
            $database = $_ENV['DB_DATABASE'] ?? '';
            $username = $_ENV['DB_USERNAME'] ?? 'root';
            $password = $_ENV['DB_PASSWORD'] ?? '';

            if (empty($database)) {
                $this->error("Database name not configured in .env file");
                return;
            }

            // Create backup directory if not exists
            if (!is_dir($path)) {
                mkdir($path, 0755, true);
                $this->info("✓ Created backup directory: {$path}");
            }

            // Generate filename
            $timestamp = date('Y-m-d_His');
            $filename = "{$database}_{$type}_{$timestamp}.sql";
            $filepath = $path . '/' . $filename;

            $this->info("Database: {$database}");
            $this->info("Backup Type: " . strtoupper($type));
            $this->info("Output File: {$filepath}");
            echo "\n";

            // Perform backup
            $this->info("⏳ Creating backup...");

            $pdo = $this->getConnection();

            // Start building SQL
            $sql = $this->generateBackupSQL($pdo, $database, $type);

            // Write to file
            file_put_contents($filepath, $sql);

            $filesize = filesize($filepath);
            $filesizeFormatted = $this->formatBytes($filesize);

            $this->success("✅ Backup created successfully!");
            $this->info("File: {$filepath}");
            $this->info("Size: {$filesizeFormatted}");

            // Compress if requested
            if ($compress) {
                $this->info("\n⏳ Compressing backup...");
                $this->compressBackup($filepath);
            }

            echo str_repeat('═', 60) . "\n";
            $this->success("✅ Database backup completed!");
        } catch (\Exception $e) {
            $this->error("Backup failed: " . $e->getMessage());
        }
    }

    /**
     * Generate SQL backup based on type
     */
    protected function generateBackupSQL($pdo, $database, $type)
    {
        $sql = "";

        // Header
        $sql .= "-- ============================================\n";
        $sql .= "-- Database Backup\n";
        $sql .= "-- Database: {$database}\n";
        $sql .= "-- Type: " . strtoupper($type) . "\n";
        $sql .= "-- Created: " . date('Y-m-d H:i:s') . "\n";
        $sql .= "-- Generator: FANY CLI v2.0\n";
        $sql .= "-- ============================================\n\n";

        $sql .= "SET FOREIGN_KEY_CHECKS=0;\n";
        $sql .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
        $sql .= "SET time_zone = \"+00:00\";\n\n";

        // Get all tables
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        foreach ($tables as $table) {
            $sql .= "\n-- ============================================\n";
            $sql .= "-- Table: {$table}\n";
            $sql .= "-- ============================================\n\n";

            // Structure
            if ($type === 'structure' || $type === 'full') {
                $sql .= "DROP TABLE IF EXISTS `{$table}`;\n";

                $row = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(\PDO::FETCH_ASSOC);
                $sql .= $row['Create Table'] . ";\n\n";
            }

            // Data
            if ($type === 'data' || $type === 'full') {
                $stmt = $pdo->query("SELECT * FROM `{$table}`");
                $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

                if (!empty($rows)) {
                    $sql .= "-- Data for table `{$table}`\n";
                    $sql .= "INSERT INTO `{$table}` VALUES\n";

                    $insertValues = [];
                    foreach ($rows as $row) {
                        $values = array_map(function ($value) use ($pdo) {
                            if ($value === null) {
                                return 'NULL';
                            }
                            return $pdo->quote($value);
                        }, array_values($row));

                        $insertValues[] = '(' . implode(', ', $values) . ')';
                    }

                    $sql .= implode(",\n", $insertValues) . ";\n\n";
                } else {
                    $sql .= "-- No data for table `{$table}`\n\n";
                }
            }
        }

        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

        return $sql;
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
            $driver = $_ENV['DB_CONNECTION'] ?? 'mysql';

            if ($driver === 'sqlite') {
                $sql = "CREATE TABLE IF NOT EXISTS {$this->migrationTable} (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
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
        } catch (\Exception $e) {
            throw new \Exception("Failed to create migrations table: " . $e->getMessage());
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
        } catch (\Exception $e) {
            return [];
        }
    }

    protected function getRanMigrationsWithDetails()
    {
        try {
            $pdo = $this->getConnection();
            $stmt = $pdo->query("SELECT * FROM {$this->migrationTable} ORDER BY batch DESC, id DESC");
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
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
        } catch (\Exception $e) {
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
        } catch (\Exception $e) {
            throw new \Exception("Failed to record migration: " . $e->getMessage());
        }
    }

    protected function removeMigrationRecord($id)
    {
        try {
            $pdo = $this->getConnection();
            $stmt = $pdo->prepare("DELETE FROM {$this->migrationTable} WHERE id = ?");
            $stmt->execute([$id]);
        } catch (\Exception $e) {
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
        } catch (\Exception $e) {
            return 1;
        }
    }

    protected function dropAllTables()
    {
        try {
            $pdo = $this->getConnection();

            // Disable foreign key checks
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

            // Get all tables
            $stmt = $pdo->query("SHOW TABLES");
            $tables = $stmt->fetchAll(\PDO::FETCH_COLUMN);

            foreach ($tables as $table) {
                $this->info("Dropping table: {$table}");
                $pdo->exec("DROP TABLE IF EXISTS `{$table}`");
            }

            // Re-enable foreign key checks
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
        } catch (\Exception $e) {
            throw new \Exception("Failed to drop tables: " . $e->getMessage());
        }
    }

    protected function getConnection()
    {
        // Load .env file
        $this->loadEnv();

        $host = $_ENV['DB_HOST'] ?? '127.0.0.1';
        $port = $_ENV['DB_PORT'] ?? '3306';
        $database = $_ENV['DB_DATABASE'] ?? '';
        $username = $_ENV['DB_USERNAME'] ?? 'root';
        $password = $_ENV['DB_PASSWORD'] ?? '';
        $driver = $_ENV['DB_CONNECTION'] ?? 'mysql';

        if (empty($database)) {
            throw new \Exception("Database name not configured in .env file");
        }

        try {
            if ($driver === 'sqlite') {
                // For sqlite, convert relative paths to absolute paths
                if (!str_starts_with($database, '/') && !str_starts_with($database, '~')) {
                    $database = getcwd() . '/' . $database;
                }
                $dsn = "sqlite:{$database}";
                $pdo = new \PDO($dsn);
            } else {
                $dsn = "{$driver}:host={$host};port={$port};dbname={$database};charset=utf8mb4";
                $pdo = new \PDO($dsn, $username, $password);
            }
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            return $pdo;
        } catch (\PDOException $e) {
            throw new \Exception("Database connection failed: " . $e->getMessage());
        }
    }

    protected function getDatabaseName()
    {
        $this->loadEnv();
        return $_ENV['DB_DATABASE'] ?? 'unknown';
    }

    protected function loadEnv()
    {
        if (!file_exists('.env')) {
            return;
        }

        $lines = file('.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);

                // Remove quotes
                $value = trim($value, '"\'');

                if (!array_key_exists($key, $_ENV)) {
                    $_ENV[$key] = $value;
                }
            }
        }
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

        echo "  \e[36mdb:reset [--seed]\e[0m\n";
        echo "    Drop all tables and re-run migrations\n";
        echo "    --seed     Also run seeders after migration\n\n";

        echo "  \e[36mdb:fresh [--seed]\e[0m\n";
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

            // Remove the seeder from the call array - handle leading/trailing commas
            // Match patterns like: PostSeeder::class, or , PostSeeder::class
            $pattern = "/,?\s*{$seederName}::class\s*,?/";
            $newContent = preg_replace($pattern, ',', $content);

            // Clean up multiple commas
            $newContent = preg_replace('/,\s*,/', ',', $newContent);
            // Clean up opening bracket with comma
            $newContent = preg_replace('/\[\s*,/', '[', $newContent);
            // Clean up closing bracket with comma
            $newContent = preg_replace('/,\s*\]/', ']', $newContent);

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
