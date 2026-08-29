<?php

namespace Database\Seeders;

use App\Models\Curriculum;
use Illuminate\Database\Seeder;

class CurriculumSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $curricula = [
            'CAPS',
            'IEB',
            'Cambridge',
            'Other',
        ];

        foreach ($curricula as $name) {
            Curriculum::firstOrCreate(['name' => $name], ['is_active' => true]);
        }
    }
}
