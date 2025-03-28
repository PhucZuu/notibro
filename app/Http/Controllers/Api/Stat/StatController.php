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
    public function completionRate(Request $request)
    {
        try {
            $userId = Auth::id();

            $query = Task::where('user_id', $userId);

            if ($request->filled(['start_date', 'end_date'])) {
                $query->whereBetween('start_time', [
                    Carbon::parse($request->start_date)->startOfDay(),
                    Carbon::parse($request->end_date)->endOfDay()
                ]);
            }

            $total = $query->count();

            $onTime = (clone $query)
                ->where('is_done', true)
                ->whereColumn('end_time', '>=', 'start_time')
                ->count();

            $rate = $total > 0 ? round(($onTime / $total) * 100, 2) : 0;

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
    
            $startDate = $request->filled('start_date') ? Carbon::parse($request->start_date)->startOfDay() : Carbon::now()->startOfWeek();
            $endDate = $request->filled('end_date') ? Carbon::parse($request->end_date)->endOfDay() : Carbon::now()->endOfWeek();
    
            $days = Task::selectRaw('DATE(start_time) as day, COUNT(*) as task_count')
                ->where('user_id', $userId)
                ->whereBetween('start_time', [$startDate, $endDate])
                ->groupBy('day')
                ->orderBy('day')
                ->get();
    
            return response()->json([
                'code' => 200,
                'message' => 'Task count per day retrieved successfully',
                'data' => $days
            ], 200);
    
        } catch (\Exception $e) {
            Log::error($e);
            return response()->json([
                'code' => 500,
                'message' => 'An error occurred while retrieving task counts by day',
            ], 500);
        }
    }
    

    // 3.Chuỗi ngày làm việc liên tiếp (đã hoàn thành task)
    public function workStreak(Request $request)
    {
        try {
            $userId = Auth::id();
    
            $startDate = $request->filled('start_date') ? Carbon::parse($request->start_date)->startOfDay() : Carbon::now()->startOfWeek();
            $endDate = $request->filled('end_date') ? Carbon::parse($request->end_date)->endOfDay() : Carbon::now()->endOfWeek();
    
            $dates = Task::where('user_id', $userId)
                ->where('is_done', true)
                ->whereBetween('start_time', [$startDate, $endDate])
                ->orderBy('start_time')
                ->pluck('start_time')
                ->map(fn($dt) => Carbon::parse($dt)->toDateString())
                ->unique()
                ->values();
    
            // Tính streak
            $maxStreak = $dates->isNotEmpty() ? 1 : 0;
            $currentStreak = 1;
            $streakData = [];
    
            for ($i = 1; $i < $dates->count(); $i++) {
                $prev = Carbon::parse($dates[$i - 1]);
                $curr = Carbon::parse($dates[$i]);
    
                if ($curr->diffInDays($prev) == 1) {
                    $currentStreak++;
                } else {
                    $currentStreak = 1;
                }
    
                $maxStreak = max($maxStreak, $currentStreak);
                $streakData[] = [
                    'date' => $curr->toDateString(),
                    'streak' => $currentStreak
                ];
            }
    
            return response()->json([
                'code' => 200,
                'message' => 'Work streak calculated successfully',
                'data' => [
                    'max_streak' => $maxStreak,
                    'streak_by_date' => $streakData
                ]
            ], 200);
    
        } catch (\Exception $e) {
            Log::error($e);
            return response()->json([
                'code' => 500,
                'message' => 'An error occurred while calculating work streak',
            ], 500);
        }
    }
    

    // 4.Tổng số task đã tạo, có thể group theo tag nếu truyền group_by_tag
    public function totalTasks(Request $request)
    {
        try {
            $userId = Auth::id();
    
            $startDate = $request->filled('start_date') ? Carbon::parse($request->start_date)->startOfDay() : Carbon::now()->startOfWeek();
            $endDate = $request->filled('end_date') ? Carbon::parse($request->end_date)->endOfDay() : Carbon::now()->endOfWeek();
    
            $query = Task::where('user_id', $userId)
                ->whereBetween('start_time', [$startDate, $endDate]);
    
            // Mặc định là nhóm theo tag
            $tasks = $query->with('tag')
                ->get()
                ->groupBy(fn($task) => optional($task->tag)->name ?? 'No Tag')
                ->map(fn($group) => $group->count());
    
            return response()->json([
                'code' => 200,
                'message' => 'Task count grouped by tag retrieved successfully',
                'data' => $tasks
            ], 200);
    
        } catch (\Exception $e) {
            Log::error($e);
    
            return response()->json([
                'code' => 500,
                'message' => 'An error occurred while retrieving total task count',
            ], 500);
        }
    }
    

}