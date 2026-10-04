<?php

namespace Core\Foundation\Database;

class Database
{
    private static $instance = null;
    private $connection = null;

    private function __construct()
    {
        $this->connect();
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function connect()
    {
        $this->connection = self::createConnection();
    }

    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * Build a PDO connection from the .env settings.
     * Single place for connection logic (web, QueryBuilder and the fany CLI).
     */
    public static function createConnection(): \PDO
    {
        $driver = (string) \Core\Support\Env::raw('DB_CONNECTION', 'mysql');
        $host = (string) \Core\Support\Env::raw('DB_HOST', '127.0.0.1');
        $port = (string) \Core\Support\Env::raw('DB_PORT', $driver === 'pgsql' ? '5432' : '3306');
        $database = (string) \Core\Support\Env::raw('DB_DATABASE', '');
        $username = (string) \Core\Support\Env::raw('DB_USERNAME', 'root');
        $password = (string) \Core\Support\Env::raw('DB_PASSWORD', '');
        $charset = (string) \Core\Support\Env::raw('DB_CHARSET', 'utf8mb4');

        $options = [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            if ($driver === 'sqlite') {
                $database = self::resolveSqlitePath($database);

                $pdo = new \PDO('sqlite:' . $database, null, null, $options);
                $pdo->exec('PRAGMA foreign_keys = ON');
                return $pdo;
            }

            if ($database === '') {
                throw new \Exception('Database name not configured (DB_DATABASE in .env)');
            }

            if ($driver === 'pgsql') {
                $dsn = "pgsql:host={$host};port={$port};dbname={$database}";
            } elseif ($driver === 'mysql') {
                $dsn = "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";
            } else {
                throw new \Exception("Unsupported DB_CONNECTION '{$driver}' (use mysql, pgsql or sqlite)");
            }

            return new \PDO($dsn, $username, $password, $options);
        } catch (\PDOException $e) {
            throw new \Exception('Database connection failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Resolve a SQLite path against the project root and create the file when missing.
     */
    private static function resolveSqlitePath(string $database): string
    {
        if ($database === '' || $database === ':memory:') {
            return ':memory:';
        }

        if (str_starts_with($database, '~')) {
            $database = (string) ($_SERVER['HOME'] ?? getenv('HOME') ?: '') . substr($database, 1);
        } elseif (!str_starts_with($database, '/') && !preg_match('/^[A-Za-z]:[\\\\\/]/', $database)) {
            $database = dirname(__DIR__, 3) . '/' . ltrim($database, '/');
        }

        $dir = dirname($database);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \Exception("Cannot create SQLite directory: {$dir}");
        }
        if (!file_exists($database) && @touch($database) === false) {
            throw new \Exception("Cannot create SQLite database file: {$database}");
        }

        return $database;
    }

    /**
     * Execute raw SQL query and return result
     */
    public function execute($sql, $bindings = [])
    {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($bindings);
            return true;
        } catch (\PDOException $e) {
            throw new \Exception("Execution failed: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Execute raw SQL and get results
     */
    public function select($sql, $bindings = [])
    {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($bindings);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            throw new \Exception("Query failed: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Get last inserted ID
     */
    public function lastInsertId()
    {
        return $this->connection->lastInsertId();
    }

    /**
     * Begin transaction
     */
    public function beginTransaction()
    {
        return $this->connection->beginTransaction();
    }

    /**
     * Commit transaction
     */
    public function commit()
    {
        return $this->connection->commit();
    }

    /**
     * Rollback transaction
     */
    public function rollback()
    {
        return $this->connection->rollBack();
    }

    // Prevent cloning
    private function __clone() {}

    // Prevent unserialization
    public function __wakeup()
    {
        throw new \Exception("Cannot unserialize singleton");
    }
}
