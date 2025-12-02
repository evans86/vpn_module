@extends('layouts.admin')

@section('title', 'Настройки бота')
@section('page-title', 'Обновление токена бота')

@section('content')
    <div class="space-y-6">
        <x-admin.card title="Обновление токена бота">
            <form action="{{ route('admin.module.bot.update-token') }}" method="POST">
                @csrf
                <div class="mb-4">
                    <label for="token" class="block text-sm font-medium text-gray-700 mb-1">
                        Токен
                    </label>
                    <input type="text" 
                           name="token" 
                           id="token" 
                           class="form-control" 
                           required
                           placeholder="Введите токен бота">
                </div>
                <div class="flex items-center justify-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save mr-2"></i> Сохранить
                    </button>
                </div>
            </form>
        </x-admin.card>
    </div>
@endsection
