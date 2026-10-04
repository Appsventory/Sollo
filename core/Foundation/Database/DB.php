<?php

namespace Core\Foundation\Database;

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
            throw new \Exception("Query failed: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Execute raw SQL and get results.
     */
    public static function select($sql, $bindings = [])
    {
        return self::raw($sql, $bindings)->fetchAll(\PDO::FETCH_ASSOC);
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
            throw new \Exception("Statement failed: " . $e->getMessage(), 0, $e);
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
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
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
