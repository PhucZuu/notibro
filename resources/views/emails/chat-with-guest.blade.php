<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f9fafb;
            padding: 20px;
            color: #333;
        }

        .container {
            max-width: 600px;
            margin: auto;
            background: #ffffff;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .header {
            border-bottom: 1px solid #e5e7eb;
            margin-bottom: 20px;
        }

        .footer {
            margin-top: 30px;
            font-size: 12px;
            color: #6b7280;
            text-align: center;
        }

        .message-content {
            white-space: pre-line;
            line-height: 1.6;
        }

        .task-box {
            background-color: #f8f9fa;
            border: 1px solid #ddd;
            padding: 16px;
            border-radius: 8px;
            font-family: Arial, sans-serif;
            font-size: 16px;
            line-height: 1.6;
            color: #333;
            margin-top: 12px;
        }

        .task-box strong {
            color: #222;
            display: inline-block;
            margin-bottom: 4px;
        }

        .task-box div {
            margin-top: 8px;
        }

        .user-info {
            margin-top: 10px;
            font-size: 14px;
            color: #4b5563;
        }
    </style>
</head>

<body>
    @php
        use Carbon\Carbon;
    @endphp
    <div class="container">
        <div class="header">
            Tiêu đề: <h2>{{ $title }}</h2>
        </div>

        <div class="message-content">
            Nội dung: {{ $content }}
        </div>

        @if ($task)
            <div class="task-box">
                <div>
                    <strong>Tên sự kiện:</strong> {{ $task->title ?? 'Không rõ' }}
                </div>

                <div>
                    <strong>Thời gian bắt đầu:</strong>
                    {{ Carbon::parse($task->start_time)->setTimezone($task->timezone_code)->format('d/m/Y H:i') }}
                </div>

                <div>
                    <strong>Múi giờ:</strong> {{ $task->timezone_code }}
                </div>

                @if ($task->location)
                    <div>
                        <strong>Địa điểm:</strong> {{ $task->location }}
                    </div>
                @endif
            </div>
        @endif

        <div class="user-info">
            Người gửi: <strong>{{ $user->first_name }} {{ $user->last_name }}</strong> - {{ $user->email }}
            @if ($isOwner)
                (Chủ sự kiện)
            @else
                (Khách)
            @endif
        </div>

        <div class="footer">
            Bạn nhận được email này vì bạn đang tham gia một sự kiện có liên quan.
        </div>
    </div>
</body>

</html>
