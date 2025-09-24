<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Redis;


class ProcessRefund implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    protected $order;
    protected $amount;

    /**
     * Create a new job instance.
     */
    public function __construct(\App\Models\Order $order, $amount = null)
    {
        $this->order = $order;
        $this->amount = $amount ?? $order->total;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        // Idempotency: Check if already refunded using refund_id
        if ($this->order->status === 'refunded') {
            return;  // Skip if already done
        }

        $refundId = $this->order->generateRefundId();  // Unique ID

        // Use Redis lock for idempotency (prevent double-run)
        $lock = Redis::connection()->set("refund_lock:{$refundId}", true, ['NX', 'EX' => 60]);  // 60s lock
        if (!$lock) {
            return;  // Already processing
        }

        try {
            // Refund logic: Restore stock
            $stock = $this->order->product->stock;
            $refundQuantity = ($this->amount / $this->order->product->price);  // Calculate quantity for partial
            $stock->quantity += $refundQuantity;
            $stock->save();

            // Update order
            $this->order->status = 'refunded';
            $this->order->save();

            // Update KPIs and Leaderboard in real-time (reverse)
            $date = $this->order->created_at->format('Y-m-d');  // Use order date
            $redis = Redis::connection();
            $redis->hIncrBy("kpis:$date", 'revenue', -$this->amount);
            $redis->hIncrBy("kpis:$date", 'order_count', -1);
            $avg = $redis->hGet("kpis:$date", 'revenue') / max(1, $redis->hGet("kpis:$date", 'order_count'));  // Avoid div by zero
            $redis->hSet("kpis:$date", 'average_order_value', $avg);

            $redis->zIncrBy('leaderboard', -$this->amount, $this->order->customer_id);

            // Queue notification if needed
            SendOrderNotification::dispatch($this->order, 'refunded');

        } finally {
            $redis->del("refund_lock:{$refundId}");
        }
    }
}
