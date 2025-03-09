<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Task Reminder</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            background-color: #f4f4f4;
            padding: 20px;
        }
        .email-container {
            max-width: 600px;
            background: #ffffff;
            padding: 20px;
            margin: auto;
            border-radius: 8px;
            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);
        }
        h2 {
            color: #333;
        }
        .task-details {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 5px;
        }
        .btn {
            display: inline-block;
            background: #007bff;
            color: white;
            padding: 10px 15px;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 15px;
        }
        .btn:hover {
            background: #0056b3;
        }
        .footer {
            margin-top: 20px;
            font-size: 0.9em;
            color: #666;
        }
    </style>
</head>
<body>

<div class="email-container">
    <h2>Xin chào {{ $user->name }},</h2>
    <p>Bạn có một nhiệm vụ sắp tới:</p>

    <div class="task-details">
        <ul>
            <li><strong>Tên nhiệm vụ:</strong> {{ $task->title }}</li>
            <li><strong>Thời gian bắt đầu:</strong> {{ \Carbon\Carbon::parse($occurrenceTime ?? $task->start_time)->format('H:i d/m/Y') }}</li>
            <li><strong>Mô tả:</strong> {{ $task->description ?? 'Không có mô tả' }}</li>
        </ul>
    </div>

    @if(isset($task->url))
        <p><a href="{{ $task->url }}" class="btn">Xem chi tiết nhiệm vụ</a></p>
    @endif

    <p>Vui lòng đảm bảo hoàn thành đúng hạn.</p>

    <p class="footer">Trân trọng,<br><strong>Notibro</strong></p>
</div>

</body>
</html>
