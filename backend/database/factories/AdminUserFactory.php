<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

class AdminUserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'      => 'Admin ' . fake()->unique()->numberBetween(1000, 9999),
            'email'     => fake()->unique()->safeEmail(),
            'password'  => Hash::make('password'),
            'role'      => 'admin',
            'is_active' => true,
        ];
    }
}
