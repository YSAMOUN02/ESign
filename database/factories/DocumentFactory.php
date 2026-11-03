<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DocumentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(3),
            'original_name' => 'contract.pdf',
            'original_path' => 'documents/original/test.pdf',
            'original_hash' => str_repeat('a', 64),
            'page_count' => 1,
            'status' => 'draft',
        ];
    }
}
