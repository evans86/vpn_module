<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Личный кабинет | VPN Partner</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🔒</text></svg>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --dark: #1e293b;
            --light: #f8fafc;
            --gray: #94a3b8;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #f1f5f9;
            color: var(--dark);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .personal-nav {
            background: white;
            padding: 1.5rem 2rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .nav-logo {
            font-weight: 700;
            font-size: 1.25rem;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .nav-links {
            display: flex;
            gap: 2rem;
        }

        .nav-links a {
            color: var(--dark);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s;
            position: relative;
            padding: 0.5rem 0;
        }

        .nav-links a:hover {
            color: var(--primary);
        }

        .nav-links a::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--primary);
            transition: width 0.3s;
        }

        .nav-links a:hover::after {
            width: 100%;
        }

        .logout-btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 0.5rem 1.25rem;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }

        .logout-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
        }

        .soon-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 2rem;
            text-align: center;
            background: linear-gradient(135deg, #f9fafb 0%, #f1f5f9 100%);
        }

        .soon-content {
            max-width: 600px;
            width: 100%;
            padding: 3rem;
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            position: relative;
            overflow: hidden;
        }

        .soon-content::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 6px;
            background: linear-gradient(90deg, var(--primary), #8b5cf6);
        }

        .soon-title {
            font-size: 3.5rem;
            font-weight: 800;
            background: linear-gradient(90deg, var(--primary), #8b5cf6);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            margin-bottom: 1.5rem;
            line-height: 1;
        }

        .soon-subtitle {
            color: var(--gray);
            font-size: 1.125rem;
            margin-bottom: 2.5rem;
            line-height: 1.6;
        }

        .progress-container {
            width: 100%;
            margin-bottom: 2rem;
        }

        .progress-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }

        .progress-label {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--primary);
            background: rgba(99, 102, 241, 0.1);
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
        }

        .progress-percent {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--dark);
        }

        .progress-bar {
            height: 8px;
            width: 100%;
            background: #e2e8f0;
            border-radius: 9999px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            width: 65%;
            background: linear-gradient(90deg, var(--primary), #8b5cf6);
            border-radius: 9999px;
            position: relative;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.8; }
            100% { opacity: 1; }
        }

        .soon-footer {
            margin-top: 3rem;
            font-size: 0.875rem;
            color: var(--gray);
        }

        @media (max-width: 768px) {
            .personal-nav {
                flex-direction: column;
                gap: 1.5rem;
                padding: 1.5rem;
            }

            .nav-links {
                flex-direction: column;
                gap: 1rem;
                align-items: center;
            }

            .soon-title {
                font-size: 2.5rem;
            }
        }
    </style>
</head>
<body>
<nav class="personal-nav">
    <div class="nav-logo">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
        </svg>
        <span>VPN Partner</span>
    </div>
    <div class="nav-links">
        <a href="{{ route('personal.dashboard') }}">Главная</a>
        <a href="{{ route('personal.orders') }}">Заказы</a>
        <a href="{{ route('personal.stats') }}">Аналитика</a>
        <a href="#">Настройки</a>
    </div>
    <form action="{{ route('personal.logout') }}" method="POST">
        @csrf
        <button type="submit" class="logout-btn">
            Выйти
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline ml-1" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M3 3a1 1 0 00-1 1v12a1 1 0 102 0V4a1 1 0 00-1-1zm10.293 9.293a1 1 0 001.414 1.414l3-3a1 1 0 000-1.414l3-3a1 1 0 10-1.414-1.414L14 9.586l-2.293-2.293a1 1 0 00-1.414 1.414L12.586 11H5a1 1 0 100 2h7.586l-1.293 1.293z" clip-rule="evenodd" />
            </svg>
        </button>
    </form>
</nav>
<div class="personal-content">
    @yield('content')
</div>


</body>
</html>
