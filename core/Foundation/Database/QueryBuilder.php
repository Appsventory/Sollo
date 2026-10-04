<?php

namespace Core\Foundation\Database;

/**
 * Query Builder.
 *
 * Values are always bound as parameters. Identifiers (tables, columns, order
 * and group columns) and operators are validated, so they are safe to build
 * from controlled input; never pass raw user input to select().
 */
class QueryBuilder
{
    protected const OPERATORS = ['=', '<', '>', '<=', '>=', '<>', '!=', 'like', 'not like'];

    protected $table;
    protected $pdo;
    protected $selects = [];
    /** @var array<int, array{boolean:string, sql:string, bindings:array}> */
    protected $wheres = [];
    protected $groupBys = [];
    protected $orderBys = [];
    protected $limit = null;
    protected $offset = null;

    public function __construct($table)
    {
        $this->table = $this->identifier($table);
        $this->pdo = Database::getInstance()->getConnection();
    }

    // ------------------------------------------------------------------
    // Validation helpers
    // ------------------------------------------------------------------

    protected function identifier($name): string
    {
        if (!is_string($name) || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)*$|^\*$/', $name)) {
            throw new \InvalidArgumentException('Invalid SQL identifier: ' . (is_scalar($name) ? $name : gettype($name)));
        }

        return $name;
    }

    protected function selectExpression($column): string
    {
        if (!is_string($column)
            || !preg_match('/^(?:[A-Za-z_][\w.]*(?:\s+as\s+[A-Za-z_]\w*)?|\*|[A-Za-z_]\w*\.\*|(?:count|sum|min|max|avg)\(\s*(?:\*|[A-Za-z_][\w.]*)\s*\)(?:\s+as\s+[A-Za-z_]\w*)?)$/i', trim($column))
        ) {
            throw new \InvalidArgumentException('Invalid select expression: ' . (is_scalar($column) ? $column : gettype($column)));
        }

        return trim($column);
    }

    protected function operator($operator): string
    {
        $operator = strtolower(trim((string) $operator));

        if (!in_array($operator, self::OPERATORS, true)) {
            throw new \InvalidArgumentException("Invalid SQL operator: {$operator}");
        }

        return $operator;
    }

    // ------------------------------------------------------------------
    // Building
    // ------------------------------------------------------------------

    /**
     * Select specific columns.
     */
    public function select($columns = ['*'])
    {
        $this->selects = array_map(fn($c) => $this->selectExpression($c), (array) $columns);
        return $this;
    }

    /**
     * Add where clause:  where('a', 1)  where('a', '>', 1)  where('a', null)
     */
    public function where($column, $operator = null, $value = null)
    {
        if (func_num_args() === 2) {
            [$value, $operator] = [$operator, '='];
        }

        return $this->addWhere('AND', $column, $operator ?? '=', $value);
    }

    /**
     * Add OR where clause.
     */
    public function orWhere($column, $operator = null, $value = null)
    {
        if (func_num_args() === 2) {
            [$value, $operator] = [$operator, '='];
        }

        return $this->addWhere('OR', $column, $operator ?? '=', $value);
    }

    protected function addWhere(string $boolean, $column, $operator, $value)
    {
        $column = $this->identifier($column);
        $operator = $this->operator($operator);

        if ($value === null) {
            if (!in_array($operator, ['=', '<>', '!='], true)) {
                throw new \InvalidArgumentException("Operator '{$operator}' cannot be used with null.");
            }

            $this->wheres[] = [
                'boolean' => $boolean,
                'sql' => $column . ($operator === '=' ? ' IS NULL' : ' IS NOT NULL'),
                'bindings' => [],
            ];

            return $this;
        }

        $this->wheres[] = ['boolean' => $boolean, 'sql' => "{$column} {$operator} ?", 'bindings' => [$value]];
        return $this;
    }

    public function whereIn($column, $values)
    {
        return $this->addIn($this->identifier($column), (array) $values, false);
    }

    public function whereNotIn($column, $values)
    {
        return $this->addIn($this->identifier($column), (array) $values, true);
    }

    protected function addIn(string $column, array $values, bool $not)
    {
        $values = array_values($values);

        if (!$values) {
            // IN () matches nothing, NOT IN () matches everything
            $this->wheres[] = ['boolean' => 'AND', 'sql' => $not ? '1 = 1' : '0 = 1', 'bindings' => []];
            return $this;
        }

        $placeholders = implode(',', array_fill(0, count($values), '?'));
        $this->wheres[] = [
            'boolean' => 'AND',
            'sql' => $column . ($not ? ' NOT IN ' : ' IN ') . "({$placeholders})",
            'bindings' => $values,
        ];

        return $this;
    }

    public function whereNull($column)
    {
        $this->wheres[] = ['boolean' => 'AND', 'sql' => $this->identifier($column) . ' IS NULL', 'bindings' => []];
        return $this;
    }

    public function whereNotNull($column)
    {
        $this->wheres[] = ['boolean' => 'AND', 'sql' => $this->identifier($column) . ' IS NOT NULL', 'bindings' => []];
        return $this;
    }

    public function groupBy($columns)
    {
        foreach ((array) $columns as $column) {
            $this->groupBys[] = $this->identifier($column);
        }

        return $this;
    }

    public function orderBy($column, $direction = 'ASC')
    {
        $direction = strtoupper((string) $direction);

        if (!in_array($direction, ['ASC', 'DESC'], true)) {
            throw new \InvalidArgumentException("Invalid order direction: {$direction}");
        }

        $this->orderBys[] = ['column' => $this->identifier($column), 'direction' => $direction];
        return $this;
    }

    public function limit($limit)
    {
        $this->limit = $limit === null ? null : max(0, (int) $limit);
        return $this;
    }

    public function offset($offset)
    {
        $this->offset = $offset === null ? null : max(0, (int) $offset);
        return $this;
    }

    // ------------------------------------------------------------------
    // Reading
    // ------------------------------------------------------------------

    /**
     * Paginate results. $page defaults to the "page" query-string value.
     */
    public function paginate($perPage = 15, $page = null)
    {
        $perPage = max(1, (int) $perPage);
        $page = max(1, (int) ($page ?? ($_GET['page'] ?? 1)));

        $total = $this->count();

        return [
            'data' => (clone $this)->limit($perPage)->offset(($page - 1) * $perPage)->get(),
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    /**
     * Get all results.
     */
    public function get()
    {
        return $this->run($this->toSql(), $this->getBindings())->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get first result.
     */
    public function first()
    {
        $results = (clone $this)->limit(1)->get();
        return $results ? $results[0] : null;
    }

    /**
     * Get count (does not modify this builder).
     */
    public function count()
    {
        $query = clone $this;
        $query->selects = ['COUNT(*) as aggregate'];
        $query->orderBys = [];
        $query->limit = $query->offset = null;

        $row = $query->run($query->toSql(), $query->getBindings())->fetch(\PDO::FETCH_ASSOC);

        return (int) ($row['aggregate'] ?? 0);
    }

    public function sum($column)
    {
        return $this->aggregate('SUM', $column) ?? 0;
    }

    public function max($column)
    {
        return $this->aggregate('MAX', $column);
    }

    public function min($column)
    {
        return $this->aggregate('MIN', $column);
    }

    protected function aggregate(string $function, $column)
    {
        $query = clone $this;
        $query->selects = ["{$function}(" . $this->identifier($column) . ') as aggregate'];
        $query->orderBys = [];
        $query->limit = $query->offset = null;

        $row = $query->run($query->toSql(), $query->getBindings())->fetch(\PDO::FETCH_ASSOC);

        return $row['aggregate'] ?? null;
    }

    // ------------------------------------------------------------------
    // Writing
    // ------------------------------------------------------------------

    /**
     * Insert one record, or a list of records.
     */
    public function insert(array $records)
    {
        if (!$records) {
            throw new \InvalidArgumentException('insert() needs at least one column.');
        }

        if (!isset($records[0]) || !is_array($records[0])) {
            $records = [$records];
        }

        $columns = array_map(fn($c) => $this->identifier($c), array_keys($records[0]));
        $values = [];
        $bindings = [];

        foreach ($records as $record) {
            foreach ($columns as $column) {
                $bindings[] = $record[$column] ?? null;
            }
            $values[] = '(' . implode(',', array_fill(0, count($columns), '?')) . ')';
        }

        $sql = "INSERT INTO {$this->table} (" . implode(',', $columns) . ') VALUES ' . implode(',', $values);

        return $this->run($sql, $bindings)->rowCount() > 0;
    }

    /**
     * Insert and get last insert ID. PostgreSQL: pass the sequence name when it is not {table}_id_seq.
     */
    public function insertGetId(array $record, ?string $sequence = null)
    {
        $this->insert($record);

        if ($this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'pgsql') {
            $sequence ??= "{$this->table}_id_seq";
        }

        return $this->pdo->lastInsertId($sequence);
    }

    /**
     * Update records. Returns the number of affected rows.
     * Without a where() clause every row is updated.
     */
    public function update(array $values)
    {
        if (!$values) {
            throw new \InvalidArgumentException('update() needs at least one column.');
        }

        $sets = [];
        $bindings = [];

        foreach ($values as $column => $value) {
            $sets[] = $this->identifier($column) . ' = ?';
            $bindings[] = $value;
        }

        $sql = "UPDATE {$this->table} SET " . implode(', ', $sets);

        if ($where = $this->compileWhere()) {
            $sql .= " WHERE {$where}";
            $bindings = array_merge($bindings, $this->getBindings());
        }

        return $this->run($sql, $bindings)->rowCount();
    }

    /**
     * Delete records. Returns the number of deleted rows.
     * Without a where() clause every row is deleted.
     */
    public function delete()
    {
        $sql = "DELETE FROM {$this->table}";

        if ($where = $this->compileWhere()) {
            $sql .= " WHERE {$where}";
        }

        return $this->run($sql, $this->getBindings())->rowCount();
    }

    /**
     * Remove all rows (TRUNCATE on MySQL/PostgreSQL, DELETE on SQLite).
     */
    public function truncate()
    {
        if ($this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $this->pdo->exec("DELETE FROM {$this->table}");
            $this->pdo->exec("DELETE FROM sqlite_sequence WHERE name = " . $this->pdo->quote($this->table));
        } else {
            $this->pdo->exec("TRUNCATE TABLE {$this->table}");
        }

        return true;
    }

    // ------------------------------------------------------------------
    // SQL generation
    // ------------------------------------------------------------------

    protected function run(string $sql, array $bindings): \PDOStatement
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($bindings);
            return $stmt;
        } catch (\PDOException $e) {
            throw new \RuntimeException('Query failed: ' . $e->getMessage() . " [SQL: {$sql}]", 0, $e);
        }
    }

    /**
     * Build the SELECT statement.
     */
    public function toSql()
    {
        $sql = 'SELECT ' . implode(', ', $this->selects ?: ['*']) . " FROM {$this->table}";

        if ($where = $this->compileWhere()) {
            $sql .= " WHERE {$where}";
        }

        if ($this->groupBys) {
            $sql .= ' GROUP BY ' . implode(', ', $this->groupBys);
        }

        if ($this->orderBys) {
            $sql .= ' ORDER BY ' . implode(', ', array_map(fn($o) => "{$o['column']} {$o['direction']}", $this->orderBys));
        }

        $limit = $this->limit;
        if ($limit === null && $this->offset !== null) {
            $driver = $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
            $limit = $driver === 'sqlite' ? -1 : ($driver === 'mysql' ? '18446744073709551615' : null);
        }

        if ($limit !== null) {
            $sql .= " LIMIT {$limit}";
        }

        if ($this->offset !== null) {
            $sql .= " OFFSET {$this->offset}";
        }

        return $sql;
    }

    /**
     * WHERE clause (without the keyword), "" when there are no conditions.
     */
    protected function compileWhere(): string
    {
        $sql = '';

        foreach ($this->wheres as $i => $where) {
            $sql .= ($i === 0 ? '' : " {$where['boolean']} ") . $where['sql'];
        }

        return $sql;
    }

    /**
     * Parameter values, in the order of the placeholders.
     */
    public function getBindings(): array
    {
        $bindings = [];

        foreach ($this->wheres as $where) {
            array_push($bindings, ...$where['bindings']);
        }

        return $bindings;
    }

    public function toArray()
    {
        return $this->get();
    }

    public function toJson()
    {
        return json_encode($this->get());
    }
}
