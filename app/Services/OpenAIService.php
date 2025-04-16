<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\Tag;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use OpenAI;

class OpenAIService
{
    protected $client;

    public function __construct()
    {
        $this->client = OpenAI::client(env('OPENAI_API_KEY'));
    }

    public function analyzeRequest($userRequest, $tableStructure)
    {

        $setting = Setting::select('*')
            ->where('user_id', '=', Auth::id())
            ->first();
        Log::info($setting);

        $tag = Tag::select('id')->where('user_id', Auth::id())->pluck('id')->toArray();

        $currentDateTime = Carbon::parse(now()->format('Y-m-d H:i:s'), $setting->timezone_code);

        try {
            $response = $this->client->chat()->create([
                'model'     => 'gpt-4o-mini',
                'messages'   => [
                    ['role' => 'system', 'content' => "Bạn là một trợ lý lập kế hoạch thông minh. 
                    Hãy phân tích tin nhắn của người dùng và xác định các trường dữ liệu cần thiết dựa trên cấu trúc bảng sau.
                    Trả lời chỉ bằng JSON hợp lệ, không có bất kỳ văn bản nào khác ngoài JSON.

                    Cấu trúc bảng:
                    " . json_encode($tableStructure, JSON_PRETTY_PRINT) . "

                    Tin nhắn của người dùng: \"$userRequest\".

                    Danh sách cá id tag của người dùng: " . json_encode($tag) . ".

                    Trả về kết quả theo định dạng sau và cần có đầy đủ các trường theo cấu trúc đã cung cấp: 

                    <json>
                    { \"field_1\": \"value_1\", \"field_2\": \"value_2\" }
                    </json>"],
                    ['role' => 'user', 'content' => 'Tin nhắn của tôi: ' . $userRequest],
                    [
                        'role' => 'user', 
                        'content' => 'Hiện tại tôi đang là ngày: ' . $currentDateTime . 'với múi giờ' . $setting->timezone_code . 'và sử dụng tiếng ' .$setting->language
                    ],
                    ['role' => 'user', 'content' => 'Hãy trích xuất các trường phù hợp từ tin nhắn trên.'],
                ]
            ]);

            // Ghi log phản hồi
            Log::info('Tin nhắn được nhận: ' . $userRequest);
            Log::info('Phản hồi từ OpenAI: ' . json_encode($response));

            // Kiểm tra xem OpenAI có trả về kết quả hợp lệ không
            // if (!isset($response['choices'][0]['message']['content'])) {
            //     Log::error('Không có dữ liệu hợp lệ từ OpenAI!');
            //     return null;
            // }

            // // Giải mã JSON từ OpenAI
            // $aiResponse = json_decode($response['choices'][0]['message']['content'], true);

            // Lấy content trả về từ OpenAI
            $content = $response['choices'][0]['message']['content'] ?? '';

            // Lọc bỏ code block Markdown nếu có
            $content = trim($content);
            if (str_starts_with($content, '```json')) {
                $content = preg_replace('/^```json\s*/', '', $content); // bỏ đầu ```json
                $content = preg_replace('/```$/', '', $content); // bỏ cuối ```
            }
            $content = trim($content);

            // Ghi log thử
            Log::info('Nội dung đã xử lý từ OpenAI: ' . $content);

            // Kiểm tra xem JSON có hợp lệ không
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('Lỗi khi giải mã JSON: ' . json_last_error_msg());
                return null;
            }

            return $content;
        } catch (Exception $e) {
            Log::error('Lỗi khi gọi OpenAI: ' . $e->getMessage());
            return null;
        }
    }
}
