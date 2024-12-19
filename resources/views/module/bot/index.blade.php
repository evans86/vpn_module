@extends('layouts.app', ['page' => __('Bot Father'), 'pageSlug' => 'bot'])

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-xl-12 col-lg-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Обновление токена бота</h4>
                    </div>
                    <div class="card-body">
                        @if(session('success'))
                            <div class="alert alert-success">
                                {{ session('success') }}
                            </div>
                        @endif

                        @if(session('error'))
                            <div class="alert alert-danger">
                                {{ session('error') }}
                            </div>
                        @endif

                        @if($errors->any())
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    @foreach($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <div class="basic-form">
                            <form method="POST" action="{{ route('module.bot.update-token') }}">
                                @csrf
                                <div class="form-group">
                                    <label>Токен бота</label>
                                    <div class="input-group">
                                        <input type="text" 
                                               name="token" 
                                               class="form-control @error('token') is-invalid @enderror" 
                                               placeholder="Введите новый токен бота"
                                               value="{{ old('token') }}"
                                               required>
                                        <div class="input-group-append">
                                            <button class="btn btn-primary" type="submit">
                                                <i class="fas fa-sync-alt mr-1"></i> Обновить
                                            </button>
                                        </div>
                                    </div>
                                    <small class="form-text text-muted">
                                        Токен должен быть получен от @BotFather. После обновления токена будет автоматически обновлен webhook.
                                    </small>
                                    @error('token')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </form>
                        </div>

                        <div class="mt-4">
                            <h5>Текущие настройки</h5>
                            <div class="table-responsive">
                                <table class="table">
                                    <tbody>
                                        <tr>
                                            <th style="width: 200px;">Webhook URL</th>
                                            <td><code>{{ config('telegram.father_bot.webhook_url') }}</code></td>
                                        </tr>
                                        <tr>
                                            <th>Текущий токен</th>
                                            <td><code>{{ config('telegram.father_bot.token') }}</code></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
