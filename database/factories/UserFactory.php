<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('##########'),
            'date_of_birth' => fake()->date(),
            'password' => static::$password ??= Hash::make('password'),
            'email_verified_at' => now(),
            'role' => UserRole::Student,
            'status' => UserStatus::Pending,
        ];
    }

    /**
     * Indicate that the user is a tutor.
     */
    public function tutor(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Tutor,
            'status' => UserStatus::Approved,
        ]);
    }

    /**
     * Indicate that the user is a platform administrator.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Admin,
            'status' => UserStatus::Approved,
        ]);
    }

    /**
     * Indicate that the user's email address has not been verified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
