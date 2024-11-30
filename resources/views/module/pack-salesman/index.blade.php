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
                                                {{ $pack_salesman->pack->name }}
                                                <small class="d-block text-muted">
                                                    Кол-во ключей: {{ $pack_salesman->pack->count_keys }}
                                                </small>
                                            @else
                                                <span class="text-muted">Пакет удален</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($pack_salesman->salesman)
                                                {{ $pack_salesman->salesman->name }}
                                                <small class="d-block text-muted">
                                                    ID: {{ $pack_salesman->salesman->id }}
                                                </small>
                                            @else
                                                <span class="text-muted">Продавец удален</span>
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
                                                @if(!$pack_salesman->isPaid())
                                                    <button type="button" 
                                                            class="btn btn-sm btn-success" 
                                                            onclick="markAsPaid({{ $pack_salesman->id }})">
                                                        Отметить оплаченным
                                                    </button>
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

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Добавляем CSRF-токен в заголовки всех AJAX-запросов
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });
        });

        function markAsPaid(id) {
            if (confirm('Отметить пакет как оплаченный?')) {
                $.ajax({
                    url: `/admin/module/pack-salesman/${id}/mark-as-paid`,
                    method: 'POST',
                    success: function(response) {
                        if (response.success) {
                            toastr.success('Статус успешно обновлен');
                            setTimeout(() => window.location.reload(), 1000);
                        } else {
                            toastr.error(response.message || 'Произошла ошибка');
                        }
                    },
                    error: function(xhr) {
                        toastr.error('Произошла ошибка при обновлении статуса');
                        console.error('Error:', xhr);
                    }
                });
            }
        }
    </script>
    @endpush
@endsection
