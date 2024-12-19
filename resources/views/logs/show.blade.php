@extends('layouts.app', ['page' => __('Детали лога'), 'pageSlug' => 'logs'])

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Детали лога</h4>
                        <a href="{{ route('admin.logs.index') }}" class="btn btn-primary btn-sm float-right">
                            <i class="fas fa-arrow-left"></i> Назад к списку
                        </a>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table">
                                    <tr>
                                        <th>Время:</th>
                                        <td>{{ $log->created_at->format('Y-m-d H:i:s') }}</td>
                                    </tr>
                                    <tr>
                                        <th>Уровень:</th>
                                        <td>
                                            <span class="badge badge-{{ $log->getLevelColorClass() }}">
                                                <i class="fas {{ $log->getLevelIcon() }} mr-1"></i>
                                                {{ $log->level }}
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Источник:</th>
                                        <td>{{ $log->source }}</td>
                                    </tr>
                                    <tr>
                                        <th>Пользователь:</th>
                                        <td>{{ $log->user_id ?: 'Система' }}</td>
                                    </tr>
                                    <tr>
                                        <th>IP адрес:</th>
                                        <td>{{ $log->ip_address }}</td>
                                    </tr>
                                    <tr>
                                        <th>User Agent:</th>
                                        <td>{{ $log->user_agent }}</td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title">Сообщение</h5>
                                    </div>
                                    <div class="card-body">
                                        <pre class="message-box">{{ $log->message }}</pre>
                                    </div>
                                </div>
                                @if($log->context)
                                    <div class="card mt-3">
                                        <div class="card-header">
                                            <h5 class="card-title">Контекст</h5>
                                        </div>
                                        <div class="card-body">
                                            <pre class="context-box">{{ json_encode($log->context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('css')
    <style>
        .message-box, .context-box {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
    </style>
@endpush
