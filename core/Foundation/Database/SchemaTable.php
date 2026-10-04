<?php

namespace Core\Foundation\Database;

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
        if (Schema::isSqlite()) {
            $this->columns[] = "\"{$name}\" INTEGER PRIMARY KEY AUTOINCREMENT";
        } else {
            $this->columns[] = "`{$name}` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY";
        }
        return $this;
    }

    public function increments($name)
    {
        if (Schema::isSqlite()) {
            $this->columns[] = "\"{$name}\" INTEGER PRIMARY KEY AUTOINCREMENT";
        } else {
            $this->columns[] = "`{$name}` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY";
        }
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
        if (Schema::isSqlite()) {
            $this->columns[] = '"created_at" DATETIME DEFAULT CURRENT_TIMESTAMP';
            $this->columns[] = '"updated_at" DATETIME DEFAULT CURRENT_TIMESTAMP';
        } else {
            $this->columns[] = "`created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP";
            $this->columns[] = "`updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP";
        }
        return $this;
    }

    public function softDeletes($name = 'deleted_at')
    {
        if (Schema::isSqlite()) {
            $this->columns[] = "\"{$name}\" DATETIME NULL DEFAULT NULL";
        } else {
            $this->columns[] = "`{$name}` TIMESTAMP NULL DEFAULT NULL";
        }
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
        if (Schema::isSqlite()) {
            return "CREATE TABLE \"{$this->tableName}\" (\n  {$columnsSql}\n)";
        }
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
