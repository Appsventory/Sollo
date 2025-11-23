<?php

namespace App\Core;

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

    /**
     * Drop a table if it exists
     */
    public static function dropIfExists($tableName)
    {
        $sql = "DROP TABLE IF EXISTS `{$tableName}`";
        self::execute($sql);
    }

    /**
     * Drop a table
     */
    public static function drop($tableName)
    {
        $sql = "DROP TABLE `{$tableName}`";
        self::execute($sql);
    }

    /**
     * Check if table exists
     */
    public static function hasTable($tableName)
    {
        $sql = "SHOW TABLES LIKE '{$tableName}'";
        $result = self::query($sql);
        return !empty($result);
    }

    /**
     * Check if column exists in table
     */
    public static function hasColumn($tableName, $columnName)
    {
        $sql = "SHOW COLUMNS FROM `{$tableName}` LIKE '{$columnName}'";
        $result = self::query($sql);
        return !empty($result);
    }

    /**
     * Rename a table
     */
    public static function rename($from, $to)
    {
        $sql = "RENAME TABLE `{$from}` TO `{$to}`";
        self::execute($sql);
    }

    /**
     * Execute SQL
     */
    protected static function execute($sql)
    {
        try {
            $pdo = Database::getInstance()->getConnection();
            $pdo->exec($sql);
        } catch (\PDOException $e) {
            throw new \Exception("Schema operation failed: " . $e->getMessage());
        }
    }

    /**
     * Execute query
     */
    protected static function query($sql)
    {
        try {
            $pdo = Database::getInstance()->getConnection();
            $stmt = $pdo->query($sql);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            throw new \Exception("Query failed: " . $e->getMessage());
        }
    }
}

class SchemaTable
{
    protected $tableName;
    protected $columns = [];
    protected $isAlter = false;

    public function __construct($tableName, $isAlter = false)
    {
        $this->tableName = $tableName;
        $this->isAlter = $isAlter;
    }

    // Primary key
    public function id($name = 'id')
    {
        return $this->bigIncrements($name);
    }

    // Integer types
    public function bigIncrements($name)
    {
        $this->columns[] = "`{$name}` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY";
        return $this;
    }

    public function increments($name)
    {
        $this->columns[] = "`{$name}` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY";
        return $this;
    }

    public function integer($name)
    {
        $this->columns[] = "`{$name}` INT";
        return $this;
    }

    public function bigInteger($name)
    {
        $this->columns[] = "`{$name}` BIGINT";
        return $this;
    }

    public function tinyInteger($name)
    {
        $this->columns[] = "`{$name}` TINYINT";
        return $this;
    }

    public function smallInteger($name)
    {
        $this->columns[] = "`{$name}` SMALLINT";
        return $this;
    }

    public function mediumInteger($name)
    {
        $this->columns[] = "`{$name}` MEDIUMINT";
        return $this;
    }

    // String types
    public function string($name, $length = 255)
    {
        $this->columns[] = "`{$name}` VARCHAR({$length})";
        return $this;
    }

    public function char($name, $length = 255)
    {
        $this->columns[] = "`{$name}` CHAR({$length})";
        return $this;
    }

    public function text($name)
    {
        $this->columns[] = "`{$name}` TEXT";
        return $this;
    }

    public function mediumText($name)
    {
        $this->columns[] = "`{$name}` MEDIUMTEXT";
        return $this;
    }

    public function longText($name)
    {
        $this->columns[] = "`{$name}` LONGTEXT";
        return $this;
    }

    // Float and decimal
    public function float($name, $total = 8, $places = 2)
    {
        $this->columns[] = "`{$name}` FLOAT({$total}, {$places})";
        return $this;
    }

    public function double($name, $total = 15, $places = 8)
    {
        $this->columns[] = "`{$name}` DOUBLE({$total}, {$places})";
        return $this;
    }

    public function decimal($name, $total = 8, $places = 2)
    {
        $this->columns[] = "`{$name}` DECIMAL({$total}, {$places})";
        return $this;
    }

    // Boolean
    public function boolean($name)
    {
        $this->columns[] = "`{$name}` TINYINT(1)";
        return $this;
    }

    // Date and time
    public function date($name)
    {
        $this->columns[] = "`{$name}` DATE";
        return $this;
    }

    public function datetime($name)
    {
        $this->columns[] = "`{$name}` DATETIME";
        return $this;
    }

    public function timestamp($name)
    {
        $this->columns[] = "`{$name}` TIMESTAMP";
        return $this;
    }

    public function time($name)
    {
        $this->columns[] = "`{$name}` TIME";
        return $this;
    }

    public function year($name)
    {
        $this->columns[] = "`{$name}` YEAR";
        return $this;
    }

    // Binary
    public function binary($name)
    {
        $this->columns[] = "`{$name}` BLOB";
        return $this;
    }

    // JSON
    public function json($name)
    {
        $this->columns[] = "`{$name}` JSON";
        return $this;
    }

    // Enum
    public function enum($name, array $values)
    {
        $valueList = "'" . implode("','", $values) . "'";
        $this->columns[] = "`{$name}` ENUM({$valueList})";
        return $this;
    }

    // Special columns
    public function timestamps()
    {
        $this->columns[] = "`created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP";
        $this->columns[] = "`updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP";
        return $this;
    }

    public function softDeletes($name = 'deleted_at')
    {
        $this->columns[] = "`{$name}` TIMESTAMP NULL DEFAULT NULL";
        return $this;
    }

    public function rememberToken()
    {
        $this->columns[] = "`remember_token` VARCHAR(100) NULL";
        return $this;
    }

    // Foreign key
    public function foreignId($name)
    {
        $this->columns[] = "`{$name}` BIGINT UNSIGNED";
        return $this;
    }

    public function foreign($column)
    {
        return new ForeignKeyConstraint($column, $this);
    }

    // Modifiers
    public function nullable()
    {
        $lastIndex = count($this->columns) - 1;
        if ($lastIndex >= 0) {
            $this->columns[$lastIndex] .= " NULL";
        }
        return $this;
    }

    public function default($value)
    {
        $lastIndex = count($this->columns) - 1;
        if ($lastIndex >= 0) {
            if (is_string($value)) {
                $value = "'{$value}'";
            } elseif (is_bool($value)) {
                $value = $value ? '1' : '0';
            } elseif (is_null($value)) {
                $value = 'NULL';
            }
            $this->columns[$lastIndex] .= " DEFAULT {$value}";
        }
        return $this;
    }

    public function unique()
    {
        $lastIndex = count($this->columns) - 1;
        if ($lastIndex >= 0) {
            $this->columns[$lastIndex] .= " UNIQUE";
        }
        return $this;
    }

    public function unsigned()
    {
        $lastIndex = count($this->columns) - 1;
        if ($lastIndex >= 0) {
            $this->columns[$lastIndex] = str_replace('INT', 'INT UNSIGNED', $this->columns[$lastIndex]);
        }
        return $this;
    }

    public function comment($comment)
    {
        $lastIndex = count($this->columns) - 1;
        if ($lastIndex >= 0) {
            $this->columns[$lastIndex] .= " COMMENT '{$comment}'";
        }
        return $this;
    }

    public function after($column)
    {
        $lastIndex = count($this->columns) - 1;
        if ($lastIndex >= 0 && $this->isAlter) {
            $this->columns[$lastIndex] .= " AFTER `{$column}`";
        }
        return $this;
    }

    // Indexes
    public function index($columns)
    {
        if (is_string($columns)) {
            $columns = [$columns];
        }
        $columnList = '`' . implode('`, `', $columns) . '`';
        $indexName = implode('_', $columns) . '_index';
        $this->columns[] = "INDEX `{$indexName}` ({$columnList})";
        return $this;
    }

    public function primary($columns)
    {
        if (is_string($columns)) {
            $columns = [$columns];
        }
        $columnList = '`' . implode('`, `', $columns) . '`';
        $this->columns[] = "PRIMARY KEY ({$columnList})";
        return $this;
    }

    // Generate SQL
    public function toSql()
    {
        $columnsSql = implode(",\n  ", $this->columns);
        return "CREATE TABLE `{$this->tableName}` (\n  {$columnsSql}\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    }

    public function toAlterSql()
    {
        $alterations = [];
        foreach ($this->columns as $column) {
            $alterations[] = "ADD COLUMN {$column}";
        }
        $alterationsSql = implode(",\n  ", $alterations);
        return "ALTER TABLE `{$this->tableName}`\n  {$alterationsSql}";
    }
}

class ForeignKeyConstraint
{
    protected $column;
    protected $table;
    protected $references;
    protected $on;
    protected $onDelete;
    protected $onUpdate;

    public function __construct($column, $table)
    {
        $this->column = $column;
        $this->table = $table;
    }

    public function references($column)
    {
        $this->references = $column;
        return $this;
    }

    public function on($table)
    {
        $this->on = $table;
        return $this;
    }

    public function onDelete($action)
    {
        $this->onDelete = $action;
        return $this;
    }

    public function onUpdate($action)
    {
        $this->onUpdate = $action;
        return $this;
    }

    public function constrained($table = null)
    {
        if ($table) {
            $this->on = $table;
        } else {
            // Auto-detect table name from column (e.g., user_id -> users)
            $tableName = str_replace('_id', '', $this->column);
            $this->on = $tableName . 's';
        }
        $this->references = 'id';
        return $this;
    }

    public function cascadeOnDelete()
    {
        $this->onDelete = 'CASCADE';
        return $this;
    }

    public function nullOnDelete()
    {
        $this->onDelete = 'SET NULL';
        return $this;
    }

    public function restrictOnDelete()
    {
        $this->onDelete = 'RESTRICT';
        return $this;
    }
}