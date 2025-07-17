@extends('module.personal.layouts.app')

@section('content')
    <div class="personal-container">
        <h1>Добро пожаловать, {{ $salesman->name }}!</h1>

        <div class="personal-card">
            <h3>Ваши ключевые метрики:</h3>
            <!-- Контент дашборда -->
        </div>
    </div>
@endsection
