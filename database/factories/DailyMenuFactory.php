<?php

namespace Database\Factories;

use App\Enums\DailyMenuStatus;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class DailyMenuFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'menu_date' => today(),
            'price' => 15000,
            'stock' => 20,
            'available_from' => null,
            'available_until' => null,
            'status' => DailyMenuStatus::Available,
            'notes' => null,
        ];
    }
}
