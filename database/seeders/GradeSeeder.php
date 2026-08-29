<?php

namespace Database\Seeders;

use App\Models\Grade;
use Illuminate\Database\Seeder;

class GradeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        for ($level = 8; $level <= 12; $level++) {
            Grade::firstOrCreate(
                ['level' => $level],
                ['name' => "Grade {$level}", 'is_active' => true],
            );
        }
    }
}
