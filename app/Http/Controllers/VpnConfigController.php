<?php

namespace App\Http\Controllers;

use App\Models\Bot\BotModule;
use App\Models\KeyActivate\KeyActivate;
use App\Models\KeyActivateUser\KeyActivateUser;
use App\Models\Panel\Panel;
use App\Models\ServerUser\ServerUser;
use App\Models\VPN\ConnectionLimitViolation;
use App\Jobs\AddMissingSlotsForKeyJob;
use App\Repositories\KeyActivate\KeyActivateRepository;
use App\Repositories\KeyActivateUser\KeyActivateUserRepository;
use App\Repositories\ServerUser\ServerUserRepository;
use App\Services\External\BottApi;
use App\Services\External\MarzbanAPI;
use App\Services\Panel\marzban\MarzbanService;
use App\Services\Panel\PanelStrategy;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class VpnConfigController extends Controller
{
    /**
     * @var KeyActivateRepository
     */
    private KeyActivateRepository $keyActivateRepository;
    /**
     * @var KeyActivateUserRepository
     */
    private KeyActivateUserRepository $keyActivateUserRepository;
    /**
     * @var ServerUserRepository
     */
    private ServerUserRepository $serverUserRepository;
    /**
     * @var \App\Services\Key\KeyActivateService
     */
    private $keyActivateService;

    public function __construct(
        KeyActivateRepository $keyActivateRepository,
        KeyActivateUserRepository $keyActivateUserRepository,
        ServerUserRepository $serverUserRepository,
        \App\Services\Key\KeyActivateService $keyActivateService
    )
    {
        $this->keyActivateRepository = $keyActivateRepository;
        $this->keyActivateUserRepository = $keyActivateUserRepository;
        $this->serverUserRepository = $serverUserRepository;
        $this->keyActivateService = $keyActivateService;
    }

    public function show(string $key_activate_id): Response
    {
        $key_activate_id = trim($key_activate_id);
        // Лимит памяти и времени: тяжёлая сборка по слотам и запросы к панелям (VPN-клиент и refresh)
        if ((int) ini_get('memory_limit') < 1024) {
            @ini_set('memory_limit', '1024M');
        }
        if (function_exists('set_time_limit')) {
            @set_time_limit(180);
        }

        try {
            // Если запрошен роут /config/error, перенаправляем на метод showError
            if ($key_activate_id === 'error') {
                return $this->showError();
            }

            // Быстрая проверка существования ключа (один лёгкий запрос) — для браузера не тянем слоты и связи.
            $keyActivate = $this->keyActivateRepository->findById($key_activate_id);

            if (!$keyActivate) {
                $showDemo = app()->environment('local') && config('app.debug', false);
                if ($showDemo) {
                    return $this->showDemoPage($key_activate_id);
                }
                if (request()->wantsJson()) {
                    return response()->json(['status' => 'error', 'message' => 'Configuration not found'], 404);
                }

                return response()->view('vpn.error', [
                    'message' => 'Конфигурация VPN не найдена. Пожалуйста, проверьте правильность ссылки или обратитесь в поддержку.'
                ]);
            }

            $userAgent = request()->header('User-Agent') ?? 'Unknown';
            // Явный параметр (обратная совместимость): ?format=subscription или ?sub=1
            $forceSubscription = in_array(request()->query('format'), ['subscription', 'sub', 'txt'], true)
                || request()->query('sub') === '1';
            // Подписка plain text по тому же URL без query — для VPN/HTTP-клиентов; в обычном браузере — HTML-страница.
            $isSubscriptionRequest = $forceSubscription
                || $this->isVpnClient($userAgent)
                || $this->isLikelyHttpClientLibrary($userAgent)
                || !$this->requestAcceptsHtml()
                || !$this->hasVersionedBrowserInUserAgent($userAgent);

            if ($isSubscriptionRequest) {
                $keyActivateUsers = $this->keyActivateUserRepository->findAllByKeyActivateIdForSubscription($key_activate_id);

                if ($keyActivateUsers->isEmpty()) {
                    if (in_array((int) $keyActivate->status, [KeyActivate::ACTIVATING, KeyActivate::PAID], true)) {
                        Log::debug('Подписка запрошена до создания слотов (активация ещё не завершена)', [
                            'key_activate_id' => $key_activate_id,
                            'status' => $keyActivate->status,
                            'source' => 'vpn',
                        ]);
                    } else {
                        Log::warning('KeyActivateUser not found for KeyActivate', [
                            'key_activate_id' => $key_activate_id,
                            'status' => $keyActivate->status,
                            'source' => 'vpn',
                        ]);
                    }
                    $replacedViolation = ConnectionLimitViolation::where('key_activate_id', $key_activate_id)
                        ->whereNotNull('key_replaced_at')->whereNotNull('replaced_key_id')->orderBy('key_replaced_at', 'desc')->first();
                    if ($replacedViolation && $replacedViolation->replaced_key_id) {
                        return response()->view('vpn.error', [
                            'message' => 'Ваш ключ доступа был заменен из-за нарушения лимита подключений. Пожалуйста, используйте новый ключ.',
                            'replacedKeyId' => $replacedViolation->replaced_key_id
                        ]);
                    }
                    if (app()->environment('local') && config('app.debug', false)) {
                        return $this->showDemoPage($key_activate_id);
                    }

                    return response()->view('vpn.error', ['message' => 'Конфигурация VPN не найдена.']);
                }


                $connectionKeys = $this->collectConnectionKeysFromKeyActivateUsers($keyActivateUsers);
                $bodyPreview = implode("\n", $connectionKeys);

                $keyActivate->loadMissing([
                    'packSalesman' => fn ($q) => $q->select('id', 'salesman_id', 'pack_id'),
                    'packSalesman.pack' => fn ($q) => $q->select('id', 'module_key'),
                    'packSalesman.salesman' => fn ($q) => $q->select('id', 'telegram_id', 'bot_link', 'panel_id', 'module_bot_id'),
                    'moduleSalesman' => fn ($q) => $q->select('id', 'telegram_id', 'bot_link', 'panel_id', 'module_bot_id'),
                    'moduleSalesman.botModule' => fn ($q) => $q->select('id', 'username', 'public_key'),
                ]);
                $profileTitle = $this->buildSubscriptionProfileTitle($keyActivate, $key_activate_id);

                $response = response($bodyPreview)
                    ->header('Content-Type', 'text/plain; charset=utf-8');

                // Заголовок для VPN-клиентов; не дублируем для обычного браузерного User-Agent без признаков клиента.
                $looksLikeDesktopBrowser = $this->hasVersionedBrowserInUserAgent($userAgent)
                    && !$this->isVpnClient($userAgent);
                if (!$looksLikeDesktopBrowser) {
                    $response->header('Profile-Title', $profileTitle);
                }

                return $response;
            }

            // Браузер: лёгкая оболочка (shell), контент подгрузится по /config/{token}/content.
            return response()->view('vpn.config-shell', [
                'token' => $key_activate_id,
                'contentUrl' => route('vpn.config.content', ['token' => $key_activate_id], false),
                'refreshUrl' => route('vpn.config.refresh', ['token' => $key_activate_id], false),
            ]);

        } catch (\App\Exceptions\KeyReplacedException $e) {
            // Ключ был перевыпущен - показываем страницу ошибки с информацией о новом ключе
            $newKeyId = $e->getNewKeyId();

            Log::info('Key was replaced, showing error page with new key link', [
                'old_key_id' => $key_activate_id,
                'new_key_id' => $newKeyId,
                'source' => 'vpn'
            ]);

            if (request()->wantsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Key was replaced',
                    'new_key_id' => $newKeyId
                ], 404);
            }

            return response()->view('vpn.error', [
                'message' => 'Конфигурация VPN не найдена. Пожалуйста, проверьте правильность ссылки или обратитесь в поддержку.',
                'replacedKeyId' => $newKeyId
            ]);
        } catch (Exception $e) {
            Log::error('Error showing VPN config', [
                'key_activate_id' => $key_activate_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Проверяем, может быть это ошибка 404 из-за перевыпуска ключа
            if (str_contains($e->getMessage(), '404') || str_contains($e->getMessage(), 'User not found')) {
                // Ищем KeyActivate по ID
                $keyActivate = $this->keyActivateRepository->findById($key_activate_id);

                if ($keyActivate) {
                    // Проверяем, был ли ключ перевыпущен
                    $replacedViolation = \App\Models\VPN\ConnectionLimitViolation::where('key_activate_id', $keyActivate->id)
                        ->whereNotNull('key_replaced_at')
                        ->whereNotNull('replaced_key_id')
                        ->orderBy('key_replaced_at', 'desc')
                        ->first();

                    if ($replacedViolation && $replacedViolation->replaced_key_id) {
                        Log::info('Key was replaced, showing error page with new key link', [
                            'old_key_id' => $key_activate_id,
                            'new_key_id' => $replacedViolation->replaced_key_id,
                            'source' => 'vpn'
                        ]);

                        if (request()->wantsJson()) {
                            return response()->json([
                                'status' => 'error',
                                'message' => 'Key was replaced',
                                'new_key_id' => $replacedViolation->replaced_key_id
                            ], 404);
                        }

                        return response()->view('vpn.error', [
                            'message' => 'Конфигурация VPN не найдена. Пожалуйста, проверьте правильность ссылки или обратитесь в поддержку.',
                            'replacedKeyId' => $replacedViolation->replaced_key_id
                        ]);
                    }
                }
            }

            if (request()->wantsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Configuration not found'
                ], 404);
            }

            return response()->view('vpn.error', [
                'message' => 'Конфигурация VPN не найдена. Пожалуйста, проверьте правильность ссылки или обратитесь в поддержку.'
            ]);
        }
    }

    /**
     * Контент страницы конфига (только из БД, без панелей). Для быстрой отрисовки: сначала отдаётся shell, потом fetch этого URL.
     * Возвращает JSON { success, page, lastUpdated?, message? } — данные для отрисовки на клиенте (без HTML с бэкенда).
     */
    public function showConfigContent(string $token): Response
    {
        $key_activate_id = trim($token);
        if ((int) ini_get('memory_limit') < 512) {
            @ini_set('memory_limit', '512M');
        }
        if (function_exists('set_time_limit')) {
            @set_time_limit(120);
        }

        try {
            // Как у подписки: findById + двухшаговая загрузка слотов (findAllByKeyActivateIdForSubscription)
            // вместо одного nested eager findWithConfigRelationsForContent — меньше нагрузка на БД и быстрее при сети до MySQL.
            $keyActivate = $this->keyActivateRepository->findById($key_activate_id);
            if (!$keyActivate) {
                return response()->json(['success' => false, 'message' => 'Ключ не найден'], 404);
            }
            $keyActivate->load([
                'packSalesman' => fn ($q) => $q->select('id', 'salesman_id', 'pack_id'),
                'packSalesman.salesman' => fn ($q) => $q->select('id', 'telegram_id', 'bot_link', 'panel_id', 'module_bot_id'),
            ]);
            $keyActivateUsers = $this->keyActivateUserRepository->findAllByKeyActivateIdForSubscription($key_activate_id);
            $keyActivate->setRelation('keyActivateUsers', $keyActivateUsers);

            if ($keyActivateUsers->isEmpty()) {
                $replacedViolation = ConnectionLimitViolation::where('key_activate_id', $key_activate_id)
                    ->whereNotNull('key_replaced_at')
                    ->whereNotNull('replaced_key_id')
                    ->orderBy('key_replaced_at', 'desc')
                    ->first();
                if ($replacedViolation && $replacedViolation->replaced_key_id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Ваш ключ доступа был заменен из-за нарушения лимита подключений. Пожалуйста, используйте новый ключ.',
                        'replacedKeyId' => $replacedViolation->replaced_key_id,
                    ], 404);
                }
                return response()->json(['success' => false, 'message' => 'Конфигурация не найдена.'], 404);
            }
            $data = $this->buildConnectionDataFromStored($keyActivate, $key_activate_id, $keyActivateUsers);
            $viewData = $this->buildBrowserPageViewData(
                $keyActivate,
                $data['firstKeyActivateUser'],
                $data['firstServerUser'],
                $data['connectionKeys'],
                $data['slotsWithLinks'],
                true,
                true,
                null,
                null,
                $data['lastUpdated'] ?? null
            );
            $page = $this->serializeConfigPageForClient($viewData);
            $lastUpdated = isset($data['lastUpdated']) && $data['lastUpdated']
                ? $data['lastUpdated']->format('d.m.Y H:i')
                : null;
            $lastUpdatedEpoch = isset($data['lastUpdated']) && $data['lastUpdated']
                ? $data['lastUpdated']->getTimestamp()
                : null;

            return response()->json([
                'success' => true,
                'page' => $page,
                'lastUpdated' => $lastUpdated,
                'lastUpdatedEpoch' => $lastUpdatedEpoch,
            ]);
        } catch (\Throwable $e) {
            Log::warning('VpnConfig content failed', [
                'key_activate_id' => $key_activate_id,
                'error' => $e->getMessage(),
                'source' => 'vpn',
            ]);
            return response()->json(['success' => false, 'message' => 'Не удалось загрузить конфигурацию.'], 500);
        }
    }

    /**
     * Кнопка «Обновить»: сразу отдаём рабочую конфигурацию из БД (как /content), без долгого HTTP к Marzban в этом запросе.
     * Синхронизация ссылок с панелями запускается после отправки ответа (Kernel::terminate) — иначе nginx даёт 504 и в логах пусто.
     * Клиент через несколько секунд тихо перезапрашивает /content, чтобы подтянуть уже обновлённые ключи из БД.
     */
    public function showConfigRefresh(string $token): Response
    {
        $key_activate_id = trim($token);
        $t0 = microtime(true);
        if ((int) ini_get('memory_limit') < 512) {
            @ini_set('memory_limit', '512M');
        }
        if (function_exists('set_time_limit')) {
            @set_time_limit(120);
        }

        Log::info('VpnConfig refresh: быстрый ответ (БД), фоновая синхронизация Marzban после send)', [
            'key_activate_id' => $key_activate_id,
            'source' => 'vpn',
        ]);

        try {
            $keyActivate = $this->keyActivateRepository->findById($key_activate_id);
            if (!$keyActivate) {
                return response()->json(['success' => false, 'message' => 'Ключ не найден'], 404);
            }
            $keyActivate->load([
                'packSalesman' => fn ($q) => $q->select('id', 'salesman_id', 'pack_id'),
                'packSalesman.salesman' => fn ($q) => $q->select('id', 'telegram_id', 'bot_link', 'panel_id', 'module_bot_id'),
            ]);
            $keyActivateUsers = $this->keyActivateUserRepository->findAllByKeyActivateIdForSubscription($key_activate_id);
            $keyActivate->setRelation('keyActivateUsers', $keyActivateUsers);

            if ($keyActivateUsers->isEmpty()) {
                $replacedViolation = ConnectionLimitViolation::where('key_activate_id', $key_activate_id)
                    ->whereNotNull('key_replaced_at')
                    ->whereNotNull('replaced_key_id')
                    ->orderBy('key_replaced_at', 'desc')
                    ->first();
                if ($replacedViolation && $replacedViolation->replaced_key_id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Ваш ключ доступа был заменен из-за нарушения лимита подключений. Пожалуйста, используйте новый ключ.',
                        'replacedKeyId' => $replacedViolation->replaced_key_id,
                    ], 404);
                }
                return response()->json(['success' => false, 'message' => 'Конфигурация не найдена.'], 404);
            }

            $data = $this->buildConnectionDataFromStored($keyActivate, $key_activate_id, $keyActivateUsers);
            $viewData = $this->buildBrowserPageViewData(
                $keyActivate,
                $data['firstKeyActivateUser'],
                $data['firstServerUser'],
                $data['connectionKeys'],
                $data['slotsWithLinks'],
                true,
                true,
                null,
                null,
                $data['lastUpdated'] ?? null
            );
            $page = $this->serializeConfigPageForClient($viewData);
            $lastUpdated = isset($data['lastUpdated']) && $data['lastUpdated']
                ? $data['lastUpdated']->format('d.m.Y H:i')
                : null;
            $lastUpdatedEpoch = isset($data['lastUpdated']) && $data['lastUpdated']
                ? $data['lastUpdated']->getTimestamp()
                : null;

            $durationMs = (int) round((microtime(true) - $t0) * 1000);
            Log::info('VpnConfig refresh: ответ отправлен (из БД)', [
                'key_activate_id' => $key_activate_id,
                'duration_ms' => $durationMs,
                'slots' => count($data['slotsWithLinks'] ?? []),
                'links_total' => count($data['connectionKeys'] ?? []),
                'last_updated_label' => $lastUpdated,
                'source' => 'vpn',
            ]);

            app()->terminating(function () use ($key_activate_id) {
                try {
                    /** @var self $ctrl */
                    $ctrl = app(self::class);
                    $ctrl->syncMarzbanForKeyActivateAfterResponse($key_activate_id);
                } catch (\Throwable $e) {
                    Log::error('VpnConfig refresh: сбой планировщика фоновой синхронизации', [
                        'key_activate_id' => $key_activate_id,
                        'error' => $e->getMessage(),
                        'source' => 'vpn',
                    ]);
                }
            });

            return response()->json([
                'success' => true,
                'page' => $page,
                'lastUpdated' => $lastUpdated,
                'lastUpdatedEpoch' => $lastUpdatedEpoch,
                'syncPending' => true,
            ]);
        } catch (\Throwable $e) {
            Log::warning('VpnConfig refresh: ошибка до ответа', [
                'key_activate_id' => $key_activate_id,
                'duration_ms' => (int) round((microtime(true) - $t0) * 1000),
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'source' => 'vpn',
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Не удалось обновить конфигурацию. Попробуйте позже.',
            ], 500);
        }
    }

    /**
     * Выполняется после отправки HTTP-ответа (terminate): тянем актуальные ссылки с Marzban в БД.
     * Не блокирует браузер и не упирается в таймаут nginx.
     */
    public function syncMarzbanForKeyActivateAfterResponse(string $key_activate_id): void
    {
        $t0 = microtime(true);
        $bgLimit = (int) config('panel.vpn_config_refresh_time_limit', 300);
        if ($bgLimit < 60) {
            $bgLimit = 60;
        }
        if ((int) ini_get('memory_limit') < 1024) {
            @ini_set('memory_limit', '1024M');
        }
        if (function_exists('set_time_limit')) {
            @set_time_limit($bgLimit);
        }

        Log::info('VpnConfig refresh: фон — синхронизация Marzban старт', [
            'key_activate_id' => $key_activate_id,
            'source' => 'vpn',
        ]);

        try {
            $keyActivate = $this->keyActivateRepository->findById($key_activate_id);
            if (!$keyActivate) {
                Log::warning('VpnConfig refresh: фон — ключ не найден', [
                    'key_activate_id' => $key_activate_id,
                    'source' => 'vpn',
                ]);

                return;
            }
            $keyActivate->load([
                'packSalesman' => fn ($q) => $q->select('id', 'salesman_id', 'pack_id'),
                'packSalesman.salesman' => fn ($q) => $q->select('id', 'telegram_id', 'bot_link', 'panel_id', 'module_bot_id'),
            ]);
            $this->buildConnectionData($keyActivate, $key_activate_id, true);

            Log::info('VpnConfig refresh: фон — синхронизация Marzban успех', [
                'key_activate_id' => $key_activate_id,
                'duration_ms' => (int) round((microtime(true) - $t0) * 1000),
                'source' => 'vpn',
            ]);
        } catch (\Throwable $e) {
            Log::warning('VpnConfig refresh: фон — синхронизация Marzban ошибка', [
                'key_activate_id' => $key_activate_id,
                'duration_ms' => (int) round((microtime(true) - $t0) * 1000),
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'source' => 'vpn',
            ]);
        }
    }

    /**
     * Быстрая сборка только из БД: сохранённые ссылки (server_user.keys), без запросов к панелям.
     * Для первого отображения страницы в браузере.
     * @param \Illuminate\Support\Collection|null $keyActivateUsers уже загруженные слоты — если переданы, повторный запрос не выполняется.
     */
    private function buildConnectionDataFromStored(KeyActivate $keyActivate, string $key_activate_id, ?\Illuminate\Support\Collection $keyActivateUsers = null): array
    {
        if ($keyActivateUsers === null) {
            $keyActivateUsers = $this->keyActivateUserRepository->findAllByKeyActivateId($key_activate_id);
        }
        $connectionKeys = [];
        $slotsWithLinks = [];
        $firstKeyActivateUser = null;
        $firstServerUser = null;
        $lastUpdated = null;

        foreach ($keyActivateUsers as $kau) {
            $serverUser = $kau->serverUser;
            if (!$serverUser && $kau->server_user_id) {
                $serverUser = ServerUser::with(['panel.server.location'])->find($kau->server_user_id);
                if ($serverUser) {
                    $kau->setRelation('serverUser', $serverUser);
                }
            }
            if (!$serverUser && $kau->server_user_id) {
                $serverUser = $this->serverUserRepository->findById($kau->server_user_id);
                if ($serverUser && !$serverUser->relationLoaded('panel')) {
                    $serverUser->load(['panel.server.location']);
                }
                if ($serverUser) {
                    $kau->setRelation('serverUser', $serverUser);
                }
            }
            if (!$serverUser) {
                continue;
            }
            if ($firstKeyActivateUser === null) {
                $firstKeyActivateUser = $kau;
                $firstServerUser = $serverUser;
            }
            if ($serverUser->panel && !$serverUser->panel->relationLoaded('server')) {
                $serverUser->panel->load('server.location');
            }
            $stored = json_decode($serverUser->keys ?? '[]', true);
            $slotLinks = is_array($stored) ? $stored : [];
            if (!empty($slotLinks)) {
                if ($serverUser->updated_at && (!$lastUpdated || $serverUser->updated_at > $lastUpdated)) {
                    $lastUpdated = $serverUser->updated_at;
                }
                $connectionKeys = array_merge($connectionKeys, $slotLinks);
                $server = null;
                $location = null;
                $serverUser->loadMissing(['panel.server.location']);
                if ($serverUser->panel && $serverUser->panel->server) {
                    $server = $serverUser->panel->server;
                    $server->loadMissing('location');
                    $location = $server->location;
                }
                $locationCode = '';
                $name = 'Сервер';
                if ($location) {
                    $locationCode = strtolower(trim($location->code ?? ''));
                    $name = $this->locationCodeToFullName($location->code ?: '');
                    if ($name === '') {
                        $name = $location->code ?: 'Сервер';
                    }
                } elseif ($server && $server->name) {
                    $name = $server->name;
                }
                $name = $this->normalizeLocationLabelName($name);
                $sectionNumber = count($slotsWithLinks) + 1;
                $locationLabel = $this->locationLabelWithEmoji($location, $name . ' #' . $sectionNumber);
                $slotsWithLinks[] = [
                    'location_label'  => $locationLabel,
                    'location_code'   => $locationCode,
                    'connection_keys' => $slotLinks,
                ];
            }
        }

        if ($firstKeyActivateUser === null) {
            $firstKeyActivateUser = $keyActivateUsers->first();
            $firstServerUser = $firstKeyActivateUser && $firstKeyActivateUser->server_user_id
                ? $this->serverUserRepository->findById($firstKeyActivateUser->server_user_id)
                : null;
        }

        return [
            'connectionKeys' => $connectionKeys,
            'slotsWithLinks' => $slotsWithLinks,
            'firstKeyActivateUser' => $firstKeyActivateUser,
            'firstServerUser' => $firstServerUser,
            'lastUpdated' => $lastUpdated,
        ];
    }

    /**
     * Собрать connectionKeys и slotsWithLinks: добавить недостающие слоты, обновить ссылки с панелей.
     * @param bool $syncMultiProvider true при вызове из refresh — добавить слоты синхронно и вернуть полные данные
     */
    private function buildConnectionData(KeyActivate $keyActivate, string $key_activate_id, bool $syncMultiProvider = false): array
    {
        $keyActivateUsers = $this->keyActivateUserRepository->findAllByKeyActivateId($key_activate_id);

        $multiProviderSlots = config('panel.multi_provider_slots', []);
        if (!empty($multiProviderSlots) && is_array($multiProviderSlots) && $keyActivate->status === KeyActivate::ACTIVE) {
            $slotCount = $keyActivateUsers->count();
            $providerCount = count($multiProviderSlots);
            if ($providerCount > 0 && $slotCount < $providerCount) {
                $cacheKey = 'vpn_config_multi_provider_checked_' . $key_activate_id;
                $doAdd = $syncMultiProvider || !Cache::has($cacheKey);
                if ($doAdd) {
                    if ($syncMultiProvider) {
                        try {
                            $added = $this->keyActivateService->addMissingProviderSlots($keyActivate, false);
                            if ($added > 0) {
                                $keyActivateUsers = $this->keyActivateUserRepository->findAllByKeyActivateId($key_activate_id);
                            }
                        } catch (Exception $e) {
                            Log::warning('VpnConfig: addMissingProviderSlots failed', [
                                'key_activate_id' => $key_activate_id,
                                'error' => $e->getMessage(),
                                'source' => 'vpn',
                            ]);
                        }
                    } else {
                        if (config('queue.default') !== 'sync') {
                            AddMissingSlotsForKeyJob::dispatch($key_activate_id);
                        } else {
                            try {
                                $added = $this->keyActivateService->addMissingProviderSlots($keyActivate, false);
                                if ($added > 0) {
                                    $keyActivateUsers = $this->keyActivateUserRepository->findAllByKeyActivateId($key_activate_id);
                                }
                            } catch (Exception $e) {
                                Log::warning('VpnConfig: addMissingProviderSlots failed', [
                                    'key_activate_id' => $key_activate_id,
                                    'error' => $e->getMessage(),
                                    'source' => 'vpn',
                                ]);
                            }
                        }
                    }
                    if (!$syncMultiProvider) {
                        Cache::put($cacheKey, 1, now()->addMinutes(10));
                    }
                }
            }
        }

        $connectionKeys = [];
        $slotsWithLinks = [];
        $firstKeyActivateUser = null;
        $firstServerUser = null;
        $lastUpdated = null;

        $orderedSlots = [];
        foreach ($keyActivateUsers as $kau) {
            $serverUser = $kau->serverUser;
            if (!$serverUser && $kau->server_user_id) {
                $serverUser = ServerUser::with(['panel.server.location'])->find($kau->server_user_id);
                if ($serverUser) {
                    $kau->setRelation('serverUser', $serverUser);
                }
            }
            if (!$serverUser && $kau->server_user_id) {
                $serverUser = $this->serverUserRepository->findById($kau->server_user_id);
                if ($serverUser && !$serverUser->relationLoaded('panel')) {
                    $serverUser->load(['panel.server.location']);
                }
                if ($serverUser) {
                    $kau->setRelation('serverUser', $serverUser);
                }
            }
            if (!$serverUser) {
                Log::warning('Server user not found for KeyActivateUser slot', [
                    'key_activate_user_id' => $kau->id,
                    'key_activate_id' => $key_activate_id,
                    'source' => 'vpn',
                ]);
                continue;
            }
            if ($firstKeyActivateUser === null) {
                $firstKeyActivateUser = $kau;
                $firstServerUser = $serverUser;
            }
            if ($serverUser->panel && !$serverUser->panel->relationLoaded('server')) {
                $serverUser->panel->load('server.location');
            }
            $orderedSlots[] = ['kau' => $kau, 'serverUser' => $serverUser];
        }

        /** @var array<int, Panel|null> $panelTokenCache panel_id => свежий токен (один updateToken на панель) */
        $panelTokenCache = [];
        foreach ($orderedSlots as $slot) {
            $su = $slot['serverUser'];
            $pid = $su->panel_id;
            if (!$pid || isset($panelTokenCache[$pid])) {
                continue;
            }
            $panel = $su->panel;
            if (!$panel) {
                $panelTokenCache[$pid] = null;
                continue;
            }
            try {
                $panelType = $panel->panel ?? \App\Models\Panel\Panel::MARZBAN;
                if ($panelType === '') {
                    $panelType = \App\Models\Panel\Panel::MARZBAN;
                }
                $panelStrategyFactory = new \App\Services\Panel\PanelStrategyFactory();
                $panelStrategy = $panelStrategyFactory->create($panelType);
                $panelTokenCache[$pid] = $panelStrategy->updateToken((int) $pid);
            } catch (\Throwable $e) {
                Log::warning('VpnConfig: updateToken failed for panel', [
                    'panel_id' => $pid,
                    'error' => $e->getMessage(),
                    'source' => 'vpn',
                ]);
                $panelTokenCache[$pid] = null;
            }
        }

        foreach ($orderedSlots as $slot) {
            $kau = $slot['kau'];
            $serverUser = $slot['serverUser'];
            $pid = $serverUser->panel_id;
            $panelFresh = $pid ? ($panelTokenCache[$pid] ?? null) : null;
            if ($panelFresh) {
                $serverUser->setRelation('panel', $panelFresh);
            }

            $slotLinks = [];
            try {
                $links = $this->getFreshUserLinks($serverUser, $panelFresh);
                if (!empty($links)) {
                    $slotLinks = $links;
                    $connectionKeys = array_merge($connectionKeys, $links);
                    $serverUser->refresh();
                    $serverUser->loadMissing(['panel.server.location']);
                    if ($serverUser->updated_at && (!$lastUpdated || $serverUser->updated_at > $lastUpdated)) {
                        $lastUpdated = $serverUser->updated_at;
                    }
                }
                unset($links);
            } catch (\App\Exceptions\KeyReplacedException $e) {
                throw $e;
            } catch (\Throwable $e) {
                Log::warning('Failed to get fresh links for one slot, using stored', [
                    'key_activate_user_id' => $kau->id,
                    'server_user_id' => $serverUser->id,
                    'error' => $e->getMessage(),
                    'source' => 'vpn',
                ]);
                $stored = json_decode($serverUser->keys ?? '[]', true);
                if (!empty($stored) && is_array($stored)) {
                    $slotLinks = $stored;
                    $connectionKeys = array_merge($connectionKeys, $stored);
                }
                unset($stored);
            }
            if (!empty($slotLinks)) {
                $server = null;
                $location = null;
                $serverUser->loadMissing(['panel.server.location']);
                if ($serverUser->panel && $serverUser->panel->server) {
                    $server = $serverUser->panel->server;
                    $server->loadMissing('location');
                    $location = $server->location;
                }
                $locationCode = '';
                $name = 'Сервер';
                if ($location) {
                    $locationCode = strtolower(trim($location->code ?? ''));
                    $name = $this->locationCodeToFullName($location->code ?: '');
                    if ($name === '') {
                        $name = $location->code ?: 'Сервер';
                    }
                } elseif ($server && $server->name) {
                    $name = $server->name;
                }
                $name = $this->normalizeLocationLabelName($name);
                $sectionNumber = count($slotsWithLinks) + 1;
                $locationLabel = $this->locationLabelWithEmoji($location, $name . ' #' . $sectionNumber);
                $slotsWithLinks[] = [
                    'location_label'  => $locationLabel,
                    'location_code'   => $locationCode,
                    'connection_keys' => $slotLinks,
                ];
            }
            unset($slotLinks);
        }

        if (empty($connectionKeys)) {
            Log::warning('VpnConfig: после синхронизации с панелями нет ссылок — откат к данным из БД (как при первой загрузке)', [
                'key_activate_id' => $key_activate_id,
                'source' => 'vpn',
            ]);

            return $this->buildConnectionDataFromStored($keyActivate, $key_activate_id, null);
        }
        if ($firstKeyActivateUser === null) {
            $firstKeyActivateUser = $keyActivateUsers->first();
            $firstServerUser = $firstKeyActivateUser->serverUser ?? $this->serverUserRepository->findById($firstKeyActivateUser->server_user_id);
        }

        return [
            'connectionKeys' => $connectionKeys,
            'slotsWithLinks' => $slotsWithLinks,
            'firstKeyActivateUser' => $firstKeyActivateUser,
            'firstServerUser' => $firstServerUser,
            'lastUpdated' => $lastUpdated,
        ];
    }

    /**
     * Получить актуальные ссылки пользователя из панели Marzban.
     *
     * @param ServerUser $serverUser Пользователь сервера
     * @param Panel|null $panelFresh Уже обновлённая панель (один updateToken на несколько слотов) — иначе токен обновится здесь
     * @return array Массив ссылок
     */
    private function getFreshUserLinks(ServerUser $serverUser, ?Panel $panelFresh = null): array
    {
        try {
            $panel = $panelFresh ?? $serverUser->panel;
            if (!$panel) {
                throw new \RuntimeException('Panel not found for server user');
            }

            if ($panelFresh === null) {
                $panelType = $panel->panel ?? \App\Models\Panel\Panel::MARZBAN;
                if ($panelType === '') {
                    $panelType = \App\Models\Panel\Panel::MARZBAN;
                }
                $panelStrategyFactory = new \App\Services\Panel\PanelStrategyFactory();
                $panelStrategy = $panelStrategyFactory->create($panelType);
                $panel = $panelStrategy->updateToken($panel->id);
            }

            $marzbanApi = new MarzbanAPI($panel->api_address);

            // Получаем актуальные данные пользователя из Marzban
            $userData = $marzbanApi->getUser($panel->auth_token, $serverUser->id);

            // Если links есть, но их мало (меньше 10 для REALITY), обновляем пользователя с правильными inbounds
            if (!empty($userData['links']) && in_array($panel->config_type, ['reality', 'reality_stable', 'mixed'], true) && count($userData['links']) < 10) {
                try {
                    // Получаем конфигурацию панели, чтобы узнать доступные inbounds
                    $panelConfig = $marzbanApi->getConfig($panel->auth_token);
                    $availableInboundTags = [];

                    if (!empty($panelConfig['inbounds']) && is_array($panelConfig['inbounds'])) {
                        foreach ($panelConfig['inbounds'] as $inbound) {
                            if (isset($inbound['tag'])) {
                                $availableInboundTags[] = $inbound['tag'];
                            }
                        }
                    }

                    // Определяем все возможные inbounds для REALITY конфигурации
                    $allPossibleInbounds = [
                        'vmess' => ["VMESS-WS"],
                        'vless' => [
                            "VLESS-WS",
                            "VLESS TCP REALITY",
                            "VLESS GRPC REALITY",
                            "VLESS XHTTP REALITY",
                            "VLESS TCP REALITY ALT",
                            "VLESS TCP HTTP/1.1 Obfuscated",
                            "VLESS HTTP Upgrade"
                        ],
                        'trojan' => ["TROJAN-WS"],
                        'shadowsocks' => ["Shadowsocks-TCP"],
                    ];

                    // Фильтруем inbounds, оставляя только те, которые существуют на панели
                    $realityInbounds = [];
                    foreach ($allPossibleInbounds as $protocol => $inboundTags) {
                        $filteredTags = [];
                        foreach ($inboundTags as $tag) {
                            if (in_array($tag, $availableInboundTags)) {
                                $filteredTags[] = $tag;
                            }
                        }
                        if (!empty($filteredTags)) {
                            $realityInbounds[$protocol] = $filteredTags;
                        }
                    }

                    // Обновляем пользователя только если есть доступные inbounds
                    if (!empty($realityInbounds)) {
                        $updatedUserData = $marzbanApi->updateUser(
                            $panel->auth_token,
                            $serverUser->id,
                            $userData['expire'] ?? 0,
                            $userData['data_limit'] ?? 0,
                            $realityInbounds
                        );

                        // Получаем обновленные данные пользователя
                        $userData = $marzbanApi->getUser($panel->auth_token, $serverUser->id);
                    }
                } catch (Exception $e) {
                    Log::warning('Failed to update user inbounds for REALITY', [
                        'user_id' => $serverUser->id,
                        'panel_id' => $panel->id,
                        'error' => $e->getMessage(),
                        'source' => 'vpn'
                    ]);
                    // Продолжаем с исходными links
                }
            }

            // Обновляем ссылки в БД и расход трафика (для страницы конфига / суммы по слотам)
            if (array_key_exists('used_traffic', $userData)) {
                $serverUser->used_traffic = (int) $userData['used_traffic'];
            }

            // Обновляем ссылки в БД
            if (!empty($userData['links'])) {
                $serverUser->keys = json_encode($userData['links']);
                $serverUser->save();
                return $userData['links'];
            }

            // Если links нет, но есть subscription_url
            if (!empty($userData['subscription_url'])) {
                $links = [$userData['subscription_url']];
                $serverUser->keys = json_encode($links);
                $serverUser->save();
                return $links;
            }

            // Если не удалось получить из panel, используем сохраненные ключи
            Log::warning('Using stored keys for user', ['user_id' => $serverUser->id]);
            if ($serverUser->isDirty()) {
                $serverUser->save();
            }
            return json_decode($serverUser->keys, true) ?? [];

        } catch (Exception $e) {
            Log::error('Failed to get fresh user links', [
                'user_id' => $serverUser->id,
                'error' => $e->getMessage(),
                'source' => 'vpn'
            ]);

            // Проверяем, является ли это ошибкой 404 (User not found)
            // Если да, проверяем, был ли ключ перевыпущен
            // Для этого нужно получить key_activate_id из serverUser через keyActivateUser
            if (str_contains($e->getMessage(), '404') || str_contains($e->getMessage(), 'User not found')) {
                // Ищем KeyActivateUser по server_user_id
                $keyActivateUser = KeyActivateUser::where('server_user_id', $serverUser->id)->first();

                if ($keyActivateUser && $keyActivateUser->key_activate_id) {
                    // Проверяем, был ли ключ перевыпущен
                    $replacedViolation = \App\Models\VPN\ConnectionLimitViolation::where('key_activate_id', $keyActivateUser->key_activate_id)
                        ->whereNotNull('key_replaced_at')
                        ->whereNotNull('replaced_key_id')
                        ->orderBy('key_replaced_at', 'desc')
                        ->first();

                    if ($replacedViolation && $replacedViolation->replaced_key_id) {
                        // Ключ был перевыпущен - пробрасываем специальное исключение
                        throw new \App\Exceptions\KeyReplacedException(
                            'Ключ был перевыпущен',
                            $replacedViolation->replaced_key_id
                        );
                    }
                }
            }

            // В случае ошибки возвращаем сохраненные ключи, если они есть
            $storedKeys = json_decode($serverUser->keys, true) ?? [];
            if (empty($storedKeys)) {
                // Если сохраненных ключей нет, пробрасываем исключение дальше
                throw new RuntimeException('Не удалось получить ключи подключения: ' . $e->getMessage());
            }
            return $storedKeys;
        }
    }

    /**
     * Собрать список ссылок (connection keys) из уже загруженных KeyActivateUser.
     * Используется для быстрого ответа подписке без дополнительных запросов к БД.
     * Подпись (fragment) в ссылках подменяется на «Локация · Протокол» для понятных названий в клиенте.
     */
    private function collectConnectionKeysFromKeyActivateUsers(\Illuminate\Support\Collection $keyActivateUsers): array
    {
        $connectionKeys = [];
        foreach ($keyActivateUsers as $kau) {
            $serverUser = $kau->serverUser;
            if (!$serverUser || empty($serverUser->keys)) {
                continue;
            }
            if (!$serverUser->relationLoaded('panel')) {
                $serverUser->load('panel.server.location');
            }
            $locationLabel = null;
            if ($serverUser->panel && $serverUser->panel->server) {
                $server = $serverUser->panel->server;
                $server->loadMissing('location');
                if ($server->location) {
                    $code = $server->location->code ?? '';
                    $locationLabel = $this->locationCodeToFullName($code);
                    if ($locationLabel === '') {
                        $locationLabel = $code ?: 'VPN';
                    }
                    $locationLabel = $this->locationLabelWithEmoji($server->location, $locationLabel);
                } elseif (!empty($server->name)) {
                    $locationLabel = $server->name;
                }
            }
            if ($locationLabel === null) {
                $locationLabel = 'VPN';
            }
            $stored = json_decode($serverUser->keys, true);
            if (!is_array($stored)) {
                continue;
            }
            try {
                $formatted = $this->formatConnectionKeys($stored, $locationLabel);
                foreach ($formatted as $key) {
                    $connectionKeys[] = stripslashes($key['link']);
                }
            } catch (\Throwable $e) {
                // При любой ошибке форматирования отдаём сырые ссылки без подмены подписи
                foreach ($stored as $rawLink) {
                    if (is_string($rawLink) && $rawLink !== '') {
                        $connectionKeys[] = stripslashes($rawLink);
                    }
                }
            }
        }
        return $connectionKeys;
    }

    /**
     * Запрос явно принимает HTML (браузер) — иначе считаем подпиской и отдаём быстро.
     */
    private function requestAcceptsHtml(): bool
    {
        $accept = strtolower(request()->header('Accept', ''));
        return str_contains($accept, 'text/html');
    }

    /**
     * В User-Agent есть типичная для браузера подпись с версией (Chrome/, Firefox/, Safari/, Edg/).
     * Используется чтобы отдавать HTML только явным браузерам, а не приложениям с Mozilla в UA.
     */
    private function hasVersionedBrowserInUserAgent(string $userAgent): bool
    {
        $ua = strtolower($userAgent);
        return str_contains($ua, 'chrome/') || str_contains($ua, 'firefox/')
            || str_contains($ua, 'safari/') || str_contains($ua, 'edg/')
            || str_contains($ua, 'opr/') || str_contains($ua, 'msie ');
    }

    /**
     * HTTP-библиотеки и типичные клиенты подписки — не HTML-страница.
     */
    private function isLikelyHttpClientLibrary(string $userAgent): bool
    {
        $u = strtolower($userAgent);
        $libs = [
            'okhttp', 'curl/', 'python-requests', 'go-http', 'dart/', 'axios', 'winhttp',
            'apache-httpclient', 'urllib', 'java/', 'httpclient', 'cfnetwork',
        ];
        foreach ($libs as $lib) {
            if (strpos($u, $lib) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Определяет, является ли клиент VPN приложением (без учета регистра)
     */
    private function isVpnClient(string $userAgent): bool
    {
        $userAgentLower = strtolower($userAgent);
        if ($userAgentLower === '' || $userAgentLower === 'unknown') {
            return false;
        }

        $vpnPatterns = [
            'v2rayng', 'nekobox', 'nekoray', 'singbox', 'hiddify', 'hiddifynext', 'shadowrocket',
            'surge', 'quantumult', 'loon', 'streisand', 'clash', 'v2rayu', 'v2rayn',
            'v2rayx', 'qv2ray', 'trojan', 'wireguard', 'openvpn', 'openconnect',
            'softether', 'shadowsocks', 'shadowsocksr', 'ssr', 'outline', 'zerotier',
            'tailscale', 'windscribe', 'protonvpn', 'nordvpn', 'expressvpn', 'pritunl',
            'openwrt', 'dd-wrt', 'merlin', 'pivpn', 'algo', 'strongswan', 'ikev2',
            'ipsec', 'l2tp', 'pptp', 'v2raytun', 'happ', 'v2box', 'happproxy',
            'hexasoftware', 'v2rayg', 'anxray', 'kitsunebi', 'potatso', 'rocket',
            'pharos', 'stash', 'mellow', 'leaf', 'hysteria', 'tuic', 'naive', 'brook',
            'vnet', 'http injector', 'anonym', 'proxy', 'vpn', 'sub', 'subscribe',
            'subscription', 'hiddifynext'
        ];

        foreach ($vpnPatterns as $pattern) {
            if (str_contains($userAgentLower, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Определяет, является ли клиент браузером
     */
    private function isBrowserClient(string $userAgent): bool
    {
        $userAgentLower = strtolower($userAgent);

        // Список распространенных браузеров
        $browserPatterns = [
            'mozilla', 'chrome', 'safari', 'firefox', 'edge', 'opera', 'ie', 'trident',
            'webkit', 'gecko', 'netscape', 'maxthon', 'ucbrowser', 'vivaldi', 'yabrowser',
            'samsungbrowser'
        ];

        // Дополнительные признаки браузеров
        $hasBrowserHeaders = request()->header('Accept') &&
            str_contains(strtolower(request()->header('Accept')), 'text/html');

        $hasBrowserPattern = false;
        foreach ($browserPatterns as $pattern) {
            if (str_contains($userAgentLower, $pattern)) {
                $hasBrowserPattern = true;
                break;
            }
        }

        return $hasBrowserHeaders || $hasBrowserPattern;
    }

    /**
     * Показывает страницу ошибки (для локального просмотра)
     */
    public function showError(): Response
    {
        // В продакшене этот роут не должен быть доступен
        if (!app()->environment('local')) {
            abort(404);
        }

        return response()->view('vpn.error', [
            'message' => 'Конфигурация не найдена. Ключ может быть неактивен или удален.'
        ]);
    }

    /**
     * Показывает демо-страницу для локальной разработки
     */
    private function showDemoPage(string $key_activate_id): Response
    {
        // Демо-данные для локального просмотра
        $userInfo = [
            'username' => 'demo-user',
            'status' => 'active',
            'data_limit' => 100 * 1024 * 1024 * 1024, // 100 GB
            'data_limit_tariff' => 100 * 1024 * 1024 * 1024,
            'data_used' => 25.5 * 1024 * 1024 * 1024, // 25.5 GB
            'expiration_date' => time() + (30 * 24 * 60 * 60), // 30 дней
            'days_remaining' => 30,
            'show_traffic_limit' => true,
        ];

        // Демо-ключи подключения
        $demoKeys = [
            'vless://f83ca0f9-419c-4aa2-bb7e-47a82c900bef@77.238.239.214:2095?security=none&type=ws&headerType=&path=%2Fvless&host=#🚀%20Marz%20(12d21d3a-fe23-4c04-8ade-e316eac24fdf)%20[VLESS%20-%20ws]',
            'vmess://eyJhZGQiOiAiNzcuMjM4LjIzOS4yMTQiLCAiYWlkIjogIjAiLCAiaG9zdCI6ICIiLCAiaWQiOiAiMjBjYjJiZDMtMzMwYy00Y2NmLWFkZTItNjJlMjZjNmNlNzM5IiwgIm5ldCI6ICJ3cyIsICJwYXRoIjogIi92bWVzcyIsICJwb3J0IjogMjA5NiwgInBzIjogIlx1ZDgzZFx1ZGU4MCBNYXJ6ICgxMmQyMWQzYS1mZTIzLTRjMDQtOGFkZS1lMzE2ZWFjMjRmZGYpIFtWTWVzcyAtIHdzXSIsICJzY3kiOiAiYXV0byIsICJ0bHMiOiAibm9uZSIsICJ0eXBlIjogIiIsICJ2IjogIjIifQ==',
            'trojan://OaPcTZw8NomUQXfY@77.238.239.214:2097?security=none&type=ws&headerType=&path=%2Ftrojan&host=#🚀%20Marz%20(12d21d3a-fe23-4c04-8ade-e316eac24fdf)%20[Trojan%20-%20ws]',
            'ss://Y2hhY2hhMjAtaWV0Zi1wb2x5MTMwNTpVZnhLUG1oa3liRjhMdEQ0@77.238.239.214:2098#🚀%20Marz%20(12d21d3a-fe23-4c04-8ade-e316eac24fdf)%20[Shadowsocks%20-%20tcp]'
        ];

        $formattedKeys = $this->formatConnectionKeys($demoKeys);
        $botLink = '#';
        $netcheckUrl = route('netcheck.index');
        $isDemoMode = true; // Флаг для отображения демо-баннера

        // Создаем демо-нарушение для просмотра
        // Можно изменить violation_count через параметр ?violation=1,2,3 в URL для просмотра разных состояний
        $violationCount = request()->get('violation', 2); // По умолчанию показываем 2-е нарушение
        $violationCount = in_array((int)$violationCount, [1, 2, 3]) ? (int)$violationCount : 2;

        $demoViolation = new \App\Models\VPN\ConnectionLimitViolation([
            'violation_count' => $violationCount,
            'actual_connections' => 5,
            'allowed_connections' => 3,
            'ip_addresses' => ['192.168.1.1', '192.168.1.2', '10.0.0.1', '172.16.0.1', '192.168.1.3'],
            'status' => \App\Models\VPN\ConnectionLimitViolation::STATUS_ACTIVE,
            'notifications_sent' => $violationCount,
            'created_at' => now()->subHours(2),
            'last_notification_sent_at' => now()->subHours(1)
        ]);
        // Устанавливаем ID для корректной работы методов
        $demoViolation->id = 'demo-violation-' . $key_activate_id;
        $demoViolation->exists = true;

        // Создаем коллекцию с одним нарушением
        $violations = collect([$demoViolation]);

        // Для демо-режима: если параметр ?replaced=1, показываем перевыпущенный ключ
        $showReplaced = request()->get('replaced', 0) == 1;
        $replacedViolation = null;
        $newKeyActivate = null;
        $newKeyFormattedKeys = null;
        $newKeyUserInfo = null;

        if ($showReplaced) {
            // Создаем демо-нарушение с перевыпущенным ключом
            $replacedViolation = new \App\Models\VPN\ConnectionLimitViolation([
                'violation_count' => 3,
                'actual_connections' => 5,
                'allowed_connections' => 3,
                'ip_addresses' => ['192.168.1.1', '192.168.1.2', '10.0.0.1', '172.16.0.1', '192.168.1.3'],
                'status' => \App\Models\VPN\ConnectionLimitViolation::STATUS_RESOLVED,
                'notifications_sent' => 3,
                'created_at' => now()->subDays(2),
                'key_replaced_at' => now()->subHours(1),
                'replaced_key_id' => 'demo-new-key-' . $key_activate_id
            ]);
            $replacedViolation->id = 'demo-replaced-violation-' . $key_activate_id;
            $replacedViolation->exists = true;

            // Создаем демо-новый ключ
            $newKeyActivate = new \stdClass();
            $newKeyActivate->id = 'demo-new-key-' . $key_activate_id;
            $newKeyActivate->exists = true;

            // Используем те же ключи, но помечаем как новые
            $newKeyFormattedKeys = $formattedKeys;

            // Информация о новом ключе (те же данные, но можно изменить)
            $newKeyUserInfo = $userInfo;
        }

        Log::info('Showing demo page for local development', [
            'key_activate_id' => $key_activate_id,
            'show_replaced' => $showReplaced,
            'source' => 'vpn'
        ]);

        return response()->view('vpn.config', compact(
            'userInfo',
            'formattedKeys',
            'botLink',
            'netcheckUrl',
            'isDemoMode',
            'violations',
            'replacedViolation',
            'newKeyActivate',
            'newKeyFormattedKeys',
            'newKeyUserInfo'
        ));
    }

    /**
     * Суммарный расход трафика по всем слотам Marzban и лимит ключа (key_activate.traffic_limit).
     *
     * @return array{data_used: int, data_limit: int, data_limit_tariff: int, status: string}
     */
    private function aggregateTrafficForConfigPage(KeyActivate $keyActivate, $firstServerUser, bool $useStoredOnly): array
    {
        $tariff = (int) ($keyActivate->traffic_limit ?? 0);
        $usedTotal = 0;
        $limitFromPanels = 0;
        $firstStatus = 'active';

        if (!$keyActivate->relationLoaded('keyActivateUsers')) {
            $keyActivate->setRelation(
                'keyActivateUsers',
                $this->keyActivateUserRepository->findAllByKeyActivateIdForSubscription($keyActivate->id)
            );
        }
        $users = $keyActivate->keyActivateUsers ?? collect();

        $slotIndex = 0;
        foreach ($users as $kau) {
            $su = $kau->serverUser;
            if (!$su) {
                continue;
            }
            $panel = $su->panel;
            if (!$panel) {
                $usedTotal += (int) ($su->used_traffic ?? 0);
                $slotIndex++;
                continue;
            }
            $panel->loadMissing('server');
            $panelType = $panel->panel ?? Panel::MARZBAN;
            if ($panelType === '') {
                $panelType = Panel::MARZBAN;
            }

            if (!$useStoredOnly) {
                try {
                    $panel_strategy = new PanelStrategy($panelType);
                    $slotInfo = $panel_strategy->getSubscribeInfo($panel->id, $su->id);
                    $usedTotal += (int) ($slotInfo['used_traffic'] ?? 0);
                    $limitFromPanels += (int) ($slotInfo['data_limit'] ?? 0);
                    if ($slotIndex === 0) {
                        if (isset($slotInfo['key_status_updated']) && $slotInfo['key_status_updated'] === true) {
                            $keyActivate->refresh();
                        }
                        $firstStatus = (string) ($slotInfo['status'] ?? 'active');
                    }
                } catch (\Throwable $e) {
                    Log::warning('aggregateTrafficForConfigPage: subscribe info failed for slot', [
                        'key_activate_id' => $keyActivate->id,
                        'server_user_id' => $su->id,
                        'error' => $e->getMessage(),
                        'source' => 'vpn',
                    ]);
                    $usedTotal += (int) ($su->used_traffic ?? 0);
                }
            } else {
                $usedTotal += (int) ($su->used_traffic ?? 0);
            }
            $slotIndex++;
        }

        if ($users->isEmpty() && $firstServerUser && $firstServerUser->panel) {
            $panel = $firstServerUser->panel;
            $panelType = $panel->panel ?? Panel::MARZBAN;
            if ($panelType === '') {
                $panelType = Panel::MARZBAN;
            }
            if (!$useStoredOnly) {
                try {
                    $panel_strategy = new PanelStrategy($panelType);
                    $slotInfo = $panel_strategy->getSubscribeInfo($panel->id, $firstServerUser->id);
                    $usedTotal = (int) ($slotInfo['used_traffic'] ?? 0);
                    $limitFromPanels = (int) ($slotInfo['data_limit'] ?? 0);
                    if (isset($slotInfo['key_status_updated']) && $slotInfo['key_status_updated'] === true) {
                        $keyActivate->refresh();
                    }
                    $firstStatus = (string) ($slotInfo['status'] ?? 'active');
                } catch (\Throwable $e) {
                    Log::warning('aggregateTrafficForConfigPage: fallback single slot failed', [
                        'key_activate_id' => $keyActivate->id,
                        'error' => $e->getMessage(),
                        'source' => 'vpn',
                    ]);
                    $usedTotal = (int) ($firstServerUser->used_traffic ?? 0);
                }
            } else {
                $usedTotal = (int) ($firstServerUser->used_traffic ?? 0);
            }
        }

        $totalLimit = $tariff > 0 ? $tariff : $limitFromPanels;

        return [
            'data_used' => $usedTotal,
            'data_limit' => $totalLimit,
            'data_limit_tariff' => $tariff,
            'status' => $firstStatus,
        ];
    }

    /**
     * Собирает данные для страницы конфига в браузере (те же поля, что раньше передавались в Blade partial).
     *
     * @param array $slotsWithLinks Массив [ ['location_label' => string, 'connection_keys' => array], ... ]
     * @param bool $useStoredOnly не дергать панель (userInfo из ключа), для быстрого первого отображения
     * @param bool $partialOnly первый фрагмент /content: без лишних SQL по нарушениям
     * @param string|null $configRefreshUrl не используется (оставлен для совместимости)
     * @param string|null $configRefreshUrlForButton URL для кнопки «Обновить»
     * @param \DateTimeInterface|null $lastUpdated время последнего обновления конфига в БД
     * @return array<string, mixed>
     */
    private function buildBrowserPageViewData(KeyActivate $keyActivate, $keyActivateUser, $serverUser, $connectionKeys, array $slotsWithLinks = [], bool $useStoredOnly = false, bool $partialOnly = false, ?string $configRefreshUrl = null, ?string $configRefreshUrlForButton = null, $lastUpdated = null): array
    {
            // При первом открытии (useStoredOnly) не дергаем БД и не обновляем статус — страница отдаётся быстрее; статус обновится по кнопке «Обновить».
            if (!$useStoredOnly) {
                $keyActivate->refresh();
                if (!$keyActivate->relationLoaded('packSalesman')) {
                    $keyActivate->load([
                        'packSalesman' => fn ($q) => $q->select('id', 'salesman_id', 'pack_id'),
                        'packSalesman.pack' => fn ($q) => $q->select('id', 'module_key'),
                        'packSalesman.salesman' => fn ($q) => $q->select('id', 'telegram_id', 'bot_link', 'panel_id', 'module_bot_id'),
                        'moduleSalesman' => fn ($q) => $q->select('id', 'telegram_id', 'bot_link', 'panel_id', 'module_bot_id'),
                    ]);
                }
                $keyActivate = $this->keyActivateService->checkAndUpdateStatus($keyActivate);
            } elseif (!$keyActivate->relationLoaded('packSalesman')) {
                $keyActivate->load([
                    'packSalesman' => fn ($q) => $q->select('id', 'salesman_id', 'pack_id'),
                    'packSalesman.pack' => fn ($q) => $q->select('id', 'module_key'),
                    'packSalesman.salesman' => fn ($q) => $q->select('id', 'telegram_id', 'bot_link', 'panel_id', 'module_bot_id'),
                    'moduleSalesman' => fn ($q) => $q->select('id', 'telegram_id', 'bot_link', 'panel_id', 'module_bot_id'),
                ]);
            }

            // Трафик: сумма used по всем слотам (Marzban); лимит — traffic_limit ключа (тариф).
            $trafficAgg = $this->aggregateTrafficForConfigPage($keyActivate, $serverUser, $useStoredOnly);

            $finishAt = $keyActivate->finish_at ?? null;
            $daysRemaining = null;
            if ($finishAt && $finishAt > 0) {
                $daysRemaining = ceil(($finishAt - time()) / \App\Constants\TimeConstants::SECONDS_IN_DAY);
            }

            $userInfo = [
                'username' => $serverUser ? $serverUser->id : '',
                'status' => $trafficAgg['status'],
                'data_limit' => $trafficAgg['data_limit'],
                'data_limit_tariff' => $trafficAgg['data_limit_tariff'],
                'data_used' => $trafficAgg['data_used'],
                'expiration_date' => $finishAt,
                'days_remaining' => $daysRemaining,
                'show_traffic_limit' => $keyActivate->isFreeIssuedKey(),
            ];

            // Форматируем ключи для отображения (плоский список для обратной совместимости)
            $firstLocationLabel = (count($slotsWithLinks) === 1) ? ($slotsWithLinks[0]['location_label'] ?? null) : null;
            $formattedKeys = $this->formatConnectionKeys($connectionKeys, $firstLocationLabel);
            // Группировка по локации/серверу (массив групп — без перезаписи, все протоколы сохраняются)
            $formattedKeysGrouped = [];
            foreach ($slotsWithLinks as $slot) {
                $formattedKeysGrouped[] = [
                    'label' => $slot['location_label'],
                    'flag_code' => $slot['location_code'] ?? '',
                    'keys'  => $this->formatConnectionKeys($slot['connection_keys'], $slot['location_label'] ?? null),
                ];
            }

            // Ссылка на бота: для ключа из модуля — бот продавца, привязанного к модулю; иначе — бот, где купили/активировали
            $botLink = $this->resolveConfigDisplayBotLink($keyActivate);

            // Добавляем ссылку на страницу проверки качества сети
            $netcheckUrl = route('netcheck.index');
            $isDemoMode = false; // Это реальная страница, не демо

            // Первый фрагмент из БД (/content): без нарушений — меньше SQL; блок нарушений после «Обновить» (refresh).
            if ($useStoredOnly && $partialOnly) {
                $violations = collect();
                $replacedViolation = null;
            } elseif ($keyActivate->relationLoaded('activeViolations') && $keyActivate->relationLoaded('replacedViolation')) {
                $violations = $keyActivate->activeViolations;
                $replacedViolation = $keyActivate->replacedViolation;
            } else {
                $violations = $keyActivate->relationLoaded('activeViolations')
                    ? $keyActivate->activeViolations
                    : ConnectionLimitViolation::query()
                        ->where('key_activate_id', $keyActivate->id)
                        ->where('status', ConnectionLimitViolation::STATUS_ACTIVE)
                        ->whereNull('key_replaced_at')
                        ->orderByDesc('created_at')
                        ->get();
                $replacedViolation = $keyActivate->relationLoaded('replacedViolation')
                    ? $keyActivate->replacedViolation
                    : ConnectionLimitViolation::query()
                        ->where('key_activate_id', $keyActivate->id)
                        ->whereNotNull('key_replaced_at')
                        ->whereNotNull('replaced_key_id')
                        ->orderByDesc('key_replaced_at')
                        ->first();
            }

            $newKeyActivate = null;
            $newKeyFormattedKeys = null;
            $newKeyUserInfo = null;

            if ($replacedViolation && $replacedViolation->replaced_key_id && !$useStoredOnly) {
                $newKeyActivate = $this->keyActivateRepository->findById($replacedViolation->replaced_key_id);

                if ($newKeyActivate) {
                    $newKeyActivate->load([
                        'packSalesman' => fn ($q) => $q->select('id', 'salesman_id', 'pack_id'),
                        'packSalesman.salesman' => fn ($q) => $q->select('id', 'telegram_id', 'bot_link', 'panel_id', 'module_bot_id'),
                    ]);
                    $newKeyActivate = $this->keyActivateService->checkAndUpdateStatus($newKeyActivate);
                    $newKeyActivateUser = $this->keyActivateUserRepository->findByKeyActivateIdWithRelations($newKeyActivate->id);

                    if ($newKeyActivateUser && $newKeyActivateUser->serverUser) {
                        $newServerUser = $newKeyActivateUser->serverUser;
                        try {
                            $newConnectionKeys = $this->getFreshUserLinks($newServerUser);

                            if ($newConnectionKeys) {
                                $newLocationLabel = 'VPN';
                                if ($newServerUser->panel && $newServerUser->panel->server) {
                                    $newServer = $newServerUser->panel->server;
                                    $newServer->loadMissing('location');
                                    if ($newServer->location) {
                                        $code = $newServer->location->code ?? '';
                                        $newLocationLabel = $this->locationCodeToFullName($code);
                                        if ($newLocationLabel === '') {
                                            $newLocationLabel = $code ?: 'VPN';
                                        }
                                        $newLocationLabel = $this->locationLabelWithEmoji($newServer->location, $newLocationLabel);
                                    } elseif (!empty($newServer->name)) {
                                        $newLocationLabel = $newServer->name;
                                    }
                                }
                                $newKeyFormattedKeys = $this->formatConnectionKeys($newConnectionKeys, $newLocationLabel);

                                $newKeyActivate->setRelation(
                                    'keyActivateUsers',
                                    $this->keyActivateUserRepository->findAllByKeyActivateIdForSubscription($newKeyActivate->id)
                                );
                                $newTrafficAgg = $this->aggregateTrafficForConfigPage($newKeyActivate, $newServerUser, $useStoredOnly);

                                $newFinishAt = $newKeyActivate->finish_at ?? null;
                                $newDaysRemaining = null;
                                if ($newFinishAt && $newFinishAt > 0) {
                                    $newDaysRemaining = ceil(($newFinishAt - time()) / \App\Constants\TimeConstants::SECONDS_IN_DAY);
                                }

                                $newKeyUserInfo = [
                                    'username' => $newServerUser->id,
                                    'status' => $newTrafficAgg['status'],
                                    'data_limit' => $newTrafficAgg['data_limit'],
                                    'data_limit_tariff' => $newTrafficAgg['data_limit_tariff'],
                                    'data_used' => $newTrafficAgg['data_used'],
                                    'expiration_date' => $newFinishAt,
                                    'days_remaining' => $newDaysRemaining,
                                    'show_traffic_limit' => $newKeyActivate->isFreeIssuedKey(),
                                ];
                            }
                        } catch (Exception $e) {
                            Log::error('Ошибка при получении конфигурации нового ключа', [
                                'new_key_id' => $newKeyActivate->id,
                                'error' => $e->getMessage(),
                                'source' => 'vpn'
                            ]);
                        }
                    }
                }
            }

            $viewData = compact(
                'keyActivate',
                'userInfo',
                'formattedKeys',
                'formattedKeysGrouped',
                'botLink',
                'netcheckUrl',
                'isDemoMode',
                'violations',
                'replacedViolation',
                'newKeyActivate',
                'newKeyFormattedKeys',
                'newKeyUserInfo'
            );
            if (!$partialOnly) {
                $viewData['configRefreshUrlForButton'] = $configRefreshUrlForButton;
                $viewData['configLastUpdated'] = $lastUpdated ? $lastUpdated->format('d.m.Y H:i') : null;
            }

            return $viewData;
    }

    /**
     * Данные для клиентской отрисовки (без Eloquent и без HTML).
     *
     * @param array<string, mixed> $viewData
     * @return array<string, mixed>
     */
    private function serializeConfigPageForClient(array $viewData): array
    {
        $keyActivate = $viewData['keyActivate'] ?? null;
        $newKeyActivate = $viewData['newKeyActivate'] ?? null;

        $formattedKeysGrouped = [];
        foreach ($viewData['formattedKeysGrouped'] ?? [] as $g) {
            $formattedKeysGrouped[] = [
                'label' => $g['label'] ?? '',
                'flag_code' => $g['flag_code'] ?? '',
                'keys' => $this->normalizeFormattedKeysForClient($g['keys'] ?? []),
            ];
        }

        $violationsOut = [];
        $violations = $viewData['violations'] ?? null;
        if ($violations instanceof \Illuminate\Support\Collection) {
            foreach ($violations as $v) {
                $violationsOut[] = [
                    'violation_count' => (int) ($v->violation_count ?? 0),
                ];
            }
        }

        $replacedViolation = $viewData['replacedViolation'] ?? null;
        $replacedOut = null;
        if ($replacedViolation) {
            $kr = $replacedViolation->key_replaced_at ?? null;
            $replacedOut = [
                'key_replaced_at' => $kr ? $kr->getTimestamp() : null,
                'replaced_key_id' => $replacedViolation->replaced_key_id ?? null,
            ];
        }

        return [
            'meta' => [
                'keyStatus' => [
                    'EXPIRED' => KeyActivate::EXPIRED,
                    'ACTIVE' => KeyActivate::ACTIVE,
                    'PAID' => KeyActivate::PAID,
                ],
            ],
            'keyActivate' => $this->serializeKeyActivateForClient($keyActivate),
            'userInfo' => $this->normalizeUserInfoForClient($viewData['userInfo'] ?? []),
            'formattedKeys' => $this->normalizeFormattedKeysForClient($viewData['formattedKeys'] ?? []),
            'formattedKeysGrouped' => $formattedKeysGrouped,
            'botLink' => (string) ($viewData['botLink'] ?? '#'),
            'netcheckUrl' => (string) ($viewData['netcheckUrl'] ?? ''),
            'isDemoMode' => (bool) ($viewData['isDemoMode'] ?? false),
            'violations' => $violationsOut,
            'replacedViolation' => $replacedOut,
            'newKeyActivate' => $this->serializeKeyActivateForClient($newKeyActivate),
            'newKeyFormattedKeys' => $viewData['newKeyFormattedKeys'] !== null
                ? $this->normalizeFormattedKeysForClient($viewData['newKeyFormattedKeys'])
                : null,
            'newKeyUserInfo' => $viewData['newKeyUserInfo'] !== null
                ? $this->normalizeUserInfoForClient($viewData['newKeyUserInfo'])
                : null,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $keys
     * @return array<int, array<string, string>>
     */
    private function normalizeFormattedKeysForClient(array $keys): array
    {
        $out = [];
        foreach ($keys as $k) {
            $link = isset($k['link']) ? stripslashes((string) $k['link']) : '';
            $out[] = [
                'protocol' => (string) ($k['protocol'] ?? ''),
                'icon' => (string) ($k['icon'] ?? ''),
                'connection_type' => (string) ($k['connection_type'] ?? ''),
                'link' => $link,
            ];
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $userInfo
     * @return array<string, mixed>
     */
    private function normalizeUserInfoForClient(array $userInfo): array
    {
        $exp = $userInfo['expiration_date'] ?? null;
        if ($exp instanceof \DateTimeInterface) {
            $exp = $exp->getTimestamp();
        }

        return [
            'username' => (string) ($userInfo['username'] ?? ''),
            'status' => (string) ($userInfo['status'] ?? 'active'),
            'data_limit' => (float) ($userInfo['data_limit'] ?? 0),
            'data_limit_tariff' => (float) ($userInfo['data_limit_tariff'] ?? 0),
            'data_used' => (float) ($userInfo['data_used'] ?? 0),
            'expiration_date' => $exp !== null ? (int) $exp : null,
            'days_remaining' => isset($userInfo['days_remaining']) && $userInfo['days_remaining'] !== null
                ? (int) $userInfo['days_remaining']
                : null,
            'show_traffic_limit' => (bool) ($userInfo['show_traffic_limit'] ?? false),
        ];
    }

    /**
     * @param KeyActivate|\stdClass|object|null $key
     * @return array<string, mixed>|null
     */
    private function serializeKeyActivateForClient($key): ?array
    {
        if ($key === null) {
            return null;
        }
        if ($key instanceof KeyActivate) {
            return [
                'id' => (string) $key->id,
                'status' => (int) $key->status,
                'finish_at' => $key->finish_at !== null ? (int) $key->finish_at : null,
                'traffic_limit' => $key->traffic_limit !== null ? (float) $key->traffic_limit : 0.0,
            ];
        }
        if (is_object($key)) {
            return [
                'id' => (string) ($key->id ?? ''),
                'status' => isset($key->status) ? (int) $key->status : 0,
                'finish_at' => isset($key->finish_at) ? (int) $key->finish_at : null,
                'traffic_limit' => isset($key->traffic_limit) ? (float) $key->traffic_limit : 0.0,
            ];
        }

        return null;
    }


//    /**
//     * @param string $key_activate_id
//     * @return Response
//     * @throws GuzzleException
//     */
//    public function show(string $key_activate_id): Response
//    {
//        try {
//            // Получаем запись key_activate_user с отношениями
//            $keyActivateUser = $this->keyActivateUserRepository->findByKeyActivateIdWithRelations($key_activate_id);
//            // Получаем информацию о пользователе сервера
//            $serverUser = $this->serverUserRepository->findById($keyActivateUser->server_user_id);
//
//            if (!$serverUser) {
//                throw new RuntimeException('Server user not found');
//            }
//
//            // Декодируем ключи подключения
//            $connectionKeys = json_decode($serverUser->keys, true);
//
//            if (!$connectionKeys) {
//                throw new RuntimeException('Invalid connection keys format');
//            }
//
//            $userAgent = request()->header('User-Agent') ?? 'Unknown';
//            Log::warning('Incoming request with User-Agent:', ['User-Agent' => $userAgent]);
//
//            // Проверяем User-Agent на наличие клиентов VPN
//            $userAgent = strtolower(request()->header('User-Agent') ?? '');
//            $isVpnClient = str_contains($userAgent, 'v2rayng') || // V2RayNG (Android)
//                str_contains($userAgent, 'nekobox') || // NekoBox (Android)
//                str_contains($userAgent, 'nekoray') || // NekoRay (Windows)
//                str_contains($userAgent, 'singbox') || // Sing-Box (кроссплатформенный)
//                str_contains($userAgent, 'hiddify') || // Hiddify (кроссплатформенный)
//                str_contains($userAgent, 'shadowrocket') || // Shadowrocket (iOS)
//                str_contains($userAgent, 'surge') || // Surge (iOS/macOS)
//                str_contains($userAgent, 'quantumult') || // Quantumult (iOS)
//                str_contains($userAgent, 'quantumult x') || // Quantumult X (iOS)
//                str_contains($userAgent, 'loon') || // Loon (iOS)
//                str_contains($userAgent, 'streisand') || // Streisand (кроссплатформенный)
//                str_contains($userAgent, 'clash') || // Clash (кроссплатформенный)
//                str_contains($userAgent, 'clashx') || // ClashX (macOS)
//                str_contains($userAgent, 'clash for windows') || // Clash for Windows
//                str_contains($userAgent, 'clash.android') || // Clash for Android
//                str_contains($userAgent, 'clash.meta') || // Clash.Meta (кроссплатформенный)
//                str_contains($userAgent, 'v2rayu') || // V2RayU (macOS)
//                str_contains($userAgent, 'v2rayn') || // V2RayN (Windows)
//                str_contains($userAgent, 'v2rayx') || // V2RayX (macOS)
//                str_contains($userAgent, 'qv2ray') || // Qv2ray (кроссплатформенный)
//                str_contains($userAgent, 'trojan') || // Trojan clients (общий)
//                str_contains($userAgent, 'trojan-go') || // Trojan-Go clients
//                str_contains($userAgent, 'wireguard') || // WireGuard clients
//                str_contains($userAgent, 'openvpn') || // OpenVPN clients
//                str_contains($userAgent, 'openconnect') || // OpenConnect clients
//                str_contains($userAgent, 'softether') || // SoftEther VPN clients
//                str_contains($userAgent, 'shadowsocks') || // Shadowsocks clients
//                str_contains($userAgent, 'shadowsocksr') || // ShadowsocksR clients
//                str_contains($userAgent, 'ssr') || // SSR clients
//                str_contains($userAgent, 'outline') || // Outline clients
//                str_contains($userAgent, 'zerotier') || // ZeroTier clients
//                str_contains($userAgent, 'tailscale') || // Tailscale clients
//                str_contains($userAgent, 'windscribe') || // Windscribe clients
//                str_contains($userAgent, 'protonvpn') || // ProtonVPN clients
//                str_contains($userAgent, 'nordvpn') || // NordVPN clients
//                str_contains($userAgent, 'expressvpn') || // ExpressVPN clients
//                str_contains($userAgent, 'pritunl') || // Pritunl clients
//                str_contains($userAgent, 'openwrt') || // OpenWRT (роутеры с VPN)
//                str_contains($userAgent, 'dd-wrt') || // DD-WRT (роутеры с VPN)
//                str_contains($userAgent, 'merlin') || // Asus Merlin (роутеры с VPN)
//                str_contains($userAgent, 'pivpn') || // PiVPN (Raspberry Pi)
//                str_contains($userAgent, 'algo') || // Algo VPN
//                str_contains($userAgent, 'strongswan') || // StrongSwan clients
//                str_contains($userAgent, 'ikev2') || // IKEv2 clients
//                str_contains($userAgent, 'ipsec') || // IPSec clients
//                str_contains($userAgent, 'l2tp') || // L2TP clients
//                str_contains($userAgent, 'pptp') || // PPTP clients
//                str_contains($userAgent, 'v2raytun') || // PPTP clients
//                str_contains($userAgent, 'Happ') || // PPTP clients
//                str_contains($userAgent, 'happ') || // PPTP clients
//                str_contains($userAgent, 'V2Box') || // PPTP clients
//                str_contains($userAgent, 'happproxy') || // Happy Proxy (Android)
//                str_contains($userAgent, 'hexasoftware') || // V2Box (Android)
//                str_contains($userAgent, 'v2box') || // V2Box (Android)
//                str_contains($userAgent, 'v2rayg') || // V2RayG (клиенты)
//                str_contains($userAgent, 'anxray') || // AnXray (Android)
//                str_contains($userAgent, 'kitsunebi') || // Kitsunebi (iOS)
//                str_contains($userAgent, 'potatso') || // Potatso (iOS)
//                str_contains($userAgent, 'rocket') || // Общий для Rocket клиентов
//                str_contains($userAgent, 'pharos') || // Pharos (iOS)
//                str_contains($userAgent, 'stash') || // Stash (iOS)
//                str_contains($userAgent, 'mellow') || // Mellow (клиенты)
//                str_contains($userAgent, 'leaf') || // Leaf (клиенты)
//                str_contains($userAgent, 'hysteria') || // Hysteria (клиенты)
//                str_contains($userAgent, 'tuic') || // TUIC (клиенты)
//                str_contains($userAgent, 'naive') || // NaiveProxy (клиенты)
//                str_contains($userAgent, 'brook') || // Brook (клиенты)
//                str_contains($userAgent, 'vnet') || // VNet (клиенты)
//                str_contains($userAgent, 'http injector') || // HTTP Injector (Android)
//                str_contains($userAgent, 'anonym') || // Анонимайзеры
//                str_contains($userAgent, 'proxy') || // Прокси клиенты
//                str_contains($userAgent, 'vpn') || // Общий для VPN клиентов
//                str_contains($userAgent, 'sub') || // Для подписочных клиентов
//                str_contains($userAgent, 'subscribe'); // Для подписочных клиентов
//
//            if ($isVpnClient || request()->wantsJson()) {
//                Log::warning('ВОТ ЭТО ВЕРНУЛИ:', ['ВОТ ЭТО ВЕРНУЛИ' => response(implode("\n", $connectionKeys))
//                    ->header('Content-Type', 'text/plain')]);
//                // Для VPN клиентов возвращаем строку с конфигурациями
//                return response(implode("\n", $connectionKeys))
//                    ->header('Content-Type', 'text/plain');
//            }
//
//            $panel_strategy = new PanelStrategy($serverUser->panel->panel);
//            // Для браузера показываем HTML страницу
//            $userInfo = [
//                'username' => $serverUser->id,
//                'status' => $info['status'],
//                'data_limit' => $info['data_limit'],
//                'data_limit_tariff' => $keyActivateUser->keyActivate->traffic_limit ?? 0,
//                'data_used' => $info['used_traffic'],
//                'expiration_date' => $keyActivateUser->keyActivate->finish_at ?? null,
//                'days_remaining' => $keyActivateUser->keyActivate->finish_at ? ceil(($keyActivateUser->keyActivate->finish_at - time()) / 86400) : null
//            ];
//
//            // Форматируем ключи для отображения
//            $formattedKeys = $this->formatConnectionKeys($connectionKeys);
//
//            // Добавляем ссылку на бота
//            $botLink = $keyActivateUser->keyActivate->packSalesman->salesman->bot_link ?? '#';
//
//            return response()->view('vpn.config', compact('userInfo', 'formattedKeys', 'botLink'));
//        } catch (Exception $e) {
//            Log::error('Error showing VPN config', [
//                'key_activate_id' => $key_activate_id,
//                'error' => $e->getMessage()
//            ]);
//
//            if (request()->wantsJson()) {
//                return response()->json([
//                    'status' => 'error',
//                    'message' => 'Configuration not found'
//                ], 404);
//            }
//
//            return response()->view('vpn.error', [
//                'message' => 'Не удалось загрузить конфигурацию VPN. Пожалуйста, проверьте правильность ссылки.'
//            ]);
//        }
//    }

    /**
     * Преобразует короткий код эмодзи локации (:nl:, :ru:) в символ флага (🇳🇱, 🇷🇺).
     * Если в БД уже записан Unicode-флаг — возвращает как есть.
     *
     * @param string $emoji Значение из location.emoji (например :nl: или 🇳🇱)
     * @return string
     */
    private function locationEmojiToFlag(string $emoji): string
    {
        $emoji = trim($emoji);
        // Уже символ флага (два regional indicator) — не трогаем
        if (mb_strlen($emoji) >= 2 && preg_match('/[\x{1F1E6}-\x{1F1FF}]/u', $emoji)) {
            return $emoji;
        }
        // Формат :xx: — две буквы кода страны → Unicode флаг (regional indicators)
        if (preg_match('/^:([a-z]{2}):$/i', $emoji, $m)) {
            $code = strtoupper($m[1]);
            if (function_exists('mb_chr')) {
                $c1 = $code[0];
                $c2 = $code[1];
                if ($c1 >= 'A' && $c1 <= 'Z' && $c2 >= 'A' && $c2 <= 'Z') {
                    return mb_chr(0x1F1E6 + ord($c1) - 65) . mb_chr(0x1F1E6 + ord($c2) - 65);
                }
            }
            // Fallback: известные коды из вашей БД
            $flags = ['NL' => "\xF0\x9F\x87\xB3\xF0\x9F\x87\xB1", 'RU' => "\xF0\x9F\x87\xB7\xF0\x9F\x87\xBA"];
            if (isset($flags[$code])) {
                return $flags[$code];
            }
        }
        return $emoji;
    }

    /**
     * Полное название локации по коду (NL → Нидерланды, RU → Россия).
     *
     * @param string $code location.code из БД
     * @return string Полное название или пустая строка, если код неизвестен
     */
    private function locationCodeToFullName(string $code): string
    {
        $code = strtoupper(trim($code));
        $names = [
            'NL' => 'Нидерланды',
            'RU' => 'Россия',
            'DE' => 'Германия',
            'US' => 'США',
            'FR' => 'Франция',
            'GB' => 'Великобритания',
            'FI' => 'Финляндия',
            'SG' => 'Сингапур',
        ];
        return $names[$code] ?? '';
    }

    /**
     * Нормализация названия локации для заголовка раздела (исправление опечаток).
     *
     * @param string $name Название из location или сервера
     * @return string
     */
    private function normalizeLocationLabelName(string $name): string
    {
        $typos = [
            'Финлядния' => 'Финляндия',
        ];
        return $typos[$name] ?? $name;
    }

    /**
     * Добавить флаг (emoji) к подписи локации, если он задан в БД.
     * Поддерживает формат :xx: (код страны) → Unicode-флаг и уже готовые emoji.
     */
    private function locationLabelWithEmoji(?\App\Models\Location\Location $location, string $label): string
    {
        if (!$location || empty(trim((string) $location->emoji))) {
            return $label;
        }
        $emoji = $this->locationEmojiToUnicode(trim($location->emoji));
        return $emoji !== '' ? $emoji . ' ' . $label : $label;
    }

    /**
     * Преобразовать emoji локации в Unicode-флаг для отображения.
     * :nl: → 🇳🇱, :fi: → 🇫🇮; если уже флаг или другой emoji — вернуть как есть.
     */
    private function locationEmojiToUnicode(string $emoji): string
    {
        if (preg_match('/^:([a-z]{2}):$/i', $emoji, $m)) {
            $code = strtoupper($m[1]);
            // Regional indicator symbols: A = U+1F1E6, B = U+1F1E7, ... Z = U+1F1FF
            $a = ord('A');
            $base = 0x1F1E6;
            $c1 = mb_chr($base + (ord($code[0]) - $a));
            $c2 = mb_chr($base + (ord($code[1]) - $a));
            return $c1 . $c2;
        }
        return $emoji;
    }

    /**
     * Заменить подпись (fragment после #) в ссылке протокола.
     * Эта подпись отображается в VPN-клиенте (v2rayNG, Nekoray и т.д.) как название конфигурации.
     *
     * @param string $link Ссылка (vless://..., vmess://... и т.д.)
     * @param string $remark Новая подпись, например "Финляндия #1 · VLESS TCP"
     * @return string Ссылка с обновлённым fragment (для вставки в HTML по-прежнему с addslashes)
     */
    private function setLinkRemark(string $link, string $remark): string
    {
        $link = stripslashes((string) $link);
        if ($link === '') {
            return addslashes($link);
        }
        $remark = trim((string) $remark);
        $hashPos = strpos($link, '#');
        $base = $hashPos !== false ? substr($link, 0, $hashPos) : $link;
        $newLink = $remark !== '' ? $base . '#' . rawurlencode($remark) : $base;
        return addslashes($newLink);
    }

    /**
     * Format connection keys for display
     *
     * @param array $connectionKeys Массив сырых ссылок
     * @param string|null $locationLabel Подпись локации для отображения в клиенте (например "Финляндия #1"). Если задана, подменяет стандартную подпись Marz (uuid) на понятную.
     * @return array
     */
    private function formatConnectionKeys(array $connectionKeys, ?string $locationLabel = null): array
    {
        $protocolDescriptions = [
            'vless' => [
                'name' => 'VLESS',
                'icon' => 'V'
            ],
            'vmess' => [
                'name' => 'VMess',
                'icon' => 'VM'
            ],
            'trojan' => [
                'name' => 'Trojan',
                'icon' => 'T'
            ],
            'shadowsocks' => [
                'name' => 'Shadowsocks',
                'icon' => 'SS'
            ]
        ];

        $formattedKeys = [];
        foreach ($connectionKeys as $configString) {
            // Удаляем экранирование слешей
            $configString = stripslashes($configString);

            if (preg_match('/^(vless|vmess|trojan|ss):\/\//', $configString, $matches)) {
                $protocol = $matches[1];
                if ($protocol === 'ss') {
                    $protocol = 'shadowsocks';
                }

                $protocolInfo = $protocolDescriptions[strtolower($protocol)] ?? [
                    'name' => strtoupper($protocol),
                    'icon' => substr(strtoupper($protocol), 0, 1)
                ];

                // Извлекаем тип подключения из комментария (например [VLESS - tcp] -> "tcp")
                preg_match('/\[(.*?)\]$/', $configString, $typeMatches);
                $connectionType = $typeMatches[1] ?? '';

                $link = addslashes($configString);
                if ($locationLabel !== null && $locationLabel !== '') {
                    $remark = $locationLabel . ' · ' . $protocolInfo['name'] . ($connectionType !== '' ? ' ' . $connectionType : '');
                    $link = $this->setLinkRemark($configString, $remark);
                }

                $formattedKeys[] = [
                    'protocol' => $protocolInfo['name'],
                    'icon' => $protocolInfo['icon'],
                    'link' => $link,
                    'connection_type' => $connectionType
                ];
            }
        }

        return $formattedKeys;
    }

    /**
     * Ссылка на бота для страницы конфига и Profile-Title:
     * — ключ куплен через веб-модуль: есть module_salesman_id → показываем бота модуля (BotModule.username / t.me);
     * — иначе → бот продаж/активации (pack_salesman.salesman.bot_link).
     *
     * Не используем признак pack.module_key: пакет может быть «модульным», но ключ продан через бота продавца.
     */
    private function resolveConfigDisplayBotLink(KeyActivate $keyActivate): string
    {
        $keyActivate->loadMissing([
            'packSalesman.pack',
            'packSalesman.salesman',
            'moduleSalesman.botModule',
        ]);

        if ($keyActivate->module_salesman_id) {
            $moduleSalesman = $keyActivate->moduleSalesman;
            if ($moduleSalesman !== null) {
                $botModule = $moduleSalesman->botModule;
                if ($botModule !== null) {
                    $fromModule = $this->botModulePublicTelegramLink($botModule);
                    if ($fromModule !== null) {
                        return $fromModule;
                    }
                }
                $link = trim((string) ($moduleSalesman->bot_link ?? ''));
                if ($link !== '' && $link !== '#') {
                    return $link;
                }
            }

            return '#';
        }

        $packSalesman = $keyActivate->packSalesman;
        if ($packSalesman !== null && $packSalesman->salesman !== null) {
            return (string) ($packSalesman->salesman->bot_link ?? '#');
        }

        return '#';
    }

    /**
     * Ссылка t.me на бота веб-модуля: сначала bot_module.username, иначе BOT-T getBot по public_key (кэш + запись в БД).
     */
    private function botModulePublicTelegramLink(BotModule $botModule): ?string
    {
        $username = trim((string) ($botModule->username ?? ''));
        $username = ltrim($username, '@');
        if ($username !== '') {
            return 'https://t.me/' . $username;
        }

        $publicKey = trim((string) ($botModule->public_key ?? ''));
        if ($publicKey === '') {
            return null;
        }

        $cacheKey = 'bot_module_tg_name:' . $botModule->id;
        $resolved = Cache::remember($cacheKey, 21600, function () use ($botModule, $publicKey) {
            try {
                $info = BottApi::getBot($publicKey);
                if (! empty($info['result']) && ! empty($info['data']['name'])) {
                    $name = ltrim((string) $info['data']['name'], '@');
                    if ($name !== '') {
                        try {
                            BotModule::where('id', $botModule->id)->update(['username' => $name]);
                        } catch (\Throwable $e) {
                            Log::debug('bot_module username cache persist skipped', [
                                'bot_module_id' => $botModule->id,
                                'message' => $e->getMessage(),
                            ]);
                        }

                        return $name;
                    }
                }
            } catch (\Throwable $e) {
                Log::debug('botModulePublicTelegramLink getBot failed', [
                    'bot_module_id' => $botModule->id,
                    'message' => $e->getMessage(),
                ]);
            }

            return '';
        });

        if ($resolved === '') {
            return null;
        }

        return 'https://t.me/' . $resolved;
    }

    /**
     * Заголовок для VPN-клиентов (Profile-Title): @bot until DD.MM.YYYY (key_id), только ASCII.
     */
    private function buildSubscriptionProfileTitle(KeyActivate $keyActivate, string $keyId): string
    {
        $rawBotLink = $this->resolveConfigDisplayBotLink($keyActivate);
        $botAt = $this->botLinkToAtUsername($rawBotLink);
        // Только ASCII: многие VPN-клиенты криво показывают UTF-8 в Profile-Title.
        $until = 'n/a';
        $finishAt = $keyActivate->finish_at ?? null;
        if ($finishAt !== null && (int) $finishAt > 0) {
            $until = \Carbon\Carbon::createFromTimestamp((int) $finishAt)->format('d.m.Y');
        }

        return sprintf('%s until %s (%s)', $botAt, $until, $keyId);
    }

    /**
     * Из bot_link (https://t.me/...) в строку вида @@handle для отображения в клиентах.
     */
    private function botLinkToAtUsername(string $botLink): string
    {
        $botLink = trim($botLink);
        if ($botLink === '' || $botLink === '#') {
            return '@bot';
        }
        if (strpos($botLink, '@') === 0) {
            return $botLink;
        }
        $host = parse_url($botLink, PHP_URL_HOST);
        $path = parse_url($botLink, PHP_URL_PATH);
        if ($host && (strpos($host, 't.me') !== false || strpos($host, 'telegram.me') !== false)) {
            $name = $path ? ltrim((string) $path, '/') : '';
            $name = preg_replace('/[?#].*$/', '', $name);
            $name = explode('/', $name)[0] ?? '';
            if ($name !== '') {
                return '@' . ltrim($name, '@');
            }
        }
        if ($path && $path !== '/') {
            $name = ltrim((string) $path, '/');
            $name = explode('/', $name)[0] ?? '';
            if ($name !== '') {
                return '@' . ltrim($name, '@');
            }
        }

        return '@bot';
    }
}
