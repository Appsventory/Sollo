<?php

namespace Core\Foundation\Seeding;

use Core\Foundation\Database\DB;

abstract class Seeder
{
    /**
     * Run the database seeds.
     */
    abstract public function run();

    /**
     * Resolve a seeder instance.
     */
    protected function call($seeders)
    {
        if (is_string($seeders)) {
            $seeders = [$seeders];
        }

        foreach ($seeders as $seeder) {
            // Add Seeder suffix if not present
            if (is_string($seeder) && !str_ends_with($seeder, 'Seeder')) {
                $seeder = $seeder . 'Seeder';
            }

            if (is_string($seeder)) {
                $this->callSeeder($seeder);
            } elseif ($seeder instanceof Seeder) {
                $this->callSeeder($seeder);
            }
        }
    }

    /**
     * Call a seeder instance.
     */
    protected function callSeeder($seeder)
    {
        if (is_string($seeder)) {
            // For class references like TeamSeeder::class or App\Database\Seeders\TeamSeeder
            $seederClass = $seeder;

            // If just the class name without namespace, try to find it
            if (strpos($seederClass, '\\') === false) {
                // Try different namespaces
                $namespaces = [
                    'App\\Database\\Seeders\\',
                    'Database\\Seeders\\',
                ];

                foreach ($namespaces as $namespace) {
                    $testClass = $namespace . $seederClass;
                    if (class_exists($testClass)) {
                        $seederClass = $testClass;
                        break;
                    }
                }
            }

            // Try to instantiate
            if (class_exists($seederClass)) {
                $seeder = new $seederClass();
            } else {
                throw new \RuntimeException("Seeder class {$seeder} not found.");
            }
        }

        if ($seeder instanceof Seeder) {
            $seeder->run();
        }
    }
    /**
     * Get database query builder.
     */
    protected function table($table)
    {
        return DB::table($table);
    }

    /**
     * Insert records into table.
     */
    protected function insert($table, array $records)
    {
        return DB::table($table)->insert($records);
    }

    /**
     * Delete records from table.
     */
    protected function delete($table, $where = null)
    {
        $query = DB::table($table);

        if ($where && is_array($where)) {
            foreach ($where as $column => $value) {
                $query->where($column, $value);
            }
        }

        return $query->delete();
    }

    /**
     * Truncate table.
     */
    protected function truncate($table)
    {
        return DB::table($table)->truncate();
    }

    /**
     * Execute raw SQL.
     */
    protected function execute($sql)
    {
        return DB::raw($sql);
    }

    /**
     * Get count of records.
     */
    protected function count($table, $where = null)
    {
        $query = DB::table($table);

        if ($where && is_array($where)) {
            foreach ($where as $column => $value) {
                $query->where($column, $value);
            }
        }

        return $query->count();
    }

}
