<?php

namespace Core\Foundation;

use Core\Foundation\Database\Database;

abstract class Seeder
{
    /**
     * Run the seeder
     */
    abstract public function run();

    /**
     * Call another seeder
     */
    protected function call($seeders)
    {
        if (!is_array($seeders)) {
            $seeders = [$seeders];
        }

        foreach ($seeders as $seeder) {
            if (is_string($seeder)) {
                $seeder = new $seeder();
            }

            if ($seeder instanceof Seeder) {
                $seeder->run();
            }
        }
    }
}
