<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gửi Mã OTP</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f6f6f6;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            background-color: #ffffff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            margin: auto;
        }
        h2 {
            color: #4A90E2;
            text-align: center;
        }
        p {
            color: #555;
            line-height: 1.5;
        }
        .otp {
            font-size: 28px;
            font-weight: bold;
            color: #4A90E2;
            text-align: center;
            padding: 15px;
            border: 2px dashed #4A90E2;
            border-radius: 5px;
            display: inline-block;
            margin: 20px 0;
        }
        .footer {
            margin-top: 30px;
            font-size: 14px;
            color: #777;
            text-align: center;
        }
        .footer a {
            color: #4A90E2;
            text-decoration: none;
        }
        .footer a:hover {
            text-decoration: underline;
        }
        @media (max-width: 600px) {
            .container {
                padding: 20px;
            }
            .otp {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Xin chào!</h2>
        <p>Dưới đây là mã OTP của bạn, vui lòng không chia sẻ mã OTP này cho bất kỳ ai:</p>
        <div class="otp">{{ $otp }}</div>
        <p>Mã OTP này sẽ hết hạn sau 5 phút.</p>
        <div class="footer">
            <p>Cảm ơn bạn đã sử dụng dịch vụ của chúng tôi!</p>
        </div>
    </div>
</body>
</html>