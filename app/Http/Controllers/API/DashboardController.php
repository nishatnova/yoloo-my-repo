<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\PackageInquiry;
use App\Traits\ResponseTrait;
use App\Models\Order;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    use ResponseTrait;
    public function getDashboardStats()
    {
        try {

            $events = PackageInquiry::whereHas('order', function ($query) {
                $query->where('status', 'Completed');
            })
            ->get();

            $completedEvents = PackageInquiry::where('status', 'Completed')
                ->whereHas('order', function ($query) {
                    $query->where('status', 'Completed');
                })
                ->get();

            $cancelEvents = PackageInquiry::where('status', 'Cancel')
                ->whereHas('order', function ($query) {
                    $query->where('status', 'Completed');
                })
                ->get();

            $totalEarnings = Order::where('status', 'Completed')->sum('amount');
            $totalEarnings = floatval($totalEarnings);  


            $todayEarnings = Order::where('status', 'Completed')
                ->whereDate('created_at', today())
                ->sum('amount');
            $todayEarnings = floatval($todayEarnings);  

            $weeklyEarnings = Order::where('status', 'Completed')
                ->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])
                ->sum('amount');
            $weeklyEarnings = floatval($weeklyEarnings); 

            $monthlyEarnings = Order::where('status', 'Completed')
                ->whereMonth('created_at', now()->month)
                ->sum('amount');
            $monthlyEarnings = floatval($monthlyEarnings);  

            $yearlyBreakdown = [];
            for ($i = 1; $i <= 12; $i++) {
                $yearlyBreakdown[$i] = Order::where('status', 'Completed')
                    ->whereYear('created_at', now()->year)
                    ->whereMonth('created_at', $i)
                    ->sum('amount');
                $yearlyBreakdown[$i] = floatval($yearlyBreakdown[$i]);  
            }

            
            $dailyBreakdown = [];
            $daysInMonth = now()->daysInMonth;
            for ($i = 1; $i <= $daysInMonth; $i++) {
                $dailyBreakdown[$i] = Order::where('status', 'Completed')
                    ->whereDate('created_at', now()->year . '-' . now()->month . '-' . $i)
                    ->sum('amount');
                $dailyBreakdown[$i] = floatval($dailyBreakdown[$i]);  
            }

            
            $monthlyGrowth = [];
            for ($i = 1; $i <= now()->month; $i++) {
                $currentMonthEarnings = Order::where('status', 'Completed')
                    ->whereYear('created_at', now()->year)
                    ->whereMonth('created_at', $i)
                    ->sum('amount');
                $currentMonthEarnings = floatval($currentMonthEarnings); 

                $previousMonthEarnings = Order::where('status', 'Completed')
                    ->whereYear('created_at', now()->year)
                    ->whereMonth('created_at', $i - 1)
                    ->sum('amount');
                $previousMonthEarnings = floatval($previousMonthEarnings);

                
                $growth = $previousMonthEarnings ? (($currentMonthEarnings - $previousMonthEarnings) / $previousMonthEarnings) * 100 : 0;
                $monthlyGrowth[$i] = floatval($growth);  
            }

            
            $data = [
                'total_users' => User::where('role', 'user')->count(),
                'total_booked' => $events->count(),
                'total_completed_event' => $completedEvents->count(),
                'total_cancel_event' => $cancelEvents->count(),
                'total_earning' => $totalEarnings,
                'today_earning' => $todayEarnings,
                'weekly_earning' => $weeklyEarnings,
                'monthly_earning' => $monthlyEarnings,
                'yearly_breakdown' => $yearlyBreakdown,
                'daily_breakdown' => $dailyBreakdown,
                'monthly_growth' => $monthlyGrowth,
            ];

            return $this->sendResponse($data, 'Dashboard stats retrieved successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving dashboard stats.', [], 500);
        }
    }



}
