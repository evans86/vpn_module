@extends('layouts.app', ['page' => __('Пакеты'), 'pageSlug' => 'packs-salesmans'])

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Купленные пакеты</h4>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="/admin/module/pack-salesman" class="mb-4">
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="salesman_search">Продавец</label>
                                        <input type="text" class="form-control" id="salesman_search" name="salesman_search"
                                               value="{{ request('salesman_search') }}"
                                               placeholder="Поиск по ID или имени">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="status">Статус</label>
                                        <select class="form-control" id="status" name="status">
                                            <option value="">Все статусы</option>
                                            <option value="paid" {{ request('status') == 'paid' ? 'selected' : '' }}>Оплачен</option>
                                            <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Ожидает оплаты</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label for="created_at">Дата создания</label>
                                        <input type="date" class="form-control" id="created_at" name="created_at"
                                               value="{{ request('created_at') }}">
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label>&nbsp;</label>
                                        <div class="btn-group btn-block">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-search"></i> Поиск
                                            </button>
                                            <a href="{{ route('admin.module.pack-salesman.index') }}"
                                               class="btn btn-secondary">
                                                <i class="fas fa-times"></i> Сбросить
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>

                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                <tr>
                                    <th><strong>#</strong></th>
                                    <th><strong>Пакет</strong></th>
                                    <th><strong>Продавец</strong></th>
                                    <th><strong>Цена</strong></th>
                                    <th><strong>Статус</strong></th>
                                    <th><strong>Дата создания</strong></th>
                                    <th><strong>Действия</strong></th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($pack_salesmans as $pack_salesman)
                                    <tr>
                                        <td>{{ $pack_salesman->id }}</td>
                                        <td>
                                            @if($pack_salesman->pack)
                                                <a href="{{ route('admin.module.key-activate.index', ['pack_salesman_id' => $pack_salesman->id]) }}"
                                                   title="Просмотреть ключи пакета">
                                                    {{ $pack_salesman->pack->name }}
                                                </a>
                                                <small class="d-block text-muted">
                                                    <a href="{{ route('admin.module.key-activate.index', ['pack_salesman_id' => $pack_salesman->id]) }}"
                                                       class="text-primary"
                                                       title="Просмотреть ключи пакета">
                                                        Кол-во ключей: {{ $pack_salesman->pack->count }}
                                                    </a>
                                                </small>
                                            @else
                                                <span class="text-danger">Пакет удален</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($pack_salesman->salesman)
                                                {{ $pack_salesman->salesman->username }}
                                                <small class="d-block">
                                                    <a href="/admin/module/salesman?telegram_id={{ $pack_salesman->salesman->telegram_id }}" class="text-primary">
                                                        ID: {{ $pack_salesman->salesman->telegram_id }}
                                                    </a>
                                                </small>
                                            @else
                                                <span class="text-danger">Продавец удален</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($pack_salesman->pack)
                                                {{ number_format($pack_salesman->pack->price, 2) }} ₽
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge {{ $pack_salesman->getStatusBadgeClass() }}">
                                                {{ $pack_salesman->getStatusText() }}
                                            </span>
                                        </td>
                                        <td>{{ $pack_salesman->created_at->format('d.m.Y H:i') }}</td>
                                        <td>
                                            <div class="btn-group">
                                                @if($pack_salesman->isPaid())
                                                    <button type="button"
                                                            class="btn btn-sm btn-success"
                                                            onclick="markAsPaid({{ $pack_salesman->id }})">
                                                        Отметить оплаченным
                                                    </button>
                                                @else
                                                    -
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="d-flex justify-content-center mt-4">
                            {{ $pack_salesmans->links('vendor.pagination.bootstrap-4') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('js')
        <script>
            function markAsPaid(id) {
                if (confirm('Вы уверены, что хотите отметить пакет как оплаченный?')) {
                    $.ajax({
                        url: `/admin/module/pack-salesman/${id}/mark-as-paid`,
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                        },
                        success: function(response) {
                            if (response.success) {
                                location.reload();
                            }
                        },
                        error: function(xhr) {
                            alert('Произошла ошибка при обновлении статуса');
                        }
                    });
                }
            }
        </script>
    @endpush
@endsection
