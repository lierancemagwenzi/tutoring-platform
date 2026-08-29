<?php

namespace Database\Seeders;

use App\Models\LearningResource;
use Illuminate\Database\Seeder;

class LearningResourceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $resources = [
            'Study Notes',
            'Worksheets',
            'Practice Questions',
            'Past Exam Papers',
            'Slides',
            'Formula Sheets',
            'Reading Material',
            'Video Lessons',
            'Homework',
            'Other',
        ];

        foreach ($resources as $name) {
            LearningResource::firstOrCreate(['name' => $name], ['is_active' => true]);
        }
    }
}
