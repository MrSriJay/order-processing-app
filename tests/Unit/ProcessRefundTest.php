<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Order;
use App\Models\Product;
use App\Models\Stock;
use App\Jobs\ProcessRefund;
use App\Jobs\SendOrderNotification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProcessRefundTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Redis::flushall(); // Clear Redis before each test
    }

    /** @test */
    public function it_processes_a_refund_and_updates_stock_kpis_and_leaderboard()
    {
        // Create product and stock
        $product = Product::factory()->create(['price' => 10]);
        $stock = Stock::factory()->create(['product_id' => $product->id, 'quantity' => 5]);

        // Create order
        $order = Order::factory()->create([
            'product_id' => $product->id,
            'customer_id' => 1,
            'quantity' => 2,
            'total' => 20,
            'status' => 'completed',
        ]);

        // Process refund
        $job = new ProcessRefund($order);
        $job->handle();

        // Refresh models
        $order->refresh();
        $stock->refresh();

        // Assertions
        $this->assertEquals('refunded', $order->status);
        $this->assertEquals(7, $stock->quantity); // 5 + 2 refunded

        // Check Redis KPIs
        $date = $order->created_at->format('Y-m-d');
        $this->assertEquals(-20, Redis::hGet("kpis:$date", 'revenue'));
        $this->assertEquals(0, Redis::hGet("kpis:$date", 'order_count'));

        // Check leaderboard
        $this->assertEquals(-20, Redis::zScore('leaderboard', $order->customer_id));

        // Check notification job dispatched
        Queue::assertPushed(SendOrderNotification::class, function ($job) use ($order) {
            return $job->getOrder()->id === $order->id && $job->getStatus() === 'refunded';
        });
    }

    /** @test */
    public function it_does_not_refund_an_already_refunded_order()
    {
        $order = Order::factory()->create(['status' => 'refunded']);

        $job = new ProcessRefund($order);
        $job->handle();

        // Ensure no job dispatched
        Queue::assertNothingPushed();
    }
}