@extends('module.personal.layouts.app')

@section('content')
    <div class="soon-container">
        <div class="soon-content">
            <h1 class="soon-title">Скоро</h1>
            <p class="soon-subtitle">Вы вошли как: <strong>{{ $salesman->username ?? $salesman->telegram_id }}</strong>
            </p>
            <p class="soon-subtitle">Мы активно работаем над улучшением вашего личного кабинета. Новые функции появятся
                в ближайшее время!</p>

            <div class="progress-container">
                <div class="progress-info">
                    <span class="progress-label">В разработке</span>
                    <span class="progress-percent">65%</span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill"></div>
                </div>
            </div>

            <div class="soon-footer">
                <p>Следите за обновлениями. Мы уведомим вас, когда все будет готово.</p>
            </div>
        </div>
    </div>
@endsection
