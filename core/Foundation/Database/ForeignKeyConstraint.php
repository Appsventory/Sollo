<?php

namespace Core\Foundation\Database;

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
