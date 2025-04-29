<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thư mời tham gia Tag</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap');
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #ff9ebd, #ff69b4);
            padding: 0;
            margin: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        .email-container {
            background: #fff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0px 10px 20px rgba(0, 0, 0, 0.15);
            max-width: 600px;
            width: 90%;
            animation: fadeIn 1s ease-in-out;
        }
        h2 {
            color: #ff4081;
            text-align: center;
            margin-bottom: 15px;
            font-weight: 600;
        }
        .task-details {
            background: #ffe6ee;
            padding: 20px;
            border-radius: 8px;
            margin-top: 15px;
            box-shadow: inset 0px 3px 6px rgba(0, 0, 0, 0.1);
        }
        .task-details ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .task-details li {
            padding: 10px 0;
            font-size: 16px;
            color: #555;
            border-bottom: 1px dashed #ff69b4;
        }
        .task-details li:last-child {
            border-bottom: none;
        }
        .task-details strong {
            color: #ff4081;
        }
        .btn {
            display: block;
            text-align: center;
            padding: 12px;
            background: linear-gradient(135deg, #ff69b4, #ff4081);
            color: #fff;
            text-decoration: none;
            border-radius: 8px;
            margin-top: 20px;
            font-size: 16px;
            font-weight: bold;
            transition: transform 0.3s ease-in-out, box-shadow 0.3s ease-in-out;
            box-shadow: 0px 4px 10px rgba(255, 105, 180, 0.4);
        }
        .btn:hover {
            background: linear-gradient(135deg, #ff4081, #ff0055);
            transform: translateY(-3px);
            box-shadow: 0px 6px 12px rgba(255, 105, 180, 0.6);
        }
        .footer {
            text-align: center;
            margin-top: 20px;
            font-size: 14px;
            color: #777;
            font-weight: 300;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>

<body>
    <div class="email-container">
        <h2>Thư mời tham gia Tag 🎉</h2>
        <p style="text-align: center;">✨ Bạn được mời tham gia vào một Tag mới ✨</p>

        <div class="task-details">
            <ul>
                <li><strong>Người mời:</strong> {{ $ownerName }}</li>
                <li><strong>Tên Tag:</strong> {{ $tag->name }}</li>
                <li><strong>Mô tả:</strong> {{ $tag->description ?? 'Không có mô tả' }}</li>
            </ul>
        </div>

        <a href="{{ env('URL_FRONTEND') . '/calendar/tag/' . $tag->uuid . '/invite' }}" class="btn">Tham gia ngay</a>

        <p class="footer">Trân trọng,<br><strong>Notibro 🌸</strong></p>
    </div>
</body>
</html>