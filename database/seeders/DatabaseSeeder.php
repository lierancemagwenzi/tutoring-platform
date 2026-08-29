<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(GradeSeeder::class);
        $this->call(SubjectSeeder::class);
        $this->call(ServiceCategorySeeder::class);
        $this->call(SessionFormatSeeder::class);
        $this->call(LearningResourceSeeder::class);
        $this->call(AssessmentTypeSeeder::class);
        $this->call(CurriculumSeeder::class);
        $this->call(FaqSeeder::class);
    }
}
