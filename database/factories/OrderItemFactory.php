<?php

namespace Database\Factories;

use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'daily_menu_id' => null,
            'product_name' => 'Menu uji',
            'unit_price' => 15000,
            'quantity' => 2,
            'line_total' => 30000,
        ];
    }
}
