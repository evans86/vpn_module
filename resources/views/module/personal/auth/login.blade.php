<!DOCTYPE html>
<html>
<head>
    <title>Авторизация продавца</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            background-color: #f5f5f5;
            margin: 0;
        }
        .auth-container {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            max-width: 400px;
            width: 100%;
        }
        .auth-button {
            display: inline-block;
            background: #0088cc;
            color: white;
            padding: 12px 24px;
            border-radius: 4px;
            text-decoration: none;
            font-weight: bold;
            margin-top: 1rem;
        }
        .auth-button:hover {
            background: #006699;
        }
    </style>
</head>
<body>
<div class="auth-container">
    <h2>Авторизация продавца</h2>
    <p>Для входа в личный кабинет необходимо авторизоваться через Telegram бота</p>

    <a href="{{ route('personal.auth.telegram') }}" class="auth-button">
        Войти через Telegram
    </a>
</div>
</body>
</html>
