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
                        <!-- Основная информация о логе -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="info-box">
                                    <span class="info-box-icon bg-info">
                                        <i class="fas fa-clock"></i>
                                    </span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">Время</span>
                                        <span class="info-box-number">{{ $log->created_at->format('Y-m-d H:i:s') }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-box">
                                    <span class="info-box-icon bg-{{ $log->getLevelColorClass() }}">
                                        <i class="fas {{ $log->getLevelIcon() }}"></i>
                                    </span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">Уровень</span>
                                        <span class="info-box-number">{{ $log->level }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Дополнительные детали -->
                        <div class="row mb-4">
                            <div class="col-md-4">
                                <div class="info-box">
                                    <span class="info-box-icon bg-secondary">
                                        <i class="fas fa-user"></i>
                                    </span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">Пользователь</span>
                                        <span class="info-box-number">{{ $log->user_id ?: 'Система' }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="info-box">
                                    <span class="info-box-icon bg-secondary">
                                        <i class="fas fa-network-wired"></i>
                                    </span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">IP адрес</span>
                                        <span class="info-box-number">{{ $log->ip_address }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="info-box">
                                    <span class="info-box-icon bg-secondary">
                                        <i class="fas fa-desktop"></i>
                                    </span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">User Agent</span>
                                        <span class="info-box-number">{{ $log->user_agent }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Сообщение и контекст -->
                        <div class="row">
                            <div class="col-md-8">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title">Сообщение</h5>
                                    </div>
                                    <div class="card-body">
                                        <pre class="message-box">{{ $log->message }}</pre>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                @if($log->context)
                                    <div class="card">
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
        .info-box {
            background: #fff;
            border: 1px solid #e3e6f0;
            border-radius: 4px;
            padding: 10px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }

        .info-box-icon {
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
            margin-right: 15px;
        }

        .info-box-content {
            flex: 1;
        }

        .info-box-text {
            font-size: 14px;
            color: #6c757d;
        }

        .info-box-number {
            font-size: 18px;
            font-weight: bold;
            color: #333;
        }

        .message-box, .context-box {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            white-space: pre-wrap;
            word-wrap: break-word;
        }

        .context-box {
            max-height: 300px;
            overflow-y: auto;
        }
    </style>
@endpush
