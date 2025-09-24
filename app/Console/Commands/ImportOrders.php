<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use League\Csv\Reader;
use App\Jobs\ProcessOrder; 
use App\Models\Customer;
use App\Models\Product;
use App\Models\Order;



class ImportOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:import {file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import orders from a CSV file';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $file = $this->argument('file');
        if (!file_exists($file)) {
            $this->error('File not found in location');
            return;
        }
        $csv = Reader::createFromPath($file, 'r');
        $csv->setHeaderOffset(0);
        $records = $csv->getRecords();
        DB::transaction(function () use ($records) {
            foreach ($records as $record) {
                // Lookup customer and product
                $customer = Customer::where('email', $record['customer_email'])->first();
                $product = Product::where('name', $record['product_name'])->first();

                if (!$customer || !$product) {
                    $this->warn('Skipping invalid record: ' . json_encode($record));
                    continue;
                }

                // Create order
                $order = Order::create([
                    'customer_id' => $customer->id,
                    'product_id' => $product->id,
                    'quantity' => $record['quantity'],
                    'total' => $product->price * $record['quantity'],
                    'status' => 'pending',
                ]);

                // Queue processing
                ProcessOrder::dispatch($order);
            }
        });

        $this->info('Orders imported and queued for processing.');


    }
}
