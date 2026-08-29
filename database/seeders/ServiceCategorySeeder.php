<?php

namespace Database\Seeders;

use App\Models\ServiceCategory;
use Illuminate\Database\Seeder;

class ServiceCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            'Private Lesson',
            'Group Lesson',
            'Monthly Tutoring',
            'Homework Assistance',
            'Exam Preparation',
            'Crash Course',
            'Holiday Programme',
            'Study Group',
        ];

        foreach ($categories as $name) {
            ServiceCategory::firstOrCreate(['name' => $name], ['is_active' => true]);
        }
    }
}
