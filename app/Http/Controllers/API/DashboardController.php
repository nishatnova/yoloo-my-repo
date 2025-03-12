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

    public function getDashboardGraphData()
    {
        try {
            $months = [
                1 => "Jan", 2 => "Feb", 3 => "Mar", 4 => "Apr",
                5 => "May", 6 => "Jun", 7 => "Jul", 8 => "Aug",
                9 => "Sep", 10 => "Oct", 11 => "Nov", 12 => "Dec"
            ];

            $yearlyBreakdown = [];
            $weeklyBreakdown = [];

            // ✅ Calculate Total Revenue for the Year
            $totalYearlyAmount = Order::where('status', 'Completed')
                ->whereYear('created_at', now()->year)
                ->sum('amount');

            // ✅ Monthly Data Calculation (Convert to Percentage)
            for ($i = 1; $i <= 12; $i++) {
                $monthlyTotal = Order::where('status', 'Completed')
                    ->whereYear('created_at', now()->year)
                    ->whereMonth('created_at', $i)
                    ->sum('amount');

                $percentage = ($totalYearlyAmount > 0) 
                    ? round(($monthlyTotal / $totalYearlyAmount) * 100, 2)
                    : 0;

                $yearlyBreakdown[] = [
                    'period' => $months[$i],  
                    'value' => $percentage // Convert to percentage
                ];
            }

            // ✅ Weekly Data Calculation (Convert to Percentage)
            $currentMonth = now()->month;
            $totalMonthlyAmount = Order::where('status', 'Completed')
                ->whereYear('created_at', now()->year)
                ->whereMonth('created_at', $currentMonth)
                ->sum('amount');

            $weeks = [
                "Week 1" => [1, 7],
                "Week 2" => [8, 14],
                "Week 3" => [15, 21],
                "Week 4" => [22, 31]
            ];

            foreach ($weeks as $weekName => $daysRange) {
                $weeklyTotal = Order::where('status', 'Completed')
                    ->whereYear('created_at', now()->year)
                    ->whereMonth('created_at', $currentMonth)
                    ->whereDay('created_at', '>=', $daysRange[0])
                    ->whereDay('created_at', '<=', $daysRange[1])
                    ->sum('amount');

                $percentage = ($totalMonthlyAmount > 0) 
                    ? round(($weeklyTotal / $totalMonthlyAmount) * 100, 2)
                    : 0;

                $weeklyBreakdown[] = [
                    'period' => $weekName,
                    'value' => $percentage // Convert to percentage
                ];
            }

            return $this->sendResponse([
                'monthly_data' => $yearlyBreakdown,
                'weekly_data' => $weeklyBreakdown
            ], 'Dashboard stats retrieved successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving dashboard stats.', [], 500);
        }
    }


    public function getEarningsData()
    {
        try {
            $dailyTarget = 5000; 
            $weeklyTarget = $dailyTarget * 7; 
            $monthlyTarget = $dailyTarget * now()->daysInMonth; 


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


            // 🎯 Calculate Percentages Against Targets
            $todayPercentage = ($dailyTarget > 0) ? round(($todayEarnings / $dailyTarget) * 100, 2) : 0;
            $weeklyPercentage = ($weeklyTarget > 0) ? round(($weeklyEarnings / $weeklyTarget) * 100, 2) : 0;
            $monthlyPercentage = ($monthlyTarget > 0) ? round(($monthlyEarnings / $monthlyTarget) * 100, 2) : 0;
        
            return $this->sendResponse([
                'today_earnings' => [
                    'amount' => $todayEarnings,
                    'percentage' => $todayPercentage
                ],
                'weekly_earnings' => [
                    'amount' => $weeklyEarnings,
                    'percentage' => $weeklyPercentage
                ],
                'monthly_earnings' => [
                    'amount' => $monthlyEarnings,
                    'percentage' => $monthlyPercentage
                ],
                
            ], 'Earnings data retrieved successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving earnings data: ' . $e->getMessage(), [], 500);
        }
    }







}
