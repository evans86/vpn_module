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
                                        <label class="form-label">Владелец пакета (склад / опт)</label>
                                        <p class="text-muted small mb-1">Кому в БД принадлежит пакет ключей (часто технический аккаунт опта).</p>
                                        <div>
                                            <a href="{{ route('admin.module.salesman.show', $key->packSalesman->salesman) }}"
                                               class="text-primary">
                                                {{ $key->packSalesman->salesman->username ?? ('#'.$key->packSalesman->salesman->id) }}
                                                <span class="text-muted">— tg {{ $key->packSalesman->salesman->telegram_id }}</span>
                                            </a>
                                        </div>
                                    </div>
                                @endif

                                @if($key->module_salesman_id && $key->moduleSalesman)
                                    <div class="mb-3">
                                        <label class="form-label">Продавец витрины (модуль)</label>
                                        <p class="text-muted small mb-1">Через чей веб‑модуль продан ключ; <code>module_salesman_id</code> = {{ $key->module_salesman_id }}.</p>
                                        <div>
                                            <a href="{{ route('admin.module.salesman.show', $key->moduleSalesman) }}"
                                               class="text-primary">
                                                {{ $key->moduleSalesman->username ?? ('#'.$key->moduleSalesman->id) }}
                                                <span class="text-muted">— tg {{ $key->moduleSalesman->telegram_id }}</span>
                                            </a>
                                            @if($key->moduleSalesman->module_bot_id)
                                                <div class="small text-muted mt-1">Модуль в БД: <code>bot_module.id = {{ $key->moduleSalesman->module_bot_id }}</code></div>
                                            @endif
                                        </div>
                                    </div>
                                @endif

                                @if($key->user_tg_id)
                                    <div class="mb-3">
                                        <label class="form-label">Покупатель VPN (конечный пользователь)</label>
                                        <div>
                                            <a href="https://t.me/{{ $key->user_tg_id }}" target="_blank" rel="noopener" class="text-primary">
                                                Telegram ID {{ $key->user_tg_id }}
                                            </a>
                                        </div>
                                    </div>
                                @endif

                                @if($key->keyActivateUsers && $key->keyActivateUsers->isNotEmpty())
                                    <div class="mb-3">
                                        <label class="form-label">Пользователи сервера (слоты)</label>
                                        <p class="text-muted small mb-2">Один ключ может иметь несколько слотов (по одному на провайдера). Подключения тянутся с разных серверов.</p>
                                        <ul class="list-unstyled mb-0">
                                            @foreach($key->keyActivateUsers as $kau)
                                                @if($kau->serverUser)
                                                    <li class="mb-2">
                                                        <a href="{{ route('admin.module.server-users.show', $kau->serverUser) }}"
                                                           class="text-primary font-mono text-sm">
                                                            {{ Str::limit($kau->serverUser->id, 12) }}…
                                                        </a>
                                                        @if($kau->serverUser->panel && $kau->serverUser->panel->server)
                                                            <span class="text-muted small"> — {{ $kau->serverUser->panel->server->name ?? 'Панель #'.$kau->serverUser->panel_id }}</span>
                                                            @if($kau->serverUser->panel->server->provider)
                                                                <span class="badge badge-secondary">{{ $kau->serverUser->panel->server->provider }}</span>
                                                            @endif
                                                        @endif
                                                    </li>
                                                @endif
                                            @endforeach
                                        </ul>
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

                                @if(in_array($key->status, [\App\Models\KeyActivate\KeyActivate::ACTIVE, \App\Models\KeyActivate\KeyActivate::ACTIVATING, \App\Models\KeyActivate\KeyActivate::PAID], true) || (\App\Models\KeyActivate\KeyActivate::EXPIRED === (int) $key->status && $key->keyActivateUsers->isNotEmpty()))
                                    <button onclick="deactivateKey('{{ $key->id }}')"
                                            class="btn btn-warning">
                                        Деактивировать
                                    </button>
                                @endif

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

            function deactivateKey(keyId) {
                if (!confirm('Деактивировать ключ? Пользователи будут сняты со всех панелей Marzban, статус «Просрочен». Запись останется.')) return;

                fetch(`{{ url('/admin/module/key-activate') }}/${keyId}/deactivate`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    }
                })
                    .then(response => response.json().then(data => ({ ok: response.ok, data })))
                    .then(({ ok, data }) => {
                        if (data.warning) {
                            alert((data.message || 'Готово') + '\n\n' + data.warning);
                        } else {
                            alert(data.message || 'Ключ деактивирован');
                        }
                        if (ok) location.reload();
                    })
                    .catch(() => alert('Ошибка при деактивации'));
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
