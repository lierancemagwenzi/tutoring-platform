<?php

namespace Database\Seeders;

use App\Models\SessionFormat;
use Illuminate\Database\Seeder;

class SessionFormatSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $formats = [
            'Online',
            'In Person',
            'Hybrid',
        ];

        foreach ($formats as $name) {
            SessionFormat::firstOrCreate(['name' => $name], ['is_active' => true]);
        }
    }
}
