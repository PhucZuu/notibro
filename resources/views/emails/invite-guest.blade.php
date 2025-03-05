<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thư Mời Sự Kiện</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #f0f4ff, #dfe7fd);
            font-family: 'Poppins', sans-serif;
        }

        .card {
            max-width: 600px;
            margin: 50px auto;
            border-radius: 15px;
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            background-color: #ffffff;
        }

        .card-header {
            background: linear-gradient(135deg, #007bff, #6a11cb, #bb1ee2);
            color: white;
            text-align: center;
            padding: 25px;
        }

        .card-header h2 {
            font-size: 24px;
            margin-bottom: 5px;
            font-weight: bold;
        }

        .card-header p {
            font-size: 16px;
            opacity: 0.9;
        }

        .event-image {
            width: 100%;
            height: auto;
            border-bottom: 5px solid #007bff;
        }

        .list-group-item {
            border: none;
            font-size: 16px;
            display: flex;
            align-items: center;
            background: none;
            padding: 12px;
        }

        .list-group-item span {
            font-size: 22px;
            color: #6a11cb;
        }

        .btn-custom {
            font-size: 16px;
            padding: 10px 15px;
            border-radius: 8px;
            font-weight: bold;
            background: linear-gradient(135deg, #eece3b, #3ed3c4);
            border: none;
            color: white;
            transition: all 0.3s ease-in-out;
            display: inline-block;
            text-decoration: none;
        }

        .contact-info {
            font-size: 15px;
            color: #555;
            text-align: center;
            padding-bottom: 20px;
        }

        .contact-info a {
            color: #007bff;
            font-weight: bold;
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
                <p>Bạn đã được mời tham gia 1 sự kiện sắp tới</p>
            </div>
            
            <div class="card-body text-center">
    
                <h3 class="mt-3 d-flex justify-content-center" style="font-weight: bold">{{ $data['title'] }}</h3>
    
                <div class="list-group mb-4">
                    <div class="list-group-item">
                        <span>📅</span>
                        <div class="ms-2">
                            <strong>Ngày:</strong> 
                            {{ \Carbon\Carbon::parse($data['start_time'])->locale('vi')->translatedFormat('l, d \t\há\n\g m Y') }}
                        </div>
                    </div>
    
                    <div class="list-group-item">
                        <span>🕒</span>
                        <div class="ms-2">
                            <strong>Thời gian:</strong> {{ \Carbon\Carbon::parse($data['start_time'])->format('H:i') }}
                        </div>
                    </div>
    
                    @if ($data['location'])
                    <div class="list-group-item">
                        <span>📍</span>
                        <div class="ms-2">
                            <strong>Địa điểm:</strong> {{ $data['location'] }}
                        </div>
                    </div>
                    @endif
                </div>
    
                <div class="d-flex justify-content-center mb-4">
                    <a href="{{ env('URL_FRONTEND') .'/calendar/event/'. $data['uuid'] . '/invite' }}" class="btn btn-custom">Xem Chi Tiết</a>
                </div>
    
                <div class="contact-info">
                    <p>Mọi thắc mắc xin vui lòng liên hệ:</p>
                    <p><strong>{{ $ownerName }}</strong> - <a href="mailto:{{ $ownerEmail }}">{{ $ownerEmail }}</a></p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
