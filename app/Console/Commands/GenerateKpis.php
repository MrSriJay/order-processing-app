<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class GenerateKpis extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:kpis {date?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate Daily KPIs and update the leaderboard';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $date = $this->argument('date') ?? now()->format('Y-m-d');
        $redis = Redis::connection();
        $kpis = $redis->hGetAll("kpis:$date");
        
        $this->info("KPIs for $date:");
        $this->line("Revenue: " . ($kpis['revenue'] ?? 0));
        $this->line("Order Count: " . ($kpis['order_count'] ?? 0));
        $this->line("Avg Order Value: " . ($kpis['average_order_value'] ?? 0));

        $leaderboard = $redis->zRevRange('leaderboard', 0, -1, true);  
        $this->info('Leaderboard (Customer ID: Total Spent):');
        foreach ($leaderboard as $customerId => $score) {
            $this->line("$customerId: $score");
        }
    }
}
