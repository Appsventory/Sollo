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
        $this->loadEnv();

        $host = $_ENV['DB_HOST'] ?? '127.0.0.1';
        $port = $_ENV['DB_PORT'] ?? '3306';
        $database = $_ENV['DB_DATABASE'] ?? '';
        $username = $_ENV['DB_USERNAME'] ?? 'root';
        $password = $_ENV['DB_PASSWORD'] ?? '';
        $driver = $_ENV['DB_CONNECTION'] ?? 'mysql';
        $charset = $_ENV['DB_CHARSET'] ?? 'utf8mb4';

        try {
            if ($driver === 'sqlite') {
                // For sqlite, make sure to use absolute path if relative path is given
                if (!str_starts_with($database, '/') && !str_starts_with($database, '~')) {
                    // Get project root by going up from current directory
                    $projectRoot = dirname(dirname(dirname(__DIR__)));
                    $database = $projectRoot . '/' . $database;
                }
                $dsn = "sqlite:{$database}";
                $this->connection = new \PDO($dsn, null, null, [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                    \PDO::ATTR_EMULATE_PREPARES => false,
                ]);
            } else {
                $dsn = "{$driver}:host={$host};port={$port};dbname={$database};charset={$charset}";

                $this->connection = new \PDO($dsn, $username, $password, [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                    \PDO::ATTR_EMULATE_PREPARES => false,
                ]);
            }
        } catch (\PDOException $e) {
            throw new \Exception("Database connection failed: " . $e->getMessage());
        }
    }

    public function getConnection()
    {
        return $this->connection;
    }

    private function loadEnv()
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
                    putenv("{$key}={$value}");
                }
            }
        }
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
            throw new \Exception("Execution failed: " . $e->getMessage());
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
            throw new \Exception("Query failed: " . $e->getMessage());
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
