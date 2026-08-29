<?php

namespace Database\Seeders;

use App\Models\Subject;
use Illuminate\Database\Seeder;

class SubjectSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $subjects = [
            'Mathematics',
            'Physical Sciences',
            'Life Sciences',
            'Accounting',
            'Business Studies',
            'Economics',
            'English',
            'Xitsonga',
            'isiZulu',
            'Sesotho',
            'Geography',
            'History',
            'Computer Applications Technology',
            'Information Technology',
            'Mathematical Literacy',
        ];

        foreach ($subjects as $name) {
            Subject::firstOrCreate(['name' => $name], ['is_active' => true]);
        }
    }
}
