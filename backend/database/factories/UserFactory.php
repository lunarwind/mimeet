<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'nickname' => '用戶' . fake()->unique()->numberBetween(1000, 9999),
            'gender' => fake()->randomElement(['male', 'female']),
            'birth_date' => fake()->dateTimeBetween('-40 years', '-18 years')->format('Y-m-d'),
            'membership_level' => 1,
            'credit_score' => 60,
            'status' => 'active',
            'email_verified' => true,
            'phone_verified' => false,
            'remember_token' => Str::random(10),
        ];
    }
}
