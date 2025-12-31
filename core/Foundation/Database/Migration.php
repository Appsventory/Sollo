<?php

namespace Core\Foundation\Database;

use Core\Foundation\Database\Database;

abstract class Migration
{
    /**
     * Run the migrations.
     */
    abstract public function up();

    /**
     * Reverse the migrations.
     */
    abstract public function down();

    /**
     * Get database connection
     */
    protected function getConnection()
    {
        return Database::getInstance()->getConnection();
    }

    /**
     * Execute raw SQL
     */
    protected function execute($sql)
    {
        try {
            $pdo = $this->getConnection();
            $pdo->exec($sql);
            return true;
        } catch (\PDOException $e) {
            throw new \Exception("Migration failed: " . $e->getMessage());
        }
    }

    /**
     * Execute query and return results
     */
    protected function query($sql)
    {
        try {
            $pdo = $this->getConnection();
            $stmt = $pdo->query($sql);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            throw new \Exception("Query failed: " . $e->getMessage());
        }
    }
}
