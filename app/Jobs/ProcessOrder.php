<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Jobs\SendOrderNotification;
use Illuminate\Support\Facades\Redis;

use App\Models\Order;

class ProcessOrder implements ShouldQueue
{
    use Queueable, Dispatchable, InteractsWithQueue, SerializesModels;

    protected $order;

    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        try {
                $stock = $this->order->product->stock;
                if ($stock->quantity < $this->order->quantity) {
                    throw new \Exception('Insufficient stock');
                }
                $stock->quantity -= $this->order->quantity;
                $stock->save();

                $paymentSuccess = true; 
                if (!$paymentSuccess) {
                    throw new \Exception('Payment failed');
                }

                $this->order->status = 'completed';
                $this->order->save();

                $this->updateKpisAndLeaderboard();
                SendOrderNotification::dispatch($this->order, 'completed');
            } catch (\Exception $e) {
                \Log::error('ProcessOrder failed for Order ID: ' . $this->order->id, [
                    'error' => $e->getMessage(),
                    'stock_quantity' => isset($stock) ? $stock->quantity : null,
                    'order_quantity' => $this->order->quantity,
                ]);
                if (isset($stock)) {
                    $stock->quantity += $this->order->quantity;
                    $stock->save();
                }
                $this->order->status = 'failed';
                $this->order->save();
                SendOrderNotification::dispatch($this->order, 'failed');
            }
    }

    protected function updateKpisAndLeaderboard()
    {
        $date = now()->format('Y-m-d');
        $redis = Redis::connection();
        $redis->hIncrBy("kpis:$date", 'revenue', $this->order->total);
        $redis->hIncrBy("kpis:$date", 'order_count', 1);
        $avg = $redis->hGet("kpis:$date", 'revenue') / $redis->hGet("kpis:$date", 'order_count');
        $redis->hSet("kpis:$date", 'average_order_value', $avg);
        $redis->zIncrBy('leaderboard', $this->order->total, $this->order->customer_id);
    }
    
}
