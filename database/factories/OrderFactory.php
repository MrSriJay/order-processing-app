<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Order;
use App\Models\Product;
use App\Models\Customer;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'product_id'  => Product::factory(),
            'quantity'    => $this->faker->numberBetween(1, 5),
            'total'       => $this->faker->randomFloat(2, 50, 500),
            'status'      => 'pending',
        ];
    }
}