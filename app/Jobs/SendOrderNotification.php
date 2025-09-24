<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Models\Notification;

class SendOrderNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $order;
    protected $status;

    /**
     * Create a new job instance.
     */
    public function __construct(\App\Models\Order $order, $status)
    {
        $this->order = $order;
        $this->status = $status;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        $message = "Order ID: {$this->order->id}, Customer ID: {$this->order->customer_id}, Status: {$this->status}, Total: {$this->order->total}";
        Log::channel('orders')->info($message);
        Notification::create([
            'order_id' => $this->order->id,
            'type' => 'log',  
            'status' => $this->status,
            'message' => $message,
        ]);
    }
}
