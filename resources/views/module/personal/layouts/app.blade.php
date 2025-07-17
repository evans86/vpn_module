<!DOCTYPE html>
<html>
<head>
    <title>Личный кабинет</title>
    <link rel="icon"
          href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🚀</text></svg>">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<style>
    .personal-nav {
        background: #2c3e50;
        padding: 1rem;
        display: flex;
        gap: 1rem;
    }

    .personal-nav a {
        color: white;
        text-decoration: none;
    }

    .personal-container {
        max-width: 1200px;
        margin: 2rem auto;
        padding: 0 1rem;
    }

    .personal-card {
        background: white;
        border-radius: 8px;
        padding: 1.5rem;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }
</style>

<nav class="personal-nav">
    <a href="{{ route('personal.dashboard') }}">Главная</a>
    <a href="{{ route('personal.orders') }}">Мои заказы</a>
    <a href="{{ route('personal.stats') }}">Статистика</a>
    <form action="{{ route('logout') }}" method="POST">
        @csrf
        <button type="submit">Выйти</button>
    </form>
</nav>

<div class="personal-content">
    @yield('content')
</div>


</body>
</html>
