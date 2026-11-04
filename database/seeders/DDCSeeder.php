<?php

namespace Database\Seeders;

use App\Services\DdcRangeSynchronizer;
use Illuminate\Database\Seeder;

class DDCSeeder extends Seeder
{
    public function run(): void
    {
        app(DdcRangeSynchronizer::class)->syncAll();
    }
}
