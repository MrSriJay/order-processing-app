<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use App\Jobs\SendOrderNotification;
use App\Models\Order;

class ProcessRefund implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $order;
    protected $amount;

    public function __construct(Order $order, $amount = null)
    {
        $this->order = $order;
        $this->amount = $amount ?? $order->total;
    }
    
    /**
     * Execute the job.
     */
    public function handle()
    {
        if ($this->order->status === 'refunded') {
            \Log::info('Order already refunded', ['order_id' => $this->order->id]);
            return;
        }

        $redis = Redis::connection();
        $lockKey = "refund_lock:order:{$this->order->id}";
        $lock = $redis->set($lockKey, 1, 'EX', 60, 'NX');

        if (!$lock) {
            \Log::warning('Refund lock already acquired', ['order_id' => $this->order->id]);
            return;
        }

        try {
            // Validate order and relationships
            if (!$this->order->product || !$this->order->product->stock) {
                throw new \Exception('Product or stock not found for order');
            }
            if ($this->amount > $this->order->total) {
                throw new \Exception('Refund amount exceeds order total');
            }
            if ($this->order->product->price <= 0) {
                throw new \Exception('Invalid product price');
            }

            // Restore stock
            $refundQuantity = $this->amount / $this->order->product->price;
            \Log::info('Refund: Calculating quantity', [
                'order_id' => $this->order->id,
                'amount' => $this->amount,
                'price' => $this->order->product->price,
                'quantity' => $refundQuantity
            ]);

            $stock = $this->order->product->stock;
            $stock->quantity += $refundQuantity;
            $stock->save();
            $this->order->status = 'refunded';
            $this->order->save();

            // Update KPIs and leaderboard
            $date = $this->order->created_at->format('Y-m-d');
            $redis->hIncrByFloat("kpis:$date", 'revenue', -$this->amount);
            $redis->hIncrBy("kpis:$date", 'order_count', -1);
            $orderCount = max(1, $redis->hGet("kpis:$date", 'order_count'));
            $revenue = $redis->hGet("kpis:$date", 'revenue') ?: 0;
            $avg = $revenue / $orderCount;
            $redis->hSet("kpis:$date", 'average_order_value', $avg);
            $redis->zIncrBy('leaderboard', -$this->amount, $this->order->customer_id);

            // Log leaderboard state before and after
            $before = $redis->zScore('leaderboard', $this->order->customer_id);
            $redis->zIncrBy('leaderboard', -$this->amount, $this->order->customer_id);
            $after = $redis->zScore('leaderboard', $this->order->customer_id);
            
            \Log::info('Refund: Leaderboard updated', [
                'order_id' => $this->order->id,
                'key' => 'leaderboard',
                'customer_id' => $this->order->customer_id,
                'amount' => -$this->amount,
                'before' => $before ?: 0,
                'after' => $after ?: 0
            ]);

            SendOrderNotification::dispatch($this->order, 'refunded');
        } catch (\Exception $e) {
            \Log::error('Refund failed for Order ID: ' . $this->order->id, [
                'error' => $e->getMessage(),
                'amount' => $this->amount,
                'product_id' => $this->order->product_id,
                'customer_id' => $this->order->customer_id
            ]);
            $this->fail($e);
        } finally {
            $redis->del($lockKey);
        }
    }

    public function tags()
    {
        return ['refund:order:' . $this->order->id];
    }
}