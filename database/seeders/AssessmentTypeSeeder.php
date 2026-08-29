<?php

namespace Database\Seeders;

use App\Models\AssessmentType;
use Illuminate\Database\Seeder;

class AssessmentTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
            'Quiz',
            'Assignment',
            'Homework',
            'Progress Test',
            'Mock Exam',
            'Final Assessment',
            'Oral Assessment',
        ];

        foreach ($types as $name) {
            AssessmentType::firstOrCreate(['name' => $name], ['is_active' => true]);
        }
    }
}
