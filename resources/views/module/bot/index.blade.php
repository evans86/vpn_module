@extends('layouts.app', ['page' => __('Bot Father'), 'pageSlug' => 'bot'])

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-12">
                <x-card title="Обновление токена бота">
                    <form action="{{ route('admin.module.bot.update-token') }}" method="POST">
                        @csrf
                        <div class="form-group">
                            <label for="token">Токен</label>
                            <input type="text" name="token" id="token" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Сохранить
                        </button>
                    </form>
                </x-card>
            </div>
        </div>
    </div>
@endsection
