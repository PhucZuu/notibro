<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thông báo nhiệm vụ</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background-color: #f4f4f4;
            padding: 0;
            margin: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }

        .email-container {
            background: #ffffff;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0px 5px 15px rgba(0, 0, 0, 0.1);
            max-width: 600px;
            width: 100%;
        }

        h2 {
            color: #333;
            text-align: center;
            margin-bottom: 15px;
        }

        .task-details {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
            margin-top: 10px;
        }

        .task-details ul {
            list-style: none;
            padding: 0;
        }

        .task-details li {
            padding: 8px 0;
            font-size: 16px;
        }

        .task-details strong {
            color: #007bff;
        }

        .btn {
            display: block;
            text-align: center;
            padding: 12px;
            background: #007bff;
            color: #fff;
            text-decoration: none;
            border-radius: 8px;
            margin-top: 15px;
            font-size: 16px;
            font-weight: bold;
        }

        .btn:hover {
            background: #0056b3;
        }

        .footer {
            text-align: center;
            margin-top: 20px;
            font-size: 14px;
            color: #555;
        }
    </style>
</head>

<body>
    <div class="email-container">
        <h2>Xin chào {{ $user->name }}</h2>
        <p>Bạn có một nhiệm vụ sắp tới:</p>

        <div class="task-details">
            @php
                use Carbon\Carbon;

                // Lấy timezone từ task hoặc settings (nếu không có thì mặc định là UTC)
                $timezone = $task->timezone_code;

                // Chuyển đổi thời gian sang múi giờ hợp lệ
                $formattedStartTime = Carbon::parse($occurrenceTime ?? $task->start_time)
                    ->setTimezone($timezone)
                    ->format('H:i d/m/Y');
            @endphp


            <ul>
                <li><strong>Tên nhiệm vụ:</strong> {{ $task->title }}</li>
                <li><strong>Thời gian bắt đầu:</strong> {{ $formattedStartTime }}</li>
                <li><strong>Mô tả:</strong> {{ $task->description ?? 'Không có mô tả' }}</li>
            </ul>
        </div>

        {{-- @if (isset($task->url))
            <a href="{{ $task->url }}" class="btn">Xem chi tiết nhiệm vụ</a>
        @endif --}}

        <p class="footer">Trân trọng,<br><strong>Notibro</strong></p>
    </div>
</body>

</html>
