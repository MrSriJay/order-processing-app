<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\ProcessRefund;

class RefundOrder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:refund {order_id} {amount?}';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refund an order';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $order = \App\Models\Order::find($this->argument('order_id'));
        if (!$order) {
            $this->error('Order not found');
            return;
        }
        $amount = $this->argument('amount') ?? null;

        ProcessRefund::dispatch($order, $amount);
        $this->info('Refund queued.');
    }
}
