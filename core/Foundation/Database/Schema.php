<?php

namespace Core\Foundation\Database;

class Schema
{
    /**
     * Create a new table
     */
    public static function create($tableName, callable $callback)
    {
        $table = new SchemaTable($tableName);
        $callback($table);

        $sql = $table->toSql();
        self::execute($sql);
    }

    /**
     * Modify an existing table
     */
    public static function table($tableName, callable $callback)
    {
        $table = new SchemaTable($tableName, true);
        $callback($table);

        $sql = $table->toAlterSql();
        self::execute($sql);
    }

    public static function dropIfExists($tableName)
    {
        self::execute('DROP TABLE IF EXISTS ' . self::quote($tableName));
    }

    public static function drop($tableName)
    {
        self::execute('DROP TABLE ' . self::quote($tableName));
    }

    public static function hasTable($tableName)
    {
        $pdo = Database::getInstance()->getConnection();

        switch (self::driver()) {
            case 'sqlite':
                $stmt = $pdo->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = ?");
                break;
            case 'pgsql':
                $stmt = $pdo->prepare("SELECT tablename FROM pg_tables WHERE schemaname = current_schema() AND tablename = ?");
                break;
            default:
                $stmt = $pdo->prepare("SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
        }

        $stmt->execute([$tableName]);
        return $stmt->fetchColumn() !== false;
    }

    public static function hasColumn($tableName, $columnName)
    {
        $pdo = Database::getInstance()->getConnection();

        switch (self::driver()) {
            case 'sqlite':
                foreach ($pdo->query('PRAGMA table_info(' . self::quote($tableName) . ')')->fetchAll(\PDO::FETCH_ASSOC) as $column) {
                    if (strcasecmp($column['name'], $columnName) === 0) {
                        return true;
                    }
                }
                return false;
            case 'pgsql':
                $stmt = $pdo->prepare("SELECT column_name FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = ? AND column_name = ?");
                break;
            default:
                $stmt = $pdo->prepare("SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
        }

        $stmt->execute([$tableName, $columnName]);
        return $stmt->fetchColumn() !== false;
    }

    public static function rename($from, $to)
    {
        self::execute('ALTER TABLE ' . self::quote($from) . ' RENAME TO ' . self::quote($to));
    }

    protected static function execute($sql)
    {
        try {
            Database::getInstance()->getConnection()->exec($sql);
        } catch (\PDOException $e) {
            throw new \Exception("Schema operation failed: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Quote a table name for the current driver after validating it.
     */
    protected static function quote($name): string
    {
        if (!is_string($name) || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) {
            throw new \InvalidArgumentException('Invalid table name: ' . (is_scalar($name) ? $name : gettype($name)));
        }

        return self::driver() === 'mysql' ? "`{$name}`" : "\"{$name}\"";
    }

    /**
     * Current DB driver from env (mysql|sqlite|pgsql|...)
     */
    public static function driver(): string
    {
        $d = \Core\Support\Env::raw('DB_CONNECTION', 'mysql');
        return strtolower((string) $d);
    }

    public static function isSqlite(): bool
    {
        return self::driver() === 'sqlite';
    }
}
