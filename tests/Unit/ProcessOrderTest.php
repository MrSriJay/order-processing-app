<?php

namespace Tests\Unit;

use Tests\TestCase;  // ✅ use Laravel's TestCase, not PHPUnit
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Log;
use App\Jobs\ProcessOrder;
use App\Jobs\SendOrderNotification;
use App\Models\Order;
use App\Models\Product;
use App\Models\Stock;
use App\Models\Customer;

class ProcessOrderTest extends TestCase
{
    use RefreshDatabase;     // ✅ Reset DB between tests

    protected function setUp(): void
    {
        parent::setUp();

        // Fake Redis
        Redis::shouldReceive()->connection()->andReturnSelf();
        Redis::shouldReceive('hIncrBy');
        Redis::shouldReceive('hGet')->andReturn(100, 5);
        Redis::shouldReceive('hSet');
        Redis::shouldReceive('zIncrBy');
    }

    /** @test */
    public function it_processes_an_order_successfully()
    {
        $customer = Customer::factory()->create();
        $product  = Product::factory()->create();
        $stock    = Stock::factory()->create(['product_id' => $product->id, 'quantity' => 10]);

        $order = Order::factory()->create([
            'product_id'  => $product->id,
            'customer_id' => $customer->id,
            'quantity'    => 2,
            'total'       => 200,
            'status'      => 'pending',
        ]);

        Queue::fake();

        $job = new ProcessOrder($order);
        $job->handle();

        $order->refresh();
        $stock->refresh();

        $this->assertEquals('completed', $order->status);
        $this->assertEquals(8, $stock->quantity);

        Queue::assertPushed(SendOrderNotification::class, fn($job) =>
            $job->getOrder()->id === $order->id && $job->getStatus() === 'completed'
        );
    }

    /** @test */
    public function it_fails_when_stock_is_insufficient()
    {
        $customer = Customer::factory()->create();
        $product  = Product::factory()->create();
        $stock    = Stock::factory()->create(['product_id' => $product->id, 'quantity' => 1]);

        $order = Order::factory()->create([
            'product_id'  => $product->id,
            'customer_id' => $customer->id,
            'quantity'    => 5,
            'total'       => 500,
            'status'      => 'pending',
        ]);

        Queue::fake();

        $job = new ProcessOrder($order);
        $job->handle();

        $order->refresh();
        $stock->refresh();

        $this->assertEquals('failed', $order->status);
        $this->assertEquals(1, $stock->quantity);

        Queue::assertPushed(SendOrderNotification::class, fn($job) =>
            $job->getOrder()->id === $order->id && $job->getStatus() === 'completed'
        );
    }
}