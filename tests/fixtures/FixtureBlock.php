<?php

namespace App\Middleware;

class FixtureBlock
{
    public function handle()
    {
        throw new \Core\Foundation\Http\HttpException(401, 'blocked by FixtureBlock');
    }
}
