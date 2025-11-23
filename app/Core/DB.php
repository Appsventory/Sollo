<?php

namespace App\Core;

class DB
{
    /**
     * Begin query for a table.
     */
    public static function table($table)
    {
        return new QueryBuilder($table);
    }

    /**
     * Execute raw SQL query.
     */
    public static function raw($sql, $bindings = [])
    {
        try {
            $pdo = Database::getInstance()->getConnection();
            $stmt = $pdo->prepare($sql);
            $stmt->execute($bindings);
            return $stmt;
        } catch (\PDOException $e) {
            throw new \Exception("Query failed: " . $e->getMessage());
        }
    }

    /**
     * Execute raw SQL and get results.
     */
    public static function select($sql, $bindings = [])
    {
        try {
            $stmt = self::raw($sql, $bindings);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Execute raw SQL and get first result.
     */
    public static function selectOne($sql, $bindings = [])
    {
        $results = self::select($sql, $bindings);
        return $results ? $results[0] : null;
    }

    /**
     * Execute insert/update/delete statement.
     */
    public static function statement($sql, $bindings = [])
    {
        try {
            $pdo = Database::getInstance()->getConnection();
            $stmt = $pdo->prepare($sql);
            return $stmt->execute($bindings);
        } catch (\PDOException $e) {
            throw new \Exception("Statement failed: " . $e->getMessage());
        }
    }

    /**
     * Get PDO connection.
     */
    public static function connection()
    {
        return Database::getInstance()->getConnection();
    }

    /**
     * Execute callback in transaction.
     */
    public static function transaction(callable $callback)
    {
        $pdo = self::connection();
        
        try {
            $pdo->beginTransaction();
            $result = $callback($pdo);
            $pdo->commit();
            return $result;
        } catch (\Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Begin transaction.
     */
    public static function beginTransaction()
    {
        return self::connection()->beginTransaction();
    }

    /**
     * Commit transaction.
     */
    public static function commit()
    {
        return self::connection()->commit();
    }

    /**
     * Rollback transaction.
     */
    public static function rollBack()
    {
        return self::connection()->rollBack();
    }
}

/**
 * Query Builder Class
 */
class QueryBuilder
{
    protected $table;
    protected $pdo;
    protected $selects = [];
    protected $wheres = [];
    protected $bindings = [];
    protected $joins = [];
    protected $groupBys = [];
    protected $havings = [];
    protected $orderBys = [];
    protected $limit = null;
    protected $offset = null;

    public function __construct($table)
    {
        $this->table = $table;
        $this->pdo = Database::getInstance()->getConnection();
    }

    /**
     * Select specific columns.
     */
    public function select($columns = ['*'])
    {
        if (is_string($columns)) {
            $columns = [$columns];
        }
        
        $this->selects = $columns;
        return $this;
    }

    /**
     * Add where clause.
     */
    public function where($column, $operator = '=', $value = null)
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $this->wheres[] = [
            'column' => $column,
            'operator' => $operator,
            'value' => $value
        ];
        
        $this->bindings[] = $value;
        return $this;
    }

    /**
     * Add OR where clause.
     */
    public function orWhere($column, $operator = '=', $value = null)
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $this->wheres[] = [
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => 'OR'
        ];
        
        $this->bindings[] = $value;
        return $this;
    }

    /**
     * Add where IN clause.
     */
    public function whereIn($column, $values)
    {
        $placeholders = implode(',', array_fill(0, count($values), '?'));
        
        $this->wheres[] = [
            'raw' => "{$column} IN ({$placeholders})"
        ];
        
        foreach ($values as $value) {
            $this->bindings[] = $value;
        }
        
        return $this;
    }

    /**
     * Add where NOT IN clause.
     */
    public function whereNotIn($column, $values)
    {
        $placeholders = implode(',', array_fill(0, count($values), '?'));
        
        $this->wheres[] = [
            'raw' => "{$column} NOT IN ({$placeholders})"
        ];
        
        foreach ($values as $value) {
            $this->bindings[] = $value;
        }
        
        return $this;
    }

    /**
     * Add where NULL clause.
     */
    public function whereNull($column)
    {
        $this->wheres[] = [
            'raw' => "{$column} IS NULL"
        ];
        
        return $this;
    }

    /**
     * Add where NOT NULL clause.
     */
    public function whereNotNull($column)
    {
        $this->wheres[] = [
            'raw' => "{$column} IS NOT NULL"
        ];
        
        return $this;
    }

    /**
     * Group by columns.
     */
    public function groupBy($columns)
    {
        if (is_string($columns)) {
            $columns = [$columns];
        }
        
        $this->groupBys = array_merge($this->groupBys, $columns);
        return $this;
    }

    /**
     * Order by column.
     */
    public function orderBy($column, $direction = 'ASC')
    {
        $this->orderBys[] = [
            'column' => $column,
            'direction' => strtoupper($direction)
        ];
        
        return $this;
    }

    /**
     * Limit results.
     */
    public function limit($limit)
    {
        $this->limit = $limit;
        return $this;
    }

    /**
     * Offset results.
     */
    public function offset($offset)
    {
        $this->offset = $offset;
        return $this;
    }

    /**
     * Paginate results.
     */
    public function paginate($perPage = 15, $page = 1)
    {
        $total = $this->count();
        $pages = ceil($total / $perPage);
        
        $offset = ($page - 1) * $perPage;
        
        return [
            'data' => $this->limit($perPage)->offset($offset)->get(),
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => $pages
        ];
    }

    /**
     * Get all results.
     */
    public function get()
    {
        $sql = $this->toSql();
        
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($this->bindings);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            throw new \Exception("Query failed: " . $e->getMessage());
        }
    }

    /**
     * Get first result.
     */
    public function first()
    {
        $results = $this->limit(1)->get();
        return $results ? $results[0] : null;
    }

    /**
     * Get count.
     */
    public function count()
    {
        $this->selects = ['COUNT(*) as count'];
        $sql = $this->toSql();
        
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($this->bindings);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $result['count'] ?? 0;
        } catch (\PDOException $e) {
            return 0;
        }
    }

    /**
     * Get sum of column.
     */
    public function sum($column)
    {
        $this->selects = ["SUM({$column}) as total"];
        $result = $this->first();
        return $result['total'] ?? 0;
    }

    /**
     * Get max value.
     */
    public function max($column)
    {
        $this->selects = ["MAX({$column}) as max"];
        $result = $this->first();
        return $result['max'] ?? null;
    }

    /**
     * Get min value.
     */
    public function min($column)
    {
        $this->selects = ["MIN({$column}) as min"];
        $result = $this->first();
        return $result['min'] ?? null;
    }

    /**
     * Insert records.
     */
    public function insert(array $records)
    {
        // Handle single record
        if (!isset($records[0]) || !is_array($records[0])) {
            $records = [$records];
        }

        if (empty($records)) {
            return false;
        }

        $columns = array_keys($records[0]);
        $columnsList = implode(',', $columns);
        $values = [];
        $bindings = [];

        foreach ($records as $record) {
            $placeholders = [];
            foreach ($columns as $column) {
                $placeholders[] = '?';
                $bindings[] = $record[$column] ?? null;
            }
            $values[] = '(' . implode(',', $placeholders) . ')';
        }

        $sql = "INSERT INTO {$this->table} ({$columnsList}) VALUES " . implode(',', $values);

        try {
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($bindings);
        } catch (\PDOException $e) {
            throw new \Exception("Insert failed: " . $e->getMessage());
        }
    }

    /**
     * Insert and get last insert ID.
     */
    public function insertGetId(array $record)
    {
        $this->insert($record);
        return Database::getInstance()->lastInsertId();
    }

    /**
     * Update records.
     */
    public function update(array $values)
    {
        $sets = [];
        $bindings = [];

        foreach ($values as $column => $value) {
            $sets[] = "{$column} = ?";
            $bindings[] = $value;
        }

        $sql = "UPDATE {$this->table} SET " . implode(',', $sets);

        if (!empty($this->wheres)) {
            $sql .= " WHERE " . $this->buildWhereClauses();
            $bindings = array_merge($bindings, $this->bindings);
        }

        try {
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($bindings);
        } catch (\PDOException $e) {
            throw new \Exception("Update failed: " . $e->getMessage());
        }
    }

    /**
     * Delete records.
     */
    public function delete()
    {
        $sql = "DELETE FROM {$this->table}";

        if (!empty($this->wheres)) {
            $sql .= " WHERE " . $this->buildWhereClauses();
        }

        try {
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($this->bindings);
        } catch (\PDOException $e) {
            throw new \Exception("Delete failed: " . $e->getMessage());
        }
    }

    /**
     * Truncate table.
     */
    public function truncate()
    {
        try {
            $this->pdo->exec("TRUNCATE TABLE {$this->table}");
            return true;
        } catch (\PDOException $e) {
            throw new \Exception("Truncate failed: " . $e->getMessage());
        }
    }

    /**
     * Build SQL query.
     */
    protected function toSql()
    {
        $sql = "SELECT " . implode(', ', $this->selects ?: ['*']);
        $sql .= " FROM {$this->table}";

        if (!empty($this->wheres)) {
            $sql .= " WHERE " . $this->buildWhereClauses();
        }

        if (!empty($this->groupBys)) {
            $sql .= " GROUP BY " . implode(', ', $this->groupBys);
        }

        if (!empty($this->orderBys)) {
            $orderParts = [];
            foreach ($this->orderBys as $order) {
                $orderParts[] = "{$order['column']} {$order['direction']}";
            }
            $sql .= " ORDER BY " . implode(', ', $orderParts);
        }

        if ($this->limit) {
            $sql .= " LIMIT {$this->limit}";
        }

        if ($this->offset) {
            $sql .= " OFFSET {$this->offset}";
        }

        return $sql;
    }

    /**
     * Build WHERE clauses.
     */
    protected function buildWhereClauses()
    {
        $clauses = [];
        
        foreach ($this->wheres as $where) {
            if (isset($where['raw'])) {
                $clauses[] = $where['raw'];
            } else {
                $boolean = isset($where['boolean']) ? $where['boolean'] : 'AND';
                $clauses[] = "{$where['column']} {$where['operator']} ?";
                
                if (count($clauses) > 1 && !isset($where['boolean'])) {
                    $clauses[count($clauses) - 1] = $boolean . ' ' . $clauses[count($clauses) - 1];
                }
            }
        }

        return implode(' ', $clauses);
    }

    /**
     * Convert to array.
     */
    public function toArray()
    {
        return $this->get();
    }

    /**
     * Convert to JSON.
     */
    public function toJson()
    {
        return json_encode($this->get());
    }
}