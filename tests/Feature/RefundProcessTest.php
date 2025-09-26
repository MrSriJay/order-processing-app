<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use App\Models\Order;
use App\Models\Product;
use App\Models\Stock;
use App\Models\Customer;
use App\Jobs\ProcessRefund;

class RefundProcessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Redis::flushall();
    }

    /** @test */
    public function it_processes_refund_and_updates_database_and_redis()
    {
        $customer = Customer::create([
            'name' => 'Test Customer',
            'email' => 'test@example.com',
        ]);

        $product = Product::create([
            'name' => 'Test Product',
            'price' => 100,
        ]);

        $stock = Stock::create([
            'product_id' => $product->id,
            'quantity' => 10,
        ]);

        $order = Order::create([
            'customer_id' => $customer->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'total' => 200,
            'status' => 'completed',
        ]);

        ProcessRefund::dispatchSync($order);

        $order->refresh();
        $stock->refresh();

        $this->assertEquals('refunded', $order->status);
        $this->assertEquals(12, $stock->quantity);
    }

    /** @test */
    public function it_skips_already_refunded_order()
    {
        $customer = Customer::create([
            'name' => 'Test Customer',
            'email' => 'test2@example.com',
        ]);

        $product = Product::create([
            'name' => 'Product',
            'price' => 50,
        ]);

        $stock = Stock::create([
            'product_id' => $product->id,
            'quantity' => 5,
        ]);

        $order = Order::create([
            'customer_id' => $customer->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'total' => 50,
            'status' => 'refunded',
        ]);

        ProcessRefund::dispatchSync($order);

        $order->refresh();
        $stock->refresh();

        $this->assertEquals('refunded', $order->status);
        $this->assertEquals(5, $stock->quantity);
    }

    /** @test */
    public function it_fails_if_product_or_stock_missing()
    {
        $this->expectException(\Exception::class);

        $customer = Customer::create([
            'name' => 'Test Customer',
            'email' => 'test3@example.com',
        ]);

        $order = Order::create([
            'customer_id' => $customer->id,
            'product_id' => 999, // non-existent product
            'quantity' => 2,
            'total' => 200,
            'status' => 'completed',
        ]);

        ProcessRefund::dispatchSync($order);
    }

    /** @test */
    public function it_returns_correct_kpi_and_leaderboard_data()
    {
        $customer = Customer::create([
            'name' => 'Test Customer',
            'email' => 'test4@example.com',
        ]);

        $product = Product::create([
            'name' => 'Product',
            'price' => 100,
        ]);

        $stock = Stock::create([
            'product_id' => $product->id,
            'quantity' => 10,
        ]);

        $order = Order::create([
            'customer_id' => $customer->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'total' => 200,
            'status' => 'completed',
        ]);

        ProcessRefund::dispatchSync($order);

        $date = $order->created_at->format('Y-m-d');
        $revenue = Redis::hGet("kpis:$date", 'revenue');
        $orderCount = Redis::hGet("kpis:$date", 'order_count');

        $this->assertEquals(-200, (float)$revenue);
        $this->assertEquals(0, (int)$orderCount);

        $leaderboardScore = Redis::zScore('leaderboard', $customer->id);
        $this->assertEquals(-200, (float)$leaderboardScore);
    }
}