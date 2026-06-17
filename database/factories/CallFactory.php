<?php

namespace Database\Factories;

use App\Enums\CallStatus;
use App\Models\Call;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

class CallFactory extends Factory
{
    protected $model = Call::class;

    public function definition(): array
    {
        return [
            'phone' => $this->faker->numerify('+37499######'),
            'status' => CallStatus::NEW,
            'client_id' => null,
            'operator_id' => null,
            'created_at' => $this->faker->dateTimeBetween('-7 days', 'now'),
        ];
    }

    public function success(): static
    {
        return $this->state(function (array $attributes) {
            $createdAt = isset($attributes['created_at']) ? Carbon::parse($attributes['created_at']) : now();

            return [
                'status' => CallStatus::SUCCESS,
                'finished_at' => $createdAt->copy()->addMinutes(rand(1, 10)),
            ];
        });
    }

    public function missed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CallStatus::MISSED,
            'operator_id' => null,
        ]);
    }

    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CallStatus::IN_PROGRESS,
        ]);
    }
}
