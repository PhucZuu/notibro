<?php

namespace App\Http\Controllers\api\Task;

use App\Http\Controllers\Controller;
use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TaskController extends Controller
{
    public function index()
    {
        //Check is user logged in
        if (Auth::check()) {
            $user_id = Auth::user()->id;
        }

        $tasks = Task::where('user_id', $user_id)
            ->orWhereRaw("
                EXISTS (
                    SELECT 1 FROM JSON_TABLE(
                        user_ids, '$[*]' COLUMNS (
                            user_id INT PATH '$.user_id',
                            status INT PATH '$.status'
                        )
                    ) AS jt
                    WHERE jt.user_id = ? AND jt.status = 1
                )
            ", [$user_id])
            ->get();
        // Lấy các ký tự đầu tiên có nhiều hơn một bản ghi
        // $firstChars = Task::select(DB::raw('LEFT(path, 1) as first_char'))
        //     ->groupBy('first_char')
        //     ->havingRaw('COUNT(*) > 1')
        //     ->pluck('first_char');

        // // Lấy các task có ký tự đầu tiên chung và sắp xếp theo start_time
        // $tasks = Task::whereIn(DB::raw('LEFT(path, 1)'), $firstChars)
        //     ->orderBy('start_time', 'asc')
        //     ->get();

        // $tasksWithEvents = []; 

        // $maxDate = Carbon::now()->addYear(); // Ngày tối đa là ngày hiện tại + 1 năm

        // foreach ($tasks as $task) {
        //     // echo($task);
        //     $startTime = Carbon::parse($task->start_time);
        //     $endTime = Carbon::parse($task->end_time);
        //     $endDate = $task->end_date ? Carbon::parse($task->end_date) : null;

        //     // echo'<pre>';
        //     // echo $startTime, $endTime, $endDate;

        //     // Kiểm tra điều kiện đầu tiên
        //     if ($endDate && $endDate->isSameDay($endTime)) {
        //         // Hiển thị 1 lần
        //         $tasksWithEvents[] = $task;
        //     } else {
        //         // Nếu không có end_date hoặc end_date cách start_time > 1 ngày
        //         if (!$endDate || $endDate->diffInDays($startTime) > 1) {
        //             $currentDate = $startTime->copy();
        //             // echo $currentDate;

        //             while ($currentDate->lessThanOrEqualTo($maxDate)) {
        //                 // Thêm sự kiện vào danh sách
        //                 $tasksWithEvents[] = $task->replicate(); // Tạo bản sao của task
        //                 $tasksWithEvents[count($tasksWithEvents) - 1]->start_time = $currentDate->toDateTimeString();
        //                 $tasksWithEvents[count($tasksWithEvents) - 1]->end_time = $currentDate->copy()->addMinutes($endTime->diffInMinutes($startTime))->toDateTimeString();

        //                 // Tính toán khoảng cách lặp lại
        //                 if ($task->date_space === 'day') {
        //                     $currentDate->addDays($task->repeat_space);
        //                 } elseif ($task->date_space === 'week') {
        //                     $currentDate->addWeeks($task->repeat_space);
        //                 } elseif ($task->date_space === 'month') {
        //                     $currentDate->addMonths($task->repeat_space);
        //                 } elseif ($task->date_space === 'year') {
        //                     $currentDate->addYears($task->repeat_space);
        //                 }
        //             }
        //         }
        //     }
        // }

        // Trả về view và truyền dữ liệu vào

        return response()->json([
            'code'      =>  200,
            'message'   =>  'Fetching Data successfully',
            'data'      =>  $tasks
        ], 200);
    }

    public function updateTask(Request $request, $id)
    {
        $code = $request->code;

        switch ($code) {
            case 'Edit_N':
                return response()->json([
                    'code'      =>  200,
                    'message'   =>  'Success',
                    'data'      =>  $code
                ]);

            case 'Edit_1':
                return response()->json([
                    'code'      =>  200,
                    'message'   =>  'Success',
                    'data'      =>  $code
                ]);

            case 'Edit_1B':
                return response()->json([
                    'code'      =>  200,
                    'message'   =>  'Success',
                    'data'      =>  $code
                ]);

            case 'Edit_A':
                return response()->json([
                    'code'      =>  200,
                    'message'   =>  'Success',
                    'data'      =>  $code
                ]);

            default:    
                return response()->json([
                    'code'      =>  400,
                    'message'   =>  'Invalid code',
                    'data'      =>  ''
                ]);
            
        }
    }
}
