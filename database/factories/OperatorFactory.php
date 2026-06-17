<?php

namespace Database\Factories;

use App\Models\Operator;
use App\Models\User;
use App\Enums\OperatorStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

class OperatorFactory extends Factory
{
    protected $model = Operator::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'status' => $this->faker->randomElement(OperatorStatus::cases()),
            'last_call_at' => null,
            'deleted_at' => Operator::getLiveTimestamp(),
        ];
    }
}
