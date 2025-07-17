<!DOCTYPE html>
<html>
<head>
    <title>Личный кабинет</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🚀</text></svg>">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
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
