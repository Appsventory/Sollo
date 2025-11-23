<?php

use App\Core\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run()
    {
        echo "\n🌱 Starting database seeding...\n";
        echo str_repeat('─', 50) . "\n";

        // Call individual seeders in order
        // $this->call([
        //     UserSeeder::class,
        //     PostSeeder::class,
        //     CategorySeeder::class,
        // ]);

        // Or call them one by one with custom messages
        // $this->callWithMessage(UserSeeder::class, 'Seeding users table');
        // $this->callWithMessage(PostSeeder::class, 'Seeding posts table');

        echo str_repeat('─', 50) . "\n";
        echo "🎉 Database seeding completed successfully!\n\n";
    }

    /**
     * Call a seeder with a custom message
     */
    protected function callWithMessage($seederClass, $message)
    {
        echo "📋 {$message}...\n";
        $this->call($seederClass);
    }

    /**
     * Call multiple seeders
     */
    protected function call($seeders)
    {
        if (is_string($seeders)) {
            $seeders = [$seeders];
        }

        foreach ($seeders as $seeder) {
            if (class_exists($seeder)) {
                $instance = new $seeder();
                if (method_exists($instance, 'run')) {
                    $instance->run();
                } else {
                    echo "⚠️  Seeder {$seeder} does not have a run() method.\n";
                }
            } else {
                echo "❌ Seeder class {$seeder} not found.\n";
            }
        }
    }
}