<?php

namespace App\Http\Controllers\Api\Stat;

use App\Http\Controllers\Controller;
use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class StatController extends Controller
{
    // 1.Tính tỷ lệ task hoàn thành đúng hạn
    public function completionRate()
    {
        try {
            $userId = Auth::id();
    
            // Lấy tất cả task của user với type là 'task'
            $query = Task::where('user_id', $userId)
                ->where('type', 'task');
    
            $total = $query->count();
    
            // Task đã hoàn thành
            $done = (clone $query)
                ->where('is_done', true)
                ->count();
    
            $rate = $total > 0 ? round(($done / $total) * 100, 2) : 0;
    
            return response()->json([
                'code' => 200,
                'message' => 'Completion rate retrieved successfully',
                'data' => [
                    'completion_rate' => $rate,
                    'unit' => '%'
                ]
            ], 200);
    
        } catch (\Exception $e) {
            Log::error($e);
            return response()->json([
                'code' => 500,
                'message' => 'An error occurred while calculating completion rate',
            ], 500);
        }
    }
    

    // 2.Ngày bận rộn nhất (nhiều task nhất)
    public function busiestDay(Request $request)
    {
        try {
            $userId = Auth::id();
    
            $startDate = $request->filled('start_date')
                ? Carbon::parse($request->start_date)->startOfDay()
                : Carbon::now()->startOfWeek();
    
            $endDate = $request->filled('end_date')
                ? Carbon::parse($request->end_date)->endOfDay()
                : Carbon::now()->endOfWeek();
    
            // Lấy tất cả task có liên quan đến user (chủ sở hữu hoặc có trong attendees)
            $tasks = Task::where(function ($q) use ($userId) {
                    $q->where('user_id', $userId)
                      ->orWhereJsonContains('attendees', [['user_id' => $userId]]);
                })
                ->where('start_time', '<=', $endDate)
                ->where('end_time', '>=', $startDate)
                ->get();
    
            // Lọc lại: chỉ lấy task mà user là chủ, hoặc là attendee có status = "yes"
            $filteredTasks = $tasks->filter(function ($task) use ($userId) {
                if ($task->user_id == $userId) {
                    return true;
                }
    
                $attendees = collect($task->attendees ?? []);
                return $attendees->contains(function ($a) use ($userId) {
                    return (int) $a['user_id'] === $userId && $a['status'] === 'yes';
                });
            });
    
            // Đếm số task mỗi ngày (dải theo từng ngày task diễn ra)
            $taskDays = collect();
    
            foreach ($filteredTasks as $task) {
                $start = Carbon::parse($task->start_time)->startOfDay();
                $end = Carbon::parse($task->end_time)->endOfDay();
    
                for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                    $key = $date->toDateString();
                    $taskDays[$key] = ($taskDays[$key] ?? 0) + 1;
                }
            }
    
            $grouped = collect($taskDays)->map(function ($count, $day) {
                return [
                    'day' => $day,
                    'task_count' => $count
                ];
            })->sortBy('day')->values();
    
            return response()->json([
                'code' => 200,
                'message' => 'Task count per day (owned + accepted shared)',
                'data' => $grouped
            ]);
        } catch (\Exception $e) {
            Log::error($e);
            return response()->json([
                'code' => 500,
                'message' => 'An error occurred while retrieving task counts by day',
            ], 500);
        }
    }
    
    
}