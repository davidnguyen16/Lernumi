<?php

namespace Database\Seeders;

use App\Models\Institution;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $institution = Institution::updateOrCreate(
            ['id' => '11111111-1111-4111-8111-111111111111'],
            ['name' => 'Lernumi Demo', 'website' => 'https://example.com'],
        );

        foreach ([
            ['subject_code' => 'COMP1000', 'subject_name' => 'Introduction to Computer Programming'],
            ['subject_code' => 'COMP1010', 'subject_name' => 'Fundamentals of Computer Science'],
        ] as $subject) {
            $institution->subjects()->updateOrCreate(
                ['subject_code' => $subject['subject_code']],
                ['subject_name' => $subject['subject_name'], 'subject_description' => null],
            );
        }
    }
}
