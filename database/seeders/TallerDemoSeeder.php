<?php

namespace Database\Seeders;

use App\Models\IngresoTaller;
use Illuminate\Database\Seeder;

class TallerDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('local', 'testing')) {
            IngresoTaller::factory()->emitido()->create();
        }
    }
}
