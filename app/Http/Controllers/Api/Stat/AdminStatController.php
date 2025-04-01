<?php

namespace App\Http\Controllers\Api\Stat;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Carbon\Carbon;

class AdminStatController extends Controller
{
    // 1. Tổng số người dùng đã đăng ký
    public function totalUsers(Request $request)
    {
        try {
            $start = $request->filled('start_date') ? Carbon::parse($request->start_date)->startOfDay() : Carbon::now()->startOfWeek();
            $end = $request->filled('end_date') ? Carbon::parse($request->end_date)->endOfDay() : Carbon::now()->endOfWeek();

            // Lấy số lượng user đăng ký theo từng ngày
            $dailyStats = User::whereBetween('created_at', [$start, $end])
                ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
                ->groupBy('date')
                ->orderBy('date')
                ->get();

            return response()->json([
                'code' => 200,
                'message' => 'Statistics retrieved successfully',
                'data' => [
                    'stats' => $dailyStats
                ]
            ]);
        } catch (\Exception $e) {
            Log::error($e);
            return response()->json([
                'code' => 500,
                'message' => 'An error occurred'
            ], 500);
        }
    }
    
    
    // 2. Tổng số task toàn hệ thống 
    public function totalTasks(Request $request)
    {
        try {
            $start = $request->filled('start_date') ? Carbon::parse($request->start_date)->startOfDay() : Carbon::now()->startOfWeek();
            $end = $request->filled('end_date') ? Carbon::parse($request->end_date)->endOfDay() : Carbon::now()->endOfWeek();
    
            // Lấy số lượng task theo từng ngày, chỉ tính task của user chưa bị xóa (deleted_at is null)
            $dailyStats = Task::whereHas('user', function ($q) {
                    $q->whereNull('deleted_at');
                })
                ->whereBetween('start_time', [$start, $end])
                ->selectRaw('DATE(start_time) as date, COUNT(*) as count')
                ->groupBy('date')
                ->orderBy('date')
                ->get();
    
            return response()->json([
                'code' => 200,
                'message' => 'Statistics retrieved successfully',
                'data' => [
                    'stats' => $dailyStats
                ]
            ]);
        } catch (\Exception $e) {
            Log::error($e);
            return response()->json([
                'code' => 500,
                'message' => 'An error occurred while retrieving task statistics'
            ], 500);
        }
    }    

    // 3. Top 10 người tạo task nhiều nhất 
    public function topTaskCreators(Request $request)
    {
        try {
            $query = Task::query();

            if ($request->filled(['start_date', 'end_date'])) {
                $query->whereBetween('start_time', [
                    Carbon::parse($request->start_date)->startOfDay(),
                    Carbon::parse($request->end_date)->endOfDay()
                ]);
            }

            $top = $query->selectRaw('user_id, COUNT(*) as task_count')
                ->groupBy('user_id')
                ->orderByDesc('task_count')
                ->with(['user' => function ($query) {
                    $query->whereNull('deleted_at'); 
                }])
                ->take(10)
                ->get()
                ->filter(function ($item) {
                    return $item->user !== null; 
                })
                ->values();

            $result = $top->map(function ($item) {
                return [
                    'user_id'    => $item->user->id,
                    'name'       => trim(($item->user->first_name ?? '') . ' ' . ($item->user->last_name ?? '')),
                    'email'      => $item->user->email,
                    'task_count' => $item->task_count,
                ];
            });

            return response()->json([
                'code' => 200,
                'message' => 'Top task creators retrieved successfully',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            Log::error($e);
            return response()->json([
                'code' => 500,
                'message' => 'An error occurred while retrieving task statistics'
            ], 500);
        }
    }
    
}