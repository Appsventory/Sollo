<?php

namespace App\Core;

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
            // Try to instantiate from string
            $seederName = $seeder;
            
            // Try different namespaces
            $namespaces = [
                'Database\\Seeders\\',
                'database\\seeders\\',
            ];

            foreach ($namespaces as $namespace) {
                $className = $namespace . $seederName;
                
                if (class_exists($className)) {
                    $seeder = new $className();
                    break;
                }
            }

            // If still string, try without namespace
            if (is_string($seeder)) {
                if (class_exists($seeder)) {
                    $seeder = new $seeder();
                } else {
                    echo "⚠️  Seeder not found: {$seederName}\n";
                    return;
                }
            }
        }

        if ($seeder instanceof Seeder) {
            echo "Running: " . get_class($seeder) . "\n";
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

    /**
     * Helper: Generate faker data (placeholder)
     */
    protected function fake()
    {
        return new FakeDataGenerator();
    }

    /**
     * Helper: Repeat array values
     */
    protected function repeat(array $data, $times)
    {
        $result = [];
        for ($i = 0; $i < $times; $i++) {
            $result[] = $data;
        }
        return $result;
    }
}

/**
 * Simple Fake Data Generator
 */
class FakeDataGenerator
{
    public function name()
    {
        $firstNames = ['John', 'Jane', 'Michael', 'Sarah', 'David', 'Emma', 'Robert', 'Lisa'];
        $lastNames = ['Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis'];
        
        return $firstNames[array_rand($firstNames)] . ' ' . $lastNames[array_rand($lastNames)];
    }

    public function email()
    {
        return 'user' . rand(1000, 9999) . '@example.com';
    }

    public function password()
    {
        return password_hash('password', PASSWORD_DEFAULT);
    }

    public function title()
    {
        $titles = [
            'The Future of Technology',
            'Getting Started with PHP',
            'Web Development Best Practices',
            'Database Design Principles',
            'Clean Code Essentials',
            'API Development Guide',
            'Security in Web Applications',
            'Performance Optimization Tips',
        ];
        
        return $titles[array_rand($titles)];
    }

    public function paragraph()
    {
        $paragraphs = [
            'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.',
            'Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.',
            'Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur.',
            'Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.',
            'Sed ut perspiciatis unde omnis iste natus error sit voluptatem accusantium doloremque laudantium.',
        ];
        
        return $paragraphs[array_rand($paragraphs)];
    }

    public function slug($text = null)
    {
        if (!$text) {
            $text = $this->title();
        }
        
        return strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $text), '-'));
    }

    public function number($min = 0, $max = 100)
    {
        return rand($min, $max);
    }

    public function boolean()
    {
        return (bool) rand(0, 1);
    }

    public function date($format = 'Y-m-d', $modifier = null)
    {
        $date = new \DateTime();
        
        if ($modifier) {
            $date->modify($modifier);
        }
        
        return $date->format($format);
    }

    public function datetime($format = 'Y-m-d H:i:s', $modifier = null)
    {
        return $this->date($format, $modifier);
    }

    public function uuid()
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    public function __call($method, $arguments)
    {
        // Fallback for unknown methods
        return 'fake_' . $method;
    }
}