@extends('layouts.app', ['page' => __('Детали ключа активации'), 'pageSlug' => 'activate_keys'])

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Детали ключа активации</h4>
                        <a href="{{ route('admin.module.key-activate.index') }}" class="btn btn-link">
                            &larr; Назад к списку
                        </a>
                    </div>

                    <div class="card-body">
                        <div class="row">
                            <!-- Основная информация -->
                            <div class="col-md-6">
                                <h5 class="mb-4">Основная информация</h5>

                                <div class="mb-3">
                                    <label class="form-label">ID</label>
                                    <div>{{ $key->id }}</div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Статус</label>
                                    <div>
                                        <span
                                            class="badge {{ $key->status === 'active' ? 'badge-success' : 'badge-danger' }}">
                                            {{ $key->status }}
                                        </span>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Дата создания</label>
                                    <div>{{ $key->created_at->format('d.m.Y H:i:s') }}</div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Дата активации</label>
                                    <div>{{ $key->activated_at ? $key->activated_at->format('d.m.Y H:i:s') : 'Не активирован' }}</div>
                                </div>
                            </div>

                            <!-- Связанные данные -->
                            <div class="col-md-6">
                                <h5 class="mb-4">Связанные данные</h5>

                                @if($key->packSalesman)
                                    <div class="mb-3">
                                        <label class="form-label">Пакет</label>
                                        <div>
                                            <a href="{{ route('admin.module.pack.show', $key->packSalesman->pack) }}"
                                               class="text-primary">
                                                {{ $key->packSalesman->pack->name }}
                                            </a>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Продавец</label>
                                        <div>
                                            <a href="{{ route('admin.module.salesman.show', $key->packSalesman->salesman) }}"
                                               class="text-primary">
                                                {{ $key->packSalesman->salesman->name }}
                                            </a>
                                        </div>
                                    </div>
                                @endif

                                @if($key->keyActivateUser && $key->keyActivateUser->serverUser)
                                    <div class="mb-3">
                                        <label class="form-label">Пользователь сервера</label>
                                        <div>
                                            <a href="{{ route('admin.server-users.show', $key->keyActivateUser->serverUser) }}"
                                               class="text-primary">
                                                {{ $key->keyActivateUser->serverUser->id }}
                                            </a>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <!-- Действия -->
                        <div class="mt-4 pt-4 border-top">
                            <div class="btn-group">
                                <button onclick="testActivate('{{ $key->id }}')"
                                        class="btn btn-primary">
                                    Тест активации
                                </button>

                                <button onclick="updateDates('{{ $key->id }}')"
                                        class="btn btn-success">
                                    Обновить даты
                                </button>

                                <button onclick="deleteKey('{{ $key->id }}')"
                                        class="btn btn-danger">
                                    Удалить
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('js')
        <script>
            function testActivate(keyId) {
                if (!confirm('Вы уверены, что хотите протестировать активацию этого ключа?')) return;

                fetch(`/admin/module/key-activate/${keyId}/test-activate`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    }
                })
                    .then(response => response.json())
                    .then(data => {
                        alert(data.message || 'Тест активации выполнен успешно');
                        if (data.success) location.reload();
                    })
                    .catch(error => alert('Ошибка при выполнении теста активации'));
            }

            function updateDates(keyId) {
                if (!confirm('Вы уверены, что хотите обновить даты для этого ключа?')) return;

                fetch(`/admin/module/key-activate/${keyId}/update-dates`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    }
                })
                    .then(response => response.json())
                    .then(data => {
                        alert(data.message || 'Даты успешно обновлены');
                        if (data.success) location.reload();
                    })
                    .catch(error => alert('Ошибка при обновлении дат'));
            }

            function deleteKey(keyId) {
                if (!confirm('Вы уверены, что хотите удалить этот ключ?')) return;

                fetch(`/admin/module/key-activate/${keyId}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    }
                })
                    .then(response => response.json())
                    .then(data => {
                        alert(data.message || 'Ключ успешно удален');
                        if (data.success) window.location.href = '{{ route("admin.module.key-activate.index") }}';
                    })
                    .catch(error => alert('Ошибка при удалении ключа'));
            }
        </script>
    @endpush
@endsection
