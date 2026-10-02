<?php

namespace Database\Factories;

use App\Enums\FulfillmentType;
use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_number' => 'KB-'.now()->format('Ymd').'-'.str_pad((string) fake()->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'tracking_token' => Str::random(40),
            'customer_name' => fake()->firstName(),
            'customer_phone' => '628'.fake()->numerify('#########'),
            'fulfillment_type' => FulfillmentType::Pickup,
            'address' => null,
            'delivery_area_id' => null,
            'notes' => null,
            'subtotal' => 30000,
            'delivery_fee' => 0,
            'total' => 30000,
            'status' => OrderStatus::PendingPayment,
        ];
    }
}
