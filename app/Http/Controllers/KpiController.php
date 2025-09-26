<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use App\Models\Order;
use App\Models\Customer;

class KpiController extends Controller
{
    public function index()
    {
        // Fetch KPIs from Redis or database
        $date = now()->format('Y-m-d');
        $redis = Redis::connection();
        try {
            $totalRevenue = $redis->hGet("kpis:$date", 'revenue') ?: 0;
            $orderCount = $redis->hGet("kpis:$date", 'order_count') ?: 0;
            $averageOrderValue = $redis->hGet("kpis:$date", 'average_order_value') ?: 0;
        } catch (\Exception $e) {
            \Log::error('Failed to fetch KPIs from Redis', ['error' => $e->getMessage()]);
            $orders = Order::where('status', 'completed')->get();
            $totalRevenue = $orders->sum('total');
            $orderCount = $orders->count();
            $averageOrderValue = $orderCount ? $totalRevenue / $orderCount : 0;
        }

        // Fetch leaderboard from Redis
        try {
            $leaderboard =  $redis->zRevRange('leaderboard', 0, -1, true);  ;
            $leaderboardFormatted = [];
            foreach ($leaderboard as $customerId => $total) {
                $customer = Customer::find($customerId);
                $leaderboardFormatted[] = [
                    'name' => $customer ? $customer->name : 'Unknown',
                    'email' => $customer ? $customer->email : 'N/A',
                    'total' => number_format($total, 2)
                ];
            }
        } catch (\Exception $e) {
            \Log::error('Failed to fetch leaderboard from Redis', ['error' => $e->getMessage()]);
            $leaderboardFormatted = Order::where('status', 'completed')
                ->groupBy('customer_id')
                ->selectRaw('customer_id, SUM(total) as total_spent')
                ->orderByDesc('total_spent')
                ->take(5)
                ->get()
                ->map(function ($item) {
                    $customer = Customer::find($item->customer_id);
                    return [
                        'name' => $customer ? $customer->name : 'Unknown',
                        'email' => $customer ? $customer->email : 'N/A',
                        'total' => number_format($item->total_spent, 2)
                    ];
                })->toArray();
        }

        return response()->json([
            'kpis' => [
                'total_revenue' => number_format($totalRevenue, 2),
                'order_count' => $orderCount,
                'average_order_value' => number_format($averageOrderValue, 2)
            ],
            'leaderboard' => $leaderboardFormatted
        ]);
    }
}