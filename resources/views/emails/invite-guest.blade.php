<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thư Mời Sự Kiện</title>
    
    <style>
        body {
            background: linear-gradient(135deg, #f3f7fa, #e1ecf7);
            font-family: 'Poppins', sans-serif;
            margin: 0;
            padding: 20px;
        }
    
        .card {
            max-width: 650px;
            margin: 40px auto;
            border-radius: 20px;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.1);
            background-color: #ffffff;
            overflow: hidden;
            animation: fadeIn 0.5s ease-in-out;
        }
    
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    
        .card-header {
            background: linear-gradient(135deg, #4f46e5, #6d28d9, #9333ea);
            color: white;
            text-align: center;
            padding: 30px 20px;
        }
    
        .card-header h2 {
            font-size: 26px;
            margin-bottom: 8px;
            font-weight: 600;
        }
    
        .card-header p {
            font-size: 16px;
            opacity: 0.95;
        }
    
        .card-body {
            padding: 30px 25px;
        }
    
        .card-body h3 {
            font-size: 22px;
            font-weight: bold;
            margin-bottom: 25px;
            color: #333;
        }
    
        .list-group {
            margin-bottom: 30px;
        }
    
        .list-group-item {
            display: flex;
            align-items: center;
            font-size: 16px;
            padding: 14px 0;
            border-bottom: 1px solid #eee;
        }
    
        .list-group-item:last-child {
            border-bottom: none;
        }
    
        .list-group-item i {
            font-size: 20px;
            color: #7c3aed;
            margin-right: 12px;
            width: 25px;
            text-align: center;
        }
    
        .btn-custom {
            font-size: 16px;
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: 600;
            background: linear-gradient(135deg, #facc15, #10b981);
            border: none;
            color: white;
            transition: background 0.3s ease, transform 0.2s;
            text-decoration: none;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
    
        .btn-custom:hover {
            background: linear-gradient(135deg, #fbbf24, #059669);
            transform: translateY(-2px);
        }
    
        .contact-info {
            text-align: center;
            font-size: 14px;
            color: #555;
            margin-top: 20px;
        }
    
        .contact-info a {
            color: #4f46e5;
            font-weight: 500;
            text-decoration: none;
        }
    
        .contact-info a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h2>Thư Mời Tham Gia Sự Kiện</h2>
                <p>Bạn đã được mời tham gia một sự kiện đặc biệt</p>
            </div>
            
            <div class="card-body text-center">
                <h3 style="text-align: center;">{{ $data['title'] }}</h3>
    
                <div class="list-group text-start">
                    <div class="list-group-item">
                        <i class="fa-solid fa-calendar-day"></i>
                        <div>
                            <strong>Ngày:</strong>
                            {{ \Carbon\Carbon::parse($data['start_time'], $data['timezone_code'])}}
                            <span>Múi giờ: {{$data['timezone_code']}}</span>
                        </div>
                    </div>
    
                    <div class="list-group-item">
                        <i class="fa-solid fa-clock"></i>
                        <div>
                            <strong>Thời gian:</strong>
                            {{ \Carbon\Carbon::parse($data['start_time'])->format('H:i') }}
                        </div>
                    </div>
    
                    @if ($data['location'])
                    <div class="list-group-item">
                        <i class="fa-solid fa-location-dot"></i>
                        <div>
                            <strong>Địa điểm:</strong> {{ $data['location'] }}
                        </div>
                    </div>
                    @endif
                </div>
    
                <a href="{{ env('URL_FRONTEND') .'/calendar/event/'. $data['uuid'] . '/invite' }}" class="btn btn-custom">
                    Xem Chi Tiết
                </a>
    
                <div class="contact-info mt-4">
                    <p>Mọi thắc mắc xin vui lòng liên hệ:</p>
                    <p><strong>{{ $ownerName }}</strong> - 
                    <a href="mailto:{{ $ownerEmail }}">{{ $ownerEmail }}</a></p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
