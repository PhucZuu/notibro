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
            $query = User::query();
    
            if ($request->filled(['start_date', 'end_date'])) {
                $query->whereBetween('created_at', [
                    Carbon::parse($request->start_date)->startOfDay(),
                    Carbon::parse($request->end_date)->endOfDay()
                ]);
            }
    
            $count = $query->count();
    
            return response()->json([
                'code' => 200,
                'message' => 'Total users retrieved successfully',
                'data' => [
                    'total_users' => $count
                ]
            ]);
        } catch (\Exception $e) {
            Log::error($e);
            return response()->json([
                'code' => 500,
                'message' => 'An error occurred while retrieving user count'
            ], 500);
        }
    }
    

    // 2. Tổng số task toàn hệ thống 
    public function totalTasks(Request $request)
    {
        try {
            $query = Task::whereHas('user', function ($q) {
                $q->whereNull('deleted_at'); // Chỉ tính task của user chưa bị xóa mềm
            });
    
            if ($request->filled(['start_date', 'end_date'])) {
                $query->whereBetween('start_time', [
                    Carbon::parse($request->start_date)->startOfDay(),
                    Carbon::parse($request->end_date)->endOfDay()
                ]);
            }
    
            $count = $query->count();
    
            return response()->json([
                'code' => 200,
                'message' => 'Total tasks retrieved successfully',
                'data' => [
                    'total_tasks' => $count
                ]
            ]);
        } catch (\Exception $e) {
            Log::error($e);
            return response()->json([
                'code' => 500,
                'message' => 'An error occurred while retrieving task count'
            ], 500);
        }
    }    

    // 3. Top người tạo task 
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