<?php

namespace Database\Factories;

use App\Modules\Identity\Models\User;
use App\Modules\People\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'person_id' => Person::factory(),
            'username' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->unique()->e164PhoneNumber(),
            'password' => static::$password ??= Hash::make('password'),
            'status' => User::STATUS_ACTIVE,
            'remember_token' => Str::random(10),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['status' => User::STATUS_INACTIVE]);
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => ['status' => User::STATUS_SUSPENDED]);
    }

    public function superAdmin(): static
    {
        return $this->state(fn (array $attributes) => ['is_super_admin' => true]);
    }
}
