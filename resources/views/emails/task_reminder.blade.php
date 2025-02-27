<!DOCTYPE html>
<html>
<head>
    <title>Task Reminder</title>
</head>
<body>
    <h2>Xin chào {{ $user->name }},</h2>
    <p>Bạn có một nhiệm vụ sắp tới:</p>
    <ul>
        <li><strong>Tên nhiệm vụ:</strong> {{ $task->title }}</li>
        <li><strong>Thời gian bắt đầu:</strong> {{ $occurrenceTime ?? $task->start_time }}</li>
        <li><strong>Mô tả:</strong> {{ $task->description }}</li>
    </ul>
    <p>Vui lòng đảm bảo hoàn thành đúng hạn.</p>
    <p>Trân trọng,<br>Notibro</p>
</body>
</html>
