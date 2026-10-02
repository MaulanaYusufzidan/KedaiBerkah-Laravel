<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class DeliveryAreaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'district' => fake()->unique()->words(2, true),
            'delivery_fee' => 5000,
            'is_active' => true,
        ];
    }
}
