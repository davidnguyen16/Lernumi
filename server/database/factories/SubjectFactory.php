<?php

namespace Database\Factories;

use App\Models\Institution;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Subject> */
class SubjectFactory extends Factory
{
    protected $model = Subject::class;

    public function definition(): array
    {
        return [
            'institution_id' => Institution::factory(),
            'subject_code' => 'COMP'.fake()->unique()->numberBetween(1000, 999999),
            'subject_name' => fake()->sentence(3),
            'subject_description' => fake()->paragraph(),
        ];
    }
}
