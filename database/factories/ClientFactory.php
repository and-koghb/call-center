<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClientFactory extends Factory
{
    protected $model = Client::class;

    public function definition(): array
    {
        return [
            'phone' => $this->faker->unique()->numerify('+37499######'),
            'name' => $this->faker->name(),
            'deleted_at' => Client::getLiveTimestamp(),
        ];
    }

    public function deleted(): static
    {
        return $this->state(fn (array $attributes) => [
            'deleted_at' => now()->subDays(rand(1, 30)),
        ]);
    }
}
