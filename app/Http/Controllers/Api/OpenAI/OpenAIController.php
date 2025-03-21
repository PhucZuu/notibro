<?php

namespace App\Http\Controllers\Api\OpenAI;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\Task;
use App\Services\OpenAIService;
use App\Services\TaskSupportService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class OpenAIController extends Controller
{
    protected $openAIService;

    protected $taskSupportService;

    public function __construct(OpenAIService $openAIService, TaskSupportService $taskSupportService)
    {
        $this->openAIService = $openAIService;
        $this->taskSupportService = $taskSupportService;
    }

    public function extractFields(Request $request)
    {
        $userRequest = $request->input('message');

        // Lấy cấu trúc bảng từ model Task
        $tableStructure = Task::getTableStructure();
        Log::info("Cấu trúc bảng tiền xử lý: " . json_encode($tableStructure));

        // Gửi đến AI để phân tích
        $fields = $this->openAIService->analyzeRequest($userRequest, $tableStructure);

        try {
            $created_result = $this->taskSupportService->store($fields);

            Log::info('Tạo thành công');
            Log::info($created_result);

            return response()->json([
                'code'    => 200,
                'message' => 'OpenAI have created your task successfully',
                'data'    => $created_result,
            ], 200);
        } catch (Exception $e) {
            Log::info('Tạo thất bại');
            return response()->json([
                'code'    => 500,
                'message' => 'Failed to create task',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
