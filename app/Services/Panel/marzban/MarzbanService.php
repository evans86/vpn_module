<?php

namespace App\Services\Panel\marzban;

use App\Dto\Bot\BotModuleFactory;
use App\Dto\Server\ServerDto;
use App\Dto\Server\ServerFactory;
use App\Models\Bot\BotModule;
use App\Models\KeyActivate\KeyActivate;
use App\Models\KeyActivateUser\KeyActivateUser;
use App\Models\PackSalesman\PackSalesman;
use App\Models\Panel\Panel;
use App\Models\Salesman\Salesman;
use App\Models\Server\Server;
use App\Models\ServerMonitoring\ServerMonitoring;
use App\Models\ServerUser\ServerUser;
use App\Services\External\BottApi;
use App\Services\External\MarzbanAPI;
use App\Services\Key\KeyActivateUserService;
use Carbon\Carbon;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Exception;
use phpseclib3\Net\SSH2;
use RuntimeException;
use Telegram\Bot\Api;

class MarzbanService
{
    /**
     * Путь к файлу конфигурации панели на сервере
     */
    private const PANEL_ENV_PATH = '/opt/marzban/.env';

    /**
     * Путь к скрипту установки
     */
    private const INSTALL_SCRIPT_URL = 'https://raw.githubusercontent.com/mozaroc/bash-hooks/main/install_marzban.sh';

    /**
     * Создание панели на сервере
     *
     * @param int $server_id
     * @return void
     * @throws Exception
     */
    public function create(int $server_id): void
    {
        try {
            Log::info('Starting panel creation', ['server_id' => $server_id, 'source' => 'panel']);
            /**
             * @var Server $server
             */
            $server = Server::query()->where('id', $server_id)->firstOrFail();


            // Проверяем статус сервера
            if (!$this->checkServerStatus($server)) {
                throw new RuntimeException('Server is not ready for panel installation');
            }

            $ssh_connect = $this->connectSshAdapter(ServerFactory::fromEntity($server));

            // Установка панели
            $this->installPanel($ssh_connect, $server->host);

            // Проверка установки и создание панели
            $this->verifyAndCreatePanel($ssh_connect, $server);

            Log::info('Panel created successfully', ['server_id' => $server_id, 'source' => 'panel']);
        } catch (Exception $e) {
            Log::critical('Failed to create panel - critical infrastructure failure', [
                'server_id' => $server_id,
                'error' => $e->getMessage(),
                'source' => 'panel',
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Проверка статуса сервера
     *
     * @param Server $server
     * @return bool
     */
    private function checkServerStatus(Server $server): bool
    {
        // TODO: Добавить проверку статуса сервера через провайдера
        return $server->server_status == Server::SERVER_CONFIGURED;
    }

    /**
     * Установка панели на сервер
     *
     * @param SSH2 $ssh
     * @param string $host
     * @return void
     * @throws RuntimeException
     */
    private function installPanel(SSH2 $ssh, string $host): void
    {
        try {
            Log::info('Installing panel', ['host' => $host, 'source' => 'panel']);

            // Проверка и запуск Docker daemon
            $this->ensureDockerRunning($ssh);

            // Команда 1: Скачивание скрипта
            Log::info('Downloading installation script', [
                'url' => self::INSTALL_SCRIPT_URL,
                'source' => 'panel'
            ]);

            $wgetOutput = $ssh->exec('wget -O install_marzban.sh ' . self::INSTALL_SCRIPT_URL . ' 2>&1');
            $wgetExitStatus = $ssh->getExitStatus();

            Log::info('Wget command executed', [
                'exit_status' => $wgetExitStatus,
                'output' => substr($wgetOutput, 0, 500), // Первые 500 символов
                'source' => 'panel'
            ]);

            if ($wgetExitStatus !== 0) {
                throw new RuntimeException("Failed to download installation script. Exit code: {$wgetExitStatus}. Output: " . substr($wgetOutput, 0, 1000));
            }

            // Команда 2: Установка прав на выполнение
            Log::info('Setting execute permissions', ['source' => 'panel']);

            $chmodOutput = $ssh->exec('chmod +x install_marzban.sh 2>&1');
            $chmodExitStatus = $ssh->getExitStatus();

            Log::info('Chmod command executed', [
                'exit_status' => $chmodExitStatus,
                'output' => substr($chmodOutput, 0, 500),
                'source' => 'panel'
            ]);

            if ($chmodExitStatus !== 0) {
                throw new RuntimeException("Failed to set execute permissions. Exit code: {$chmodExitStatus}. Output: " . substr($chmodOutput, 0, 1000));
            }

            // Команда 3: Запуск скрипта установки
            Log::info('Running installation script', [
                'host' => $host,
                'source' => 'panel'
            ]);

            // Увеличиваем таймаут для длительной операции установки
            $ssh->setTimeout(600); // 10 минут

            $installOutput = $ssh->exec('./install_marzban.sh ' . escapeshellarg($host) . ' 2>&1');
            $installExitStatus = $ssh->getExitStatus();

            // Восстанавливаем таймаут
            $ssh->setTimeout(100000);

            // Логируем полный вывод (может быть длинным)
            Log::info('Installation script executed', [
                'exit_status' => $installExitStatus,
                'output_length' => strlen($installOutput),
                'output_preview' => substr($installOutput, 0, 2000), // Первые 2000 символов
                'output_end' => substr($installOutput, -1000), // Последние 1000 символов
                'source' => 'panel'
            ]);

            if ($installExitStatus !== 0) {
                // Проверяем, связана ли ошибка с Docker
                if (strpos($installOutput, 'Cannot connect to the Docker daemon') !== false ||
                    strpos($installOutput, 'docker daemon running') !== false) {
                    Log::error('Docker daemon issue detected during installation', [
                        'host' => $host,
                        'source' => 'panel'
                    ]);

                    // Пытаемся запустить Docker и повторить
                    $this->ensureDockerRunning($ssh);

                    // Повторяем установку
                    Log::info('Retrying installation after Docker restart', [
                        'host' => $host,
                        'source' => 'panel'
                    ]);

                    $installOutput = $ssh->exec('./install_marzban.sh ' . escapeshellarg($host) . ' 2>&1');
                    $installExitStatus = $ssh->getExitStatus();

                    if ($installExitStatus !== 0) {
                        throw new RuntimeException(
                            "Installation script failed after Docker restart. Exit code: {$installExitStatus}. " .
                            "Output (last 2000 chars): " . substr($installOutput, -2000)
                        );
                    }
                } else {
                    // Пытаемся получить более подробную информацию об ошибке
                    $errorDetails = $ssh->exec('tail -n 50 /var/log/marzban-install.log 2>&1 || echo "Log file not found"');

                    throw new RuntimeException(
                        "Installation script failed. Exit code: {$installExitStatus}. " .
                        "Output (last 2000 chars): " . substr($installOutput, -2000) . ". " .
                        "Install log: " . substr($errorDetails, 0, 1000)
                    );
                }
            }

            Log::info('Panel installation completed successfully', [
                'host' => $host,
                'source' => 'panel'
            ]);

        } catch (Exception $e) {
            Log::error('Panel installation failed', [
                'host' => $host,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'source' => 'panel'
            ]);
            throw new RuntimeException('Failed to install panel: ' . $e->getMessage());
        }
    }

    /**
     * Проверка и запуск Docker daemon
     *
     * @param SSH2 $ssh
     * @return void
     * @throws RuntimeException
     */
    private function ensureDockerRunning(SSH2 $ssh): void
    {
        try {
            Log::info('Checking Docker daemon status', ['source' => 'panel']);

            // Проверяем, запущен ли Docker daemon
            $dockerCheck = $ssh->exec('docker info > /dev/null 2>&1; echo $?');
            $dockerCheck = trim($dockerCheck);

            if ($dockerCheck === '0') {
                Log::info('Docker daemon is running', ['source' => 'panel']);
                return;
            }

            Log::warning('Docker daemon is not running, attempting to start', ['source' => 'panel']);

            // Пытаемся запустить Docker daemon
            $startDockerOutput = $ssh->exec('sudo systemctl start docker 2>&1');
            $startDockerStatus = $ssh->getExitStatus();

            Log::info('Docker start command executed', [
                'exit_status' => $startDockerStatus,
                'output' => substr($startDockerOutput, 0, 500),
                'source' => 'panel'
            ]);

            // Ждем немного, чтобы Docker запустился
            sleep(3);

            // Проверяем снова
            $dockerCheck = $ssh->exec('docker info > /dev/null 2>&1; echo $?');
            $dockerCheck = trim($dockerCheck);

            if ($dockerCheck !== '0') {
                // Пытаемся включить автозапуск и запустить
                $enableDockerOutput = $ssh->exec('sudo systemctl enable docker && sudo systemctl start docker 2>&1');
                $enableDockerStatus = $ssh->getExitStatus();

                Log::info('Docker enable and start command executed', [
                    'exit_status' => $enableDockerStatus,
                    'output' => substr($enableDockerOutput, 0, 500),
                    'source' => 'panel'
                ]);

                // Ждем еще немного
                sleep(3);

                // Финальная проверка
                $dockerCheck = $ssh->exec('docker info > /dev/null 2>&1; echo $?');
                $dockerCheck = trim($dockerCheck);

                if ($dockerCheck !== '0') {
                    throw new RuntimeException(
                        "Docker daemon is not running and could not be started. " .
                        "Please ensure Docker is installed and the user has sudo privileges. " .
                        "Start output: " . substr($startDockerOutput, 0, 500) . ". " .
                        "Enable output: " . substr($enableDockerOutput, 0, 500)
                    );
                }
            }

            Log::info('Docker daemon is now running', ['source' => 'panel']);

        } catch (Exception $e) {
            Log::error('Failed to ensure Docker is running', [
                'error' => $e->getMessage(),
                'source' => 'panel'
            ]);
            throw new RuntimeException('Docker daemon is required but not available: ' . $e->getMessage());
        }
    }

    /**
     * Проверка установки и создание панели в БД
     *
     * @param SSH2 $ssh
     * @param Server $server
     * @return void
     * @throws RuntimeException
     */
    private function verifyAndCreatePanel(SSH2 $ssh, Server $server): void
    {
        Log::info('Verifying panel installation', ['server_id' => $server->id, 'source' => 'panel']);

        if (str_contains($ssh->exec('stat ' . self::PANEL_ENV_PATH), 'No such file')) {
            throw new RuntimeException('Panel configuration file not found');
        }

        $envContent = $ssh->exec('cat ' . self::PANEL_ENV_PATH);
        $config = $this->parseEnvFile($envContent);

        if (empty($config['SUDO_USERNAME']) || empty($config['SUDO_PASSWORD'])) {
            throw new RuntimeException('Invalid panel configuration');
        }

        $panel = new Panel();
        $panel->server_id = $server->id;
        $panel->panel = Panel::MARZBAN;
        $panel->panel_status = Panel::PANEL_CREATED;
        $panel->panel_adress = $config['XRAY_SUBSCRIPTION_URL_PREFIX'] . '/dashboard';
        $panel->panel_login = $config['SUDO_USERNAME'];
        $panel->panel_password = $config['SUDO_PASSWORD'];
        $panel->save();

        Log::info('Panel record created', ['panel_id' => $panel->id, 'source' => 'panel']);
    }

    /**
     * Парсинг файла конфигурации панели
     *
     * @param string $content
     * @return array
     */
    private function parseEnvFile(string $content): array
    {
        $lines = explode("\n", $content);
        $config = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || str_starts_with($line, '#')) {
                continue;
            }

            $parts = explode('=', $line, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $key = trim($parts[0]);
            $value = trim(str_replace(['"', "'"], '', $parts[1]));
            $config[$key] = $value;
        }

        return $config;
    }

    /**
     * Подключение к серверу по SSH
     *
     * @param ServerDto $serverDto
     * @return SSH2
     * @throws RuntimeException
     */
    public function connectSshAdapter(ServerDto $serverDto): SSH2
    {
        try {
            $ssh = new SSH2($serverDto->ip);
            $ssh->setTimeout(100000);

            if (!$ssh->login($serverDto->login, $serverDto->password)) {
                throw new RuntimeException('SSH authentication failed');
            }

            return $ssh;
        } catch (Exception $e) {
            Log::error('SSH connection failed', [
                'source' => 'panel',
                'ip' => $serverDto->ip,
                'error' => $e->getMessage()
            ]);
            throw new RuntimeException('SSH connection failed: ' . $e->getMessage());
        }
    }

    /**
     * Обновление токена доступа панели
     *
     * @param int $panel_id
     * @return Panel
     * @throws GuzzleException
     * @throws Exception
     */
    public function updateMarzbanToken(int $panel_id): Panel
    {
        Log::info('Updating panel token', ['panel_id' => $panel_id, 'source' => 'panel']);

        try {
            /**
             * @var Panel $panel
             */
            $panel = Panel::query()->where('id', $panel_id)->firstOrFail();

            if (is_null($panel->auth_token) || $panel->token_died_time <= time()) {
                $marzbanApi = new MarzbanAPI($panel->api_address);
                $panel->auth_token = $marzbanApi->getToken($panel->panel_login, $panel->panel_password);
                $panel->token_died_time = time() + \App\Constants\TimeConstants::PANEL_TOKEN_LIFETIME;
                $panel->save();

                Log::info('Panel token updated', ['panel_id' => $panel_id, 'source' => 'panel']);
            }

            return $panel;
        } catch (Exception $e) {
            Log::error('Failed to update panel token', [
                'source' => 'panel',
                'panel_id' => $panel_id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * @throws GuzzleException
     * @throws Exception
     */
    public function getUserSubscribeInfo(int $panel_id, string $user_id): array
    {
        try {
            /**
             * @var ServerUser $serverUser
             */
            $serverUser = ServerUser::query()->where('id', $user_id)->firstOrFail();
            $panel = $this->updateMarzbanToken($panel_id);
            $marzbanApi = new MarzbanAPI($panel->api_address);
            $userData = $marzbanApi->getUser($panel->auth_token, $user_id);

            Log::info('📊 Marzban API вернул данные пользователя', [
                'user_id' => $user_id,
                'panel_id' => $panel_id,
                'status' => $userData['status'] ?? 'unknown',
                'expire' => $userData['expire'] ?? null,
                'expire_date' => isset($userData['expire']) && $userData['expire'] > 0
                    ? date('Y-m-d H:i:s', $userData['expire'])
                    : 'не установлено',
                'used_traffic_gb' => isset($userData['used_traffic']) ? round($userData['used_traffic'] / (1024*1024*1024), 2) : 0,
                'data_limit_gb' => isset($userData['data_limit']) ? round($userData['data_limit'] / (1024*1024*1024), 2) : 0,
                'source' => 'panel'
            ]);

            $info = [
                'used_traffic' => $userData['used_traffic'] ?? 0,
                'data_limit' => $userData['data_limit'] ?? 0,
                'expire' => $userData['expire'] ?? null,
                'status' => $userData['status'] ?? 'unknown',
                'key_status_updated' => false, // Флаг что статус ключа был обновлен
            ];

            // Проверяем есть ли связь с ключом активации
            if (!$serverUser->keyActivateUser || !$serverUser->keyActivateUser->keyActivate) {
                Log::warning('⚠️  ServerUser не имеет связи с KeyActivate', [
                    'server_user_id' => $user_id,
                    'panel_id' => $panel_id,
                    'source' => 'panel'
                ]);
                return $info;
            }

            $keyActivate = $serverUser->keyActivateUser->keyActivate;
            $currentTime = time();

            // КРИТИЧЕСКИ ВАЖНО: Проверяем finish_at из БД ПЕРЕД проверкой expire из Marzban
            // finish_at - это источник истины для нашего приложения
            // Если finish_at еще не истек, НЕ деактивируем ключ, даже если Marzban вернул истекший expire
            $finishAtFromDb = $keyActivate->finish_at;

            // Проверяем реальное истечение срока по timestamp из Marzban
            // Статус может быть 'limited' (превышен трафик) или 'disabled' (временно отключен)
            // но это не значит что ключ просрочен по времени!
            if (isset($userData['expire']) && $userData['expire'] > 0) {
                $expireTime = $userData['expire'];

                // КРИТИЧЕСКАЯ ПРОВЕРКА: Если expire из Marzban в миллисекундах, конвертируем в секунды
                // Marzban может возвращать expire в миллисекундах (если > 2147483647, то это миллисекунды)
                if ($expireTime > 2147483647) {
                    $expireTime = intval($expireTime / 1000);
                    Log::warning('⚠️  Marzban вернул expire в миллисекундах, конвертировали в секунды', [
                        'key_id' => $keyActivate->id,
                        'original_expire' => $userData['expire'],
                        'converted_expire' => $expireTime,
                        'source' => 'panel'
                    ]);
                }

                // Ставим EXPIRED только если:
                // 1. Срок по Marzban ДЕЙСТВИТЕЛЬНО истек (currentTime > expireTime)
                // 2. Срок по БД ТОЖЕ истек (finish_at истек или не установлен)
                // 3. Статус ключа был ACTIVE
                if ($currentTime > $expireTime) {
                    // ПРОВЕРЯЕМ finish_at из БД - это источник истины!
                    $dbExpired = !$finishAtFromDb || ($finishAtFromDb > 0 && $currentTime > $finishAtFromDb);

                    if (!$dbExpired) {
                        // finish_at из БД еще не истек - НЕ деактивируем ключ!
                        Log::warning('🚫 ПРЕДОТВРАЩЕНА преждевременная деактивация ключа!', [
                            'key_id' => $keyActivate->id,
                            'user_id' => $user_id,
                            'panel_id' => $panel_id,
                            'marzban_expire' => $expireTime,
                            'marzban_expire_date' => date('Y-m-d H:i:s', $expireTime),
                            'db_finish_at' => $finishAtFromDb,
                            'db_finish_at_date' => $finishAtFromDb ? date('Y-m-d H:i:s', $finishAtFromDb) : null,
                            'current_time' => $currentTime,
                            'current_date' => date('Y-m-d H:i:s', $currentTime),
                            'days_remaining_in_db' => $finishAtFromDb ? ceil(($finishAtFromDb - $currentTime) / 86400) : null,
                            'reason' => 'finish_at из БД еще не истек, хотя Marzban вернул истекший expire',
                            'source' => 'panel'
                        ]);
                    } elseif ($keyActivate->status === KeyActivate::ACTIVE) {
                        // И Marzban, и БД показывают истечение - деактивируем
                        $oldStatus = $keyActivate->status;
                        $keyActivate->status = KeyActivate::EXPIRED;
                        $keyActivate->save();

                        $info['key_status_updated'] = true; // Отмечаем что статус был обновлен

                        $daysOverdue = round(($currentTime - $expireTime) / 86400, 1);
                        $dbDaysOverdue = $finishAtFromDb ? round(($currentTime - $finishAtFromDb) / 86400, 1) : null;

                        // Загружаем связь если не загружена
                        if (!$keyActivate->relationLoaded('keyActivateUser')) {
                            $keyActivate->load('keyActivateUser');
                        }

                        Log::critical("🚫 [KEY: {$keyActivate->id}] СТАТУС КЛЮЧА ИЗМЕНЕН НА EXPIRED (срок истек по данным Marzban И БД) | KEY_ID: {$keyActivate->id} | {$keyActivate->id}", [
                            'source' => 'panel',
                            'action' => 'update_status_to_expired',
                            'key_id' => $keyActivate->id,
                            'search_key' => $keyActivate->id, // Для быстрого поиска
                            'search_tag' => 'KEY_EXPIRED',
                            'user_id' => $user_id,
                            'panel_id' => $panel_id,
                            'old_status' => $oldStatus,
                            'old_status_text' => 'ACTIVE (Активирован)',
                            'new_status' => KeyActivate::EXPIRED,
                            'new_status_text' => 'EXPIRED (Просрочен)',
                            'reason' => 'Срок истек по данным Marzban API И по finish_at из БД',
                            'marzban_expire' => $expireTime,
                            'marzban_expire_date' => date('Y-m-d H:i:s', $expireTime),
                            'marzban_days_overdue' => $daysOverdue,
                            'db_finish_at' => $finishAtFromDb,
                            'db_finish_at_date' => $finishAtFromDb ? date('Y-m-d H:i:s', $finishAtFromDb) : null,
                            'db_days_overdue' => $dbDaysOverdue,
                            'current_time' => $currentTime,
                            'current_date' => date('Y-m-d H:i:s', $currentTime),
                            'user_tg_id' => $keyActivate->user_tg_id,
                            'pack_salesman_id' => $keyActivate->pack_salesman_id,
                            'module_salesman_id' => $keyActivate->module_salesman_id,
                            'traffic_limit' => $keyActivate->traffic_limit,
                            'server_user_id' => $serverUser->id,
                            'has_key_activate_user' => $keyActivate->keyActivateUser ? true : false,
                            'key_activate_user_id' => $keyActivate->keyActivateUser ? $keyActivate->keyActivateUser->id : null,
                            'key_activate_user_server_user_id' => ($keyActivate->keyActivateUser && $keyActivate->keyActivateUser->serverUser) ? $keyActivate->keyActivateUser->serverUser->id : null,
                            'key_created_at' => $keyActivate->created_at ? $keyActivate->created_at->format('Y-m-d H:i:s') : null,
                            'key_updated_at' => $keyActivate->updated_at ? $keyActivate->updated_at->format('Y-m-d H:i:s') : null,
                            'warning' => '⚠️ ВАЖНО: При смене статуса на EXPIRED связь keyActivateUser НЕ должна удаляться!',
                            'method' => 'getUserSubscribeInfo',
                            'file' => __FILE__,
                            'line' => __LINE__
                        ]);
                    } else {
                        Log::debug('ℹ️  Ключ уже имеет статус отличный от ACTIVE, пропускаем обновление', [
                            'key_id' => $keyActivate->id,
                            'current_status' => $keyActivate->status,
                            'expire_time' => $expireTime,
                            'source' => 'panel'
                        ]);
                    }
                } else {
                    Log::debug('⏰ Срок действия ключа еще не истек (по данным Marzban)', [
                        'key_id' => $keyActivate->id,
                        'expire_time' => $expireTime,
                        'expire_date' => date('Y-m-d H:i:s', $expireTime),
                        'current_time' => $currentTime,
                        'days_remaining' => ceil(($expireTime - $currentTime) / 86400),
                        'source' => 'panel'
                        ]);
                }
            } else {
                Log::warning('⚠️  Marzban не вернул expire timestamp или он равен 0', [
                    'key_id' => $keyActivate->id ?? 'unknown',
                    'user_id' => $user_id,
                    'expire' => $userData['expire'] ?? 'not set',
                    'db_finish_at' => $finishAtFromDb,
                    'source' => 'panel'
                ]);
            }

            return $info;
        } catch (Exception $e) {
            Log::error('Failed to check user status', [
                'panel_id' => $panel_id,
                'user_id' => $user_id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * @throws GuzzleException
     * @throws Exception
     */
    public function getServerStats(): void
    {
        try {
            $panels = Panel::query()->where('panel_status', Panel::PANEL_CONFIGURED)->get();

            $panels->each(function ($panel) {
                // Обработка каждой панели
                $panel = $this->updateMarzbanToken($panel->id);
                $marzbanApi = new MarzbanAPI($panel->api_address);
                $serverStats = $marzbanApi->getServerStats($panel->auth_token);
                $statistics = json_encode($serverStats);
                ServerMonitoring::create([
                    'panel_id' => $panel->id,
                    'statistics' => $statistics
                ]);
            });

            self::cleanOldStatistics();

        } catch (Exception $e) {
            Log::error('Failed to check user status', [
                'error' => $e->getMessage(),
                'source' => 'panel'
            ]);
            throw $e;
        }
    }

    /**
     * @throws Exception
     */
    public function cleanOldStatistics(): void
    {
        try {
            // Вычисляем дату, которая была неделю назад
            $oneWeekAgo = Carbon::now()->subWeek();

            Log::info('Starting cleanup of records older than one week.', [
                'cleanup_date' => $oneWeekAgo,
                'source' => 'panel'
            ]);

            // Удаляем все записи старше недели
            ServerMonitoring::where('created_at', '<', $oneWeekAgo)
                ->chunkById(100, function ($records) {
                    foreach ($records as $record) {
                        $record->delete();
                    }
                });

            Log::info('Cleanup completed.', [
                'cleanup_date' => $oneWeekAgo,
                'source' => 'panel'
            ]);
        } catch (Exception $e) {
            // Логируем ошибку, если что-то пошло не так
            Log::error('Failed to clean old records.', [
                'error' => $e->getMessage(),
                'source' => 'panel'
            ]);
            throw $e;
        }
    }

    /**
     * Проверка онлайн статуса пользователя
     *
     * @param int $panel_id
     * @param string $user_id
     * @return array
     * @throws GuzzleException
     * @throws Exception
     */
    public function checkOnline(int $panel_id, string $user_id): array
    {
        try {
            $panel = $this->updateMarzbanToken($panel_id);
            $marzbanApi = new MarzbanAPI($panel->api_address);
            $userData = $marzbanApi->getUser($panel->auth_token, $user_id);

            $timeOnline = strtotime($userData['online_at']);
            $status = $this->determineUserStatus($timeOnline);

            return $status;
        } catch (Exception $e) {
            Log::error('Failed to check user status', [
                'panel_id' => $panel_id,
                'user_id' => $user_id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Определение статуса пользователя на основе времени последней активности
     *
     * @param int|null $timeOnline
     * @return array
     */
    private function determineUserStatus(?int $timeOnline): array
    {
        if ($timeOnline === null) {
            return [
                'status' => 'inactive',
                'message' => 'Пользователь еще не активирован'
            ];
        }

        if ($timeOnline < time() - 60) {
            return [
                'status' => 'offline',
                'message' => 'Пользователь не активен',
                'last_seen' => date('Y-m-d H:i:s', $timeOnline)
            ];
        }

        return [
            'status' => 'online',
            'message' => 'Пользователь активен',
            'last_update' => date('Y-m-d H:i:s', $timeOnline)
        ];
    }

    /**
     * Удаление пользователя панели
     *
     * @param int $panel_id
     * @param string $user_id
     * @return void
     * @throws GuzzleException
     * @throws Exception
     */
    public function deleteServerUser(int $panel_id, string $user_id): void
    {
        Log::info('Deleting server user', ['panel_id' => $panel_id, 'user_id' => $user_id, 'source' => 'panel']);

        try {
            $panel = $this->updateMarzbanToken($panel_id);
            $marzbanApi = new MarzbanAPI($panel->api_address);

            // Удаляем пользователя из панели
            $deleteResult = $marzbanApi->deleteUser($panel->auth_token, $user_id);
            Log::info('Marzban API delete result', ['result' => $deleteResult, 'source' => 'panel']);

            // Удаляем запись из БД - загружаем только необходимые поля
            $serverUser = ServerUser::select('id')->where('id', $user_id)->firstOrFail();

            // Удаляем связанную запись KeyActivateUser через прямой запрос
            $keyActivateUser = \App\Models\KeyActivateUser\KeyActivateUser::where('server_user_id', $user_id)
                ->select('id', 'key_activate_id')
                ->first();

            if ($keyActivateUser) {
                $keyActivateId = $keyActivateUser->key_activate_id;

                // Получаем статус ключа отдельным запросом
                $keyStatus = \App\Models\KeyActivate\KeyActivate::where('id', $keyActivateId)
                    ->value('status');

                Log::critical("⚠️ [KEY: {$keyActivateId}] УДАЛЕНИЕ СВЯЗИ keyActivateUser (при удалении пользователя сервера) | KEY_ID: {$keyActivateId} | {$keyActivateId}", [
                    'source' => 'panel',
                    'action' => 'delete_key_activate_user',
                    'key_activate_user_id' => $keyActivateUser->id,
                    'key_activate_id' => $keyActivateId,
                    'search_key' => $keyActivateId, // Для быстрого поиска
                    'search_tag' => 'KEY_USER_DELETED',
                    'server_user_id' => $user_id,
                    'panel_id' => $panel_id,
                    'key_status' => $keyStatus ?? 'unknown',
                    'key_status_text' => $keyStatus ? $this->getStatusTextByCode($keyStatus) : 'unknown',
                    'reason' => 'Удаление пользователя сервера через deleteServerUser()',
                    'method' => 'deleteServerUser',
                    'file' => __FILE__,
                    'line' => __LINE__
                ]);

                // Удаляем через прямой запрос
                \App\Models\KeyActivateUser\KeyActivateUser::where('id', $keyActivateUser->id)->delete();
            }

            // Удаляем через прямой запрос
            $deleted = ServerUser::where('id', $user_id)->delete();
            if (!$deleted) {
                throw new RuntimeException('Failed to delete user record from database');
            }

            Log::info('Server user deleted successfully', [
                'panel_id' => $panel_id,
                'user_id' => $user_id,
                'source' => 'panel'
            ]);
        } catch (Exception $e) {
            Log::error('Failed to delete server user', [
                'panel_id' => $panel_id,
                'user_id' => $user_id,
                'error' => $e->getMessage(),
                'source' => 'panel',
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Генерация REALITY ключей через SSH
     *
     * @param Panel $panel
     * @return array Массив с ключами: ['private_key', 'public_key', 'short_id', 'grpc_short_id']
     * @throws RuntimeException
     */
    private function generateRealityKeys(Panel $panel): array
    {
        try {
            if (!$panel->server) {
                throw new RuntimeException('Server not found for panel');
            }

            $serverDto = ServerFactory::fromEntity($panel->server);
            $ssh = $this->connectSshAdapter($serverDto);

            Log::info('Generating REALITY keys', [
                'panel_id' => $panel->id,
                'server_id' => $panel->server_id,
                'source' => 'panel'
            ]);

            // Генерация приватного и публичного ключа
            $x25519Output = $ssh->exec('docker exec marzban-marzban-1 xray x25519 2>&1');

            if ($ssh->getExitStatus() !== 0) {
                throw new RuntimeException("Failed to generate x25519 keys: {$x25519Output}");
            }

            // Парсинг вывода xray x25519
            // Формат может быть разным:
            // "Private key: XXX\nPublic key: YYY"
            // или "XXX\nYYY" (без префиксов)
            $privateKey = null;
            $publicKey = null;

            $lines = array_filter(array_map('trim', explode("\n", $x25519Output)));

            foreach ($lines as $line) {
                // Ищем строки с префиксами "Private key:" или "Public key:"
                if (preg_match('/Private\s+key[:\s]+(.+)/i', $line, $matches)) {
                    $privateKey = trim($matches[1]);
                } elseif (preg_match('/Public\s+key[:\s]+(.+)/i', $line, $matches)) {
                    $publicKey = trim($matches[1]);
                } elseif (empty($privateKey) && preg_match('/^[A-Za-z0-9_\-]{40,}$/', $line)) {
                    // Если нет префиксов, первая длинная строка - приватный ключ
                    $privateKey = $line;
                } elseif (!empty($privateKey) && empty($publicKey) && preg_match('/^[A-Za-z0-9_\-]{40,}$/', $line)) {
                    // Вторая длинная строка - публичный ключ
                    $publicKey = $line;
                }
            }

            if (empty($privateKey) || empty($publicKey)) {
                throw new RuntimeException("Failed to parse x25519 keys from output. Output: " . substr($x25519Output, 0, 200));
            }

            // Генерация ShortID для TCP REALITY
            $shortIdOutput = $ssh->exec('openssl rand -hex 8 2>&1');
            if ($ssh->getExitStatus() !== 0) {
                throw new RuntimeException("Failed to generate ShortID: {$shortIdOutput}");
            }
            $shortId = trim($shortIdOutput);

            // Генерация ShortID для GRPC REALITY (другой)
            $grpcShortIdOutput = $ssh->exec('openssl rand -hex 8 2>&1');
            if ($ssh->getExitStatus() !== 0) {
                throw new RuntimeException("Failed to generate GRPC ShortID: {$grpcShortIdOutput}");
            }
            $grpcShortId = trim($grpcShortIdOutput);

            // Генерация ShortID для XHTTP REALITY
            $xhttpShortIdOutput = $ssh->exec('openssl rand -hex 8 2>&1');
            if ($ssh->getExitStatus() !== 0) {
                throw new RuntimeException("Failed to generate XHTTP ShortID: {$xhttpShortIdOutput}");
            }
            $xhttpShortId = trim($xhttpShortIdOutput);

            // Валидация ключей
            if (strlen($privateKey) < 40 || strlen($publicKey) < 40) {
                throw new RuntimeException("Invalid key length generated");
            }

            if (strlen($shortId) !== 16 || strlen($grpcShortId) !== 16 || strlen($xhttpShortId) !== 16) {
                throw new RuntimeException("Invalid ShortID length generated");
            }

            Log::info('REALITY keys generated successfully', [
                'panel_id' => $panel->id,
                'source' => 'panel'
            ]);

            return [
                'private_key' => $privateKey,
                'public_key' => $publicKey,
                'short_id' => $shortId,
                'grpc_short_id' => $grpcShortId,
                'xhttp_short_id' => $xhttpShortId
            ];
        } catch (Exception $e) {
            Log::error('Failed to generate REALITY keys', [
                'panel_id' => $panel->id,
                'error' => $e->getMessage(),
                'source' => 'panel'
            ]);
            throw new RuntimeException('Failed to generate REALITY keys: ' . $e->getMessage());
        }
    }

    /**
     * Получение или генерация REALITY ключей для панели
     *
     * @param Panel $panel
     * @return array
     * @throws RuntimeException
     */
    private function getOrGenerateRealityKeys(Panel $panel): array
    {
        // Проверяем, есть ли уже сохраненные ключи
        if ($panel->hasRealityKeys()) {
            Log::info('Using existing REALITY keys', [
                'panel_id' => $panel->id,
                'generated_at' => $panel->reality_keys_generated_at,
                'source' => 'panel'
            ]);

            return [
                'private_key' => $panel->reality_private_key,
                'public_key' => $panel->reality_public_key,
                'short_id' => $panel->reality_short_id,
                'grpc_short_id' => $panel->reality_grpc_short_id,
                'xhttp_short_id' => $panel->reality_xhttp_short_id
            ];
        }

        // Генерируем новые ключи
        Log::info('Generating new REALITY keys', [
            'panel_id' => $panel->id,
            'source' => 'panel'
        ]);

        $keys = $this->generateRealityKeys($panel);

        // Сохраняем ключи в БД
        $panel->reality_private_key = $keys['private_key'];
        $panel->reality_public_key = $keys['public_key'];
        $panel->reality_short_id = $keys['short_id'];
        $panel->reality_grpc_short_id = $keys['grpc_short_id'];
        $panel->reality_xhttp_short_id = $keys['xhttp_short_id'];
        $panel->reality_keys_generated_at = now();
        $panel->save();

        Log::info('REALITY keys saved to database', [
            'panel_id' => $panel->id,
            'source' => 'panel'
        ]);

        return $keys;
    }

    /**
     * Получение путей к TLS сертификатам из панели или конфигурации
     *
     * @param Panel|null $panel Панель для получения сертификатов
     * @return array Массив с ключами 'cert' и 'key'
     */
    private function getTlsCertificatePaths(?Panel $panel = null): array
    {
        // Если указана панель и у неё есть свои сертификаты - используем их
        if ($panel && $panel->tls_certificate_path && $panel->tls_key_path) {
            return [
                'cert' => $panel->tls_certificate_path,
                'key' => $panel->tls_key_path
            ];
        }

        // Иначе используем настройки из конфигурации
        return [
            'cert' => config('marzban.tls_certificate_path', '/var/lib/marzban/certificates/cert.pem'),
            'key' => config('marzban.tls_key_path', '/var/lib/marzban/certificates/key.pem')
        ];
    }

    /**
     * Получение настроек безопасности (TLS или none) в зависимости от настроек панели
     *
     * @param Panel|null $panel Панель для проверки настроек TLS
     * @return array Массив с ключами 'security' и 'tlsSettings' (если нужно)
     */
    private function getSecuritySettings(?Panel $panel = null): array
    {
        // Если панель указана и у неё включен TLS - используем TLS
        if ($panel && $panel->use_tls) {
            $certPaths = $this->getTlsCertificatePaths($panel);
            
            Log::info('TLS settings applied for panel', [
                'panel_id' => $panel->id,
                'use_tls' => $panel->use_tls,
                'cert_path' => $certPaths['cert'],
                'key_path' => $certPaths['key'],
                'source' => 'panel'
            ]);
            
            return [
                'security' => 'tls',
                'tlsSettings' => [
                    'allowInsecure' => false,
                    'minVersion' => '1.2',
                    'certificates' => [
                        [
                            'certificateFile' => $certPaths['cert'],
                            'keyFile' => $certPaths['key']
                        ]
                    ]
                ]
            ];
        }

        // По умолчанию используем none для обратной совместимости
        Log::info('TLS not enabled for panel, using security=none', [
            'panel_id' => $panel ? $panel->id : null,
            'use_tls' => $panel ? $panel->use_tls : null,
            'has_cert' => $panel && $panel->tls_certificate_path ? 'yes' : 'no',
            'source' => 'panel'
        ]);
        
        return [
            'security' => 'none'
        ];
    }

    /**
     * Построение базовой конфигурации (общая часть)
     *
     * @param Panel|null $panel Панель для проверки настроек прокси
     * @return array
     */
    private function buildBaseConfig(?Panel $panel = null): array
    {
        $outbounds = [
            [
                "protocol" => "freedom",
                "tag" => "DIRECT",
                "settings" => [
                    "domainStrategy" => "UseIPv4"
                ]
            ]
        ];

        $routingRules = [
            [
                "type" => "field",
                "ip" => [
                    "geoip:private"
                ],
                "outboundTag" => "DIRECT"
            ]
        ];

        return [
            "log" => [
                "loglevel" => "warning",
                "access" => "/var/lib/marzban/access.log",
                "error" => "/var/lib/marzban/error.log",
                "dnsLog" => true
            ],
            "dns" => [
                "servers" => [
                    "1.1.1.1",
                    "8.8.8.8",
                    [
                        "address" => "1.1.1.1",
                        "port" => 53,
                        "domains" => [
                            "geosite:cn"
                        ],
                        "skipFallback" => false
                    ],
                    [
                        "address" => "8.8.8.8",
                        "port" => 53,
                        "domains" => [
                            "geosite:google"
                        ],
                        "skipFallback" => false
                    ]
                ],
                "queryStrategy" => "UseIPv4",
                "disableCache" => false,
                "disableFallback" => false,
                "disableFallbackIfMatch" => false
            ],
            "routing" => [
                "domainStrategy" => "IPIfNonMatch",
                "rules" => $routingRules
            ],
            "outbounds" => $outbounds,
            "policy" => [
                "levels" => [
                    [
                        "handshake" => 8,
                        "connIdle" => 300,
                        "uplinkOnly" => 2,
                        "downlinkOnly" => 2,
                        "statsUserUplink" => true,
                        "statsUserDownlink" => true
                    ]
                ],
                "system" => [
                    "statsInboundUplink" => true,
                    "statsInboundDownlink" => true
                ]
            ]
        ];
    }


    /**
     * Построение стабильных протоколов (без REALITY)
     *
     * @param Panel|null $panel Панель для получения сертификатов
     * @return array
     */
    private function buildStableInbounds(?Panel $panel = null): array
    {
        return [
                [
                    "tag" => "VLESS-WS",
                    "listen" => "0.0.0.0",
                    "port" => 2087,
                    "protocol" => "vless",
                    "settings" => [
                        "clients" => [],
                        "decryption" => "none",
                        "level" => 0
                    ],
                    "streamSettings" => array_merge([
                        "network" => "ws",
                        "wsSettings" => [
                            "path" => "/vless"
                        ]
                    ], $this->getSecuritySettings($panel)),
                    "sniffing" => [
                        "enabled" => true,
                        "destOverride" => ["http", "tls"]
                    ]
                ],
                [
                    "tag" => "VMESS-WS",
                    "listen" => "0.0.0.0",
                    "port" => 2096,
                    "protocol" => "vmess",
                    "settings" => [
                        "clients" => [],
                        "level" => 0
                    ],
                    "streamSettings" => array_merge([
                        "network" => "ws",
                        "wsSettings" => [
                            "path" => "/vmess"
                        ]
                    ], $this->getSecuritySettings($panel)),
                    "sniffing" => [
                        "enabled" => true,
                        "destOverride" => ["http", "tls"]
                    ]
                ],
                [
                    "tag" => "TROJAN-WS",
                    "listen" => "0.0.0.0",
                    "port" => 2097,
                    "protocol" => "trojan",
                    "settings" => [
                        "clients" => [],
                        "level" => 0
                    ],
                    "streamSettings" => array_merge([
                        "network" => "ws",
                        "wsSettings" => [
                            "path" => "/trojan"
                        ]
                    ], $this->getSecuritySettings($panel)),
                    "sniffing" => [
                        "enabled" => true,
                        "destOverride" => ["http", "tls"]
                    ]
                ],
                [
                    "tag" => "Shadowsocks-TCP",
                    "listen" => "0.0.0.0",
                    "port" => 8388,
                    "protocol" => "shadowsocks",
                    "settings" => [
                        "clients" => [],
                        "network" => "tcp,udp",
                        "level" => 0
                    ]
                ],
                // VLESS TCP с HTTP/1.1 обфускацией для обхода ML-анализа (старый стандарт)
                // Использует HTTP/1.1 вместо HTTP/2, что пропускается ML-моделью как обычный веб-трафик
                // Используем порт 8080 (альтернативный HTTP) - не блокируется, но выглядит как веб-трафик
                [
                    "tag" => "VLESS TCP HTTP/1.1 Obfuscated",
                    "listen" => "0.0.0.0",
                    "port" => 8080,
                    "protocol" => "vless",
                    "settings" => [
                        "clients" => [],
                        "decryption" => "none",
                        "level" => 0
                    ],
                    "streamSettings" => [
                        "network" => "tcp",
                        "tcpSettings" => [
                            "acceptProxyProtocol" => false,
                            "header" => [
                                "type" => "http",
                                "request" => [
                                    "version" => "1.1",
                                    "method" => "GET",
                                    "path" => ["/", "/index.html", "/home"],
                                    "headers" => [
                                        "Host" => ["www.microsoft.com", "microsoft.com"],
                                        "User-Agent" => [
                                            "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36",
                                            "Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:121.0) Gecko/20100101 Firefox/121.0"
                                        ],
                                        "Accept" => ["text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8"],
                                        "Accept-Language" => ["en-US,en;q=0.9", "ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3"],
                                        "Accept-Encoding" => ["gzip, deflate, br"],
                                        "Connection" => ["keep-alive"],
                                        "Upgrade-Insecure-Requests" => ["1"],
                                        "Sec-Fetch-Dest" => ["document"],
                                        "Sec-Fetch-Mode" => ["navigate"],
                                        "Sec-Fetch-Site" => ["none"],
                                        "Cache-Control" => ["max-age=0"]
                                    ]
                                ],
                                "response" => [
                                    "version" => "1.1",
                                    "status" => "200",
                                    "reason" => "OK",
                                    "headers" => [
                                        "Content-Type" => ["text/html; charset=utf-8"],
                                        "Connection" => ["keep-alive"]
                                    ]
                                ]
                            ]
                        ]
                    ],
                    "sniffing" => [
                        "enabled" => true,
                        "destOverride" => ["http", "tls"]
                    ]
                ],
                // VLESS HTTP Upgrade - альтернативный протокол для обхода (имитирует HTTP запрос)
                // Используем порт 8881 (альтернативный HTTP) - не блокируется
                [
                    "tag" => "VLESS HTTP Upgrade",
                    "listen" => "0.0.0.0",
                    "port" => 8881,
                    "protocol" => "vless",
                    "settings" => [
                        "clients" => [],
                        "decryption" => "none",
                        "level" => 0
                    ],
                    "streamSettings" => array_merge([
                        "network" => "httpupgrade",
                        "httpupgradeSettings" => [
                            "path" => "/",
                            "host" => "www.microsoft.com"
                        ]
                    ], $this->getSecuritySettings($panel)),
                    "sniffing" => [
                        "enabled" => true,
                        "destOverride" => ["http", "tls"]
                    ]
                ]
        ];
    }

    /**
     * Построение REALITY протоколов (улучшенная версия для обхода белых списков)
     *
     * @param string $privateKey
     * @param string $shortId
     * @param string $grpcShortId
     * @param string $xhttpShortId
     * @return array
     */
    private function buildRealityInbounds(string $privateKey, string $shortId, string $grpcShortId, string $xhttpShortId): array
    {
        return [
            // VLESS TCP REALITY - основной протокол с улучшенными SNI
            // Используем порт 8443 (альтернативный HTTPS) - не блокируется, но выглядит как веб-трафик
            [
                "tag" => "VLESS TCP REALITY",
                "listen" => "0.0.0.0",
                "port" => 8443,
                "protocol" => "vless",
                "settings" => [
                    "clients" => [],
                    "decryption" => "none",
                    "level" => 0
                ],
                "streamSettings" => [
                    "network" => "tcp",
                    "tcpSettings" => [
                        "acceptProxyProtocol" => false,
                        "header" => [
                            "type" => "none"
                        ]
                    ],
                    "security" => "reality",
                    "realitySettings" => [
                        "show" => false,
                        "dest" => "www.microsoft.com:443",
                        "xver" => 0,
                        "serverNames" => [
                            "www.microsoft.com",
                            "microsoft.com",
                            "login.microsoftonline.com",
                            "outlook.office.com",
                            "office.com",
                            // Добавляем альтернативные домены для ротации
                            "www.cloudflare.com",
                            "cloudflare.com",
                            "www.discord.com",
                            "discord.com"
                        ],
                        "privateKey" => $privateKey,
                        "shortIds" => ["", $shortId]
                    ]
                ],
                "sniffing" => [
                    "enabled" => true,
                    "destOverride" => ["http", "tls", "quic"]
                ]
            ],
            // VLESS GRPC REALITY - альтернативный протокол с улучшенными SNI
            // Используем порт 9443 (альтернативный HTTPS) - не блокируется
            [
                "tag" => "VLESS GRPC REALITY",
                "listen" => "0.0.0.0",
                "port" => 9443,
                "protocol" => "vless",
                "settings" => [
                    "clients" => [],
                    "decryption" => "none",
                    "level" => 0
                ],
                "streamSettings" => [
                    "network" => "grpc",
                    "grpcSettings" => [
                        "serviceName" => "GunService"
                    ],
                    "security" => "reality",
                    "realitySettings" => [
                        "show" => false,
                        "dest" => "www.apple.com:443",
                        "xver" => 0,
                        "serverNames" => [
                            "www.apple.com",
                            "apple.com",
                            "cdn-apple.com",
                            "icloud.com",
                            "appleid.apple.com",
                            // Добавляем альтернативные домены
                            "www.github.com",
                            "github.com",
                            "www.stackoverflow.com",
                            "stackoverflow.com"
                        ],
                        "privateKey" => $privateKey,
                        "shortIds" => ["", $grpcShortId]
                    ]
                ],
                "sniffing" => [
                    "enabled" => true,
                    "destOverride" => ["http", "tls", "quic"]
                ]
            ],
            // VLESS XHTTP REALITY - комбинация Reality + XHTTP для максимального обхода блокировок
            // Используем порт 8880 (альтернативный HTTP) - не блокируется, выглядит как веб-трафик
            [
                "tag" => "VLESS XHTTP REALITY",
                "listen" => "0.0.0.0",
                "port" => 8880,
                "protocol" => "vless",
                "settings" => [
                    "clients" => [],
                    "decryption" => "none",
                    "level" => 0
                ],
                "streamSettings" => [
                    "network" => "tcp",
                    "security" => "reality",
                    "realitySettings" => [
                        "show" => false,
                        "dest" => "www.microsoft.com:443",
                        "xver" => 0,
                        "serverNames" => [
                            "www.microsoft.com",
                            "microsoft.com",
                            "login.microsoftonline.com",
                            "outlook.office.com",
                            "office.com",
                            "www.cloudflare.com",
                            "cloudflare.com"
                        ],
                        "privateKey" => $privateKey,
                        "shortIds" => ["", $xhttpShortId]
                    ],
                    "tcpSettings" => [
                        "acceptProxyProtocol" => false,
                        "header" => [
                            "type" => "http",
                            "request" => [
                                "version" => "1.1",
                                "method" => "GET",
                                "path" => ["/", "/index.html", "/home", "/api/v1"],
                                "headers" => [
                                    "Host" => ["www.microsoft.com", "microsoft.com", "login.microsoftonline.com"],
                                    "User-Agent" => [
                                        "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36",
                                        "Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:121.0) Gecko/20100101 Firefox/121.0",
                                        "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36"
                                    ],
                                    "Accept" => ["text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8"],
                                    "Accept-Language" => ["en-US,en;q=0.9", "ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3"],
                                    "Accept-Encoding" => ["gzip, deflate, br"],
                                    "Connection" => ["keep-alive"],
                                    "Upgrade-Insecure-Requests" => ["1"],
                                    "Sec-Fetch-Dest" => ["document"],
                                    "Sec-Fetch-Mode" => ["navigate"],
                                    "Sec-Fetch-Site" => ["none"],
                                    "Cache-Control" => ["max-age=0"]
                                ]
                            ],
                            "response" => [
                                "version" => "1.1",
                                "status" => "200",
                                "reason" => "OK",
                                "headers" => [
                                    "Content-Type" => ["text/html; charset=utf-8"],
                                    "Connection" => ["keep-alive"],
                                    "Cache-Control" => ["private, max-age=0"]
                                ]
                            ]
                        ]
                    ]
                ],
                "sniffing" => [
                    "enabled" => true,
                    "destOverride" => ["http", "tls", "quic"]
                ]
            ],
            // Дополнительный VLESS TCP REALITY с другими SNI для разнообразия
            // Используем порт 2083 (CDN порт) - не блокируется, выглядит как CDN трафик
            [
                "tag" => "VLESS TCP REALITY ALT",
                "listen" => "0.0.0.0",
                "port" => 2083,
                "protocol" => "vless",
                "settings" => [
                    "clients" => [],
                    "decryption" => "none",
                    "level" => 0
                ],
                "streamSettings" => [
                    "network" => "tcp",
                    "security" => "reality",
                    "realitySettings" => [
                        "show" => false,
                        "dest" => "www.google.com:443",
                        "xver" => 0,
                        "serverNames" => [
                            "www.google.com",
                            "google.com",
                            "accounts.google.com",
                            "mail.google.com",
                            "drive.google.com",
                            // Добавляем альтернативные домены
                            "www.amazon.com",
                            "amazon.com",
                            "www.netflix.com",
                            "netflix.com"
                        ],
                        "privateKey" => $privateKey,
                        "shortIds" => ["", $xhttpShortId]
                    ]
                ],
                "sniffing" => [
                    "enabled" => true,
                    "destOverride" => ["http", "tls", "quic"]
                ]
            ]
        ];
    }

    /**
     * Применение конфигурации к панели
     *
     * @param Panel $panel
     * @param array $json_config
     * @param string $config_type
     * @return void
     * @throws RuntimeException
     */
    private function applyConfiguration(Panel $panel, array $json_config, string $config_type): void
    {
        $marzbanApi = new MarzbanAPI($panel->api_address);

        try {
            // Валидация конфигурации перед отправкой
            $this->validateConfiguration($json_config);

            // Получаем текущую конфигурацию для сравнения (опционально, для отладки)
            try {
                $currentConfig = $marzbanApi->getConfig($panel->auth_token);
                if (!empty($currentConfig)) {
                    Log::debug('Current Marzban config structure', [
                        'panel_id' => $panel->id,
                        'has_inbounds' => isset($currentConfig['inbounds']),
                        'inbounds_count' => isset($currentConfig['inbounds']) ? count($currentConfig['inbounds']) : 0,
                        'source' => 'panel'
                    ]);
                }
            } catch (\Exception $e) {
                // Игнорируем ошибку получения текущей конфигурации
                Log::debug('Could not get current config for comparison', [
                    'panel_id' => $panel->id,
                    'error' => $e->getMessage(),
                    'source' => 'panel'
                ]);
            }

            // Логирование конфигурации перед отправкой (для отладки)
            // Сохраняем JSON для проверки структуры
            $configJson = json_encode($json_config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            Log::info('Sending configuration to Marzban', [
                'panel_id' => $panel->id,
                'config_type' => $config_type,
                'inbounds_count' => count($json_config['inbounds']),
                'inbounds_tags' => array_column($json_config['inbounds'], 'tag'),
                'config_size' => strlen($configJson),
                'source' => 'panel'
            ]);

            // Детальное логирование для отладки (только первые 2000 символов)
            Log::debug('Configuration JSON (first 2000 chars)', [
                'panel_id' => $panel->id,
                'config_preview' => substr($configJson, 0, 2000),
                'source' => 'panel'
            ]);

            // Применение конфигурации с retry механизмом
            $maxRetries = 2;
            $retryDelay = 2; // секунды

            for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
        try {
            $marzbanApi->modifyConfig($panel->auth_token, $json_config);
                    break; // Успешно, выходим из цикла
                } catch (RuntimeException $e) {
                    // Если это последняя попытка или ошибка не связана с сервером, пробрасываем дальше
                    if ($attempt === $maxRetries || !str_contains($e->getMessage(), 'Сервер Marzban недоступен')) {
                        throw $e;
                    }

                    // Логируем попытку повтора
                    Log::warning('Retrying configuration update', [
                        'panel_id' => $panel->id,
                        'attempt' => $attempt,
                        'max_retries' => $maxRetries,
                        'source' => 'panel'
                    ]);

                    // Ждем перед следующей попыткой
                    sleep($retryDelay);
                }
            }

            // Обновление статуса панели
            $panel->panel_status = Panel::PANEL_CONFIGURED;
            $panel->config_type = $config_type;
            $panel->config_updated_at = now();
            $panel->has_error = false;
            $panel->error_message = null;
            $panel->error_at = null;
            $panel->save();

            $protocolsCount = count($json_config['inbounds']);
            Log::info('Configuration updated successfully', [
                'panel_id' => $panel->id,
                'config_type' => $config_type,
                'protocols_count' => $protocolsCount,
                'source' => 'panel'
            ]);
        } catch (Exception $e) {
            // Сохранение ошибки в БД
            $panel->has_error = true;
            $panel->error_message = $e->getMessage();
            $panel->error_at = now();
            $panel->save();

            Log::error('Failed to update configuration', [
                'panel_id' => $panel->id,
                'config_type' => $config_type,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'source' => 'panel'
            ]);

            throw new RuntimeException('Failed to update panel configuration: ' . $e->getMessage());
        }
    }

    /**
     * Обновление конфигурации панели - стабильный вариант (без REALITY)
     *
     * Использует только проверенные протоколы для максимальной стабильности
     *
     * @param int $panel_id
     * @return void
     * @throws GuzzleException
     */
    public function updateConfigurationStable(int $panel_id): void
    {
        $panel = self::updateMarzbanToken($panel_id);

        Log::info('Updating configuration to stable (without REALITY)', [
            'panel_id' => $panel_id,
            'use_tls' => $panel->use_tls,
            'has_cert' => $panel->tls_certificate_path ? 'yes' : 'no',
            'has_key' => $panel->tls_key_path ? 'yes' : 'no',
            'source' => 'panel'
        ]);

        $json_config = $this->buildBaseConfig($panel);
        $json_config['inbounds'] = $this->buildStableInbounds($panel);

        $this->applyConfiguration($panel, $json_config, Panel::CONFIG_TYPE_STABLE);
    }

    /**
     * Обновление конфигурации панели - с REALITY (лучший обход блокировок)
     *
     * Автоматически генерирует и сохраняет REALITY ключи при необходимости
     * Включает REALITY протоколы + стабильные протоколы для обратной совместимости
     * При ошибке генерации ключей использует fallback на стабильный конфиг
     *
     * @param int $panel_id
     * @return void
     * @throws GuzzleException
     */
    public function updateConfigurationReality(int $panel_id): void
    {
        $panel = self::updateMarzbanToken($panel_id);

        Log::info('Updating configuration to REALITY (best bypass)', [
            'panel_id' => $panel_id,
            'source' => 'panel'
        ]);

        try {
            // Получаем или генерируем REALITY ключи
            $realityKeys = $this->getOrGenerateRealityKeys($panel);

            $json_config = $this->buildBaseConfig($panel);
            $json_config['inbounds'] = array_merge(
                $this->buildRealityInbounds(
                    $realityKeys['private_key'],
                    $realityKeys['short_id'],
                    $realityKeys['grpc_short_id'],
                    $realityKeys['xhttp_short_id']
                ),
                $this->buildStableInbounds($panel)
            );

            $this->applyConfiguration($panel, $json_config, Panel::CONFIG_TYPE_REALITY);
        } catch (Exception $e) {
            // Fallback: если не удалось сгенерировать ключи, используем стабильный конфиг
            Log::warning('Failed to generate REALITY keys, falling back to stable config', [
                'panel_id' => $panel_id,
                'error' => $e->getMessage(),
                'source' => 'panel'
            ]);

            // Применяем стабильный конфиг вместо REALITY
            $this->updateConfigurationStable($panel_id);

            // Пробрасываем исключение с информацией о fallback
            throw new RuntimeException(
                'Не удалось применить REALITY конфигурацию. ' .
                'Применен стабильный конфиг. Ошибка: ' . $e->getMessage()
            );
        }
    }

    /**
     * Обновление конфигурации панели (legacy метод для обратной совместимости)
     *
     * По умолчанию использует REALITY конфигурацию
     *
     * @param int $panel_id
     * @return void
     * @throws GuzzleException
     */
    public function updateConfiguration(int $panel_id): void
    {
        // По умолчанию используем REALITY конфигурацию
        $this->updateConfigurationReality($panel_id);
    }

    /**
     * Валидация конфигурации перед применением
     *
     * @param array $config
     * @return void
     * @throws RuntimeException
     */
    private function validateConfiguration(array $config): void
    {
        // Проверка обязательных полей
        if (empty($config['inbounds'])) {
            throw new RuntimeException('Configuration must contain inbounds');
        }

        // Проверка портов на уникальность
        $ports = [];
        foreach ($config['inbounds'] as $inbound) {
            if (isset($inbound['port'])) {
                $port = $inbound['port'];
                if (isset($ports[$port])) {
                    throw new RuntimeException("Duplicate port found: {$port}");
                }
                $ports[$port] = true;

                // Проверка валидности порта
                if ($port < 1 || $port > 65535) {
                    throw new RuntimeException("Invalid port number: {$port}");
                }
            }
        }

        // Проверка REALITY настроек
        foreach ($config['inbounds'] as $inbound) {
            if (isset($inbound['streamSettings']['security'])
                && $inbound['streamSettings']['security'] === 'reality') {

                $realitySettings = $inbound['streamSettings']['realitySettings'] ?? [];

                if (empty($realitySettings['privateKey'])) {
                    throw new RuntimeException('REALITY private key is required');
                }

                if (empty($realitySettings['shortIds']) || count($realitySettings['shortIds']) < 2) {
                    throw new RuntimeException('REALITY shortIds must contain at least 2 values');
                }

                if (empty($realitySettings['serverNames'])) {
                    throw new RuntimeException('REALITY serverNames are required');
                }

                if (empty($realitySettings['dest'])) {
                    throw new RuntimeException('REALITY dest is required');
                }
            }
        }

        Log::info('Configuration validation passed', ['source' => 'panel']);
    }

    /**
     * Добавление пользователи и протоколов подключения
     *
     * @param int $panel_id
     * @param int $userTgId
     * @param int $data_limit
     * @param int $expire
     * @param string $key_activate_id
     * @return ServerUser
     * @throws GuzzleException
     */
    public function addServerUser(int $panel_id, int $userTgId, int $data_limit, int $expire, string $key_activate_id, array $options = []): ServerUser
    {
        try {
            // Получаем key_activate ДО использования в логе
            /**
             * @var KeyActivate $key_activate
             */
            $key_activate = KeyActivate::query()->where('id', $key_activate_id)->firstOrFail();

            Log::info('Creating server user', [
                'panel_id' => $panel_id,
                'data_limit' => $data_limit,
                'expire' => $expire,
                'expire_date' => date('Y-m-d H:i:s', $expire),
                'key_finish_at' => $key_activate->finish_at ?? null,
                'key_finish_at_date' => $key_activate->finish_at ? date('Y-m-d H:i:s', $key_activate->finish_at) : null,
                'current_time' => time(),
                'current_date' => date('Y-m-d H:i:s'),
                'days_until_expire' => ceil(($expire - time()) / 86400),
                'source' => 'panel',
                'key_activate_id' => $key_activate_id
            ]);

            $panel = self::updateMarzbanToken($panel_id);
            if (!$panel->server) {
                throw new RuntimeException('Server not found for panel');
            }

            $marzbanApi = new MarzbanAPI($panel->api_address);
            $userId = Str::uuid();
            $maxConnections = $options['max_connections'] ?? config('panel.max_connections', 4);

            $userData = $marzbanApi->createUser(
                $panel->auth_token,
                $userId,
                $data_limit,
                $expire,
                $maxConnections // ← ПЕРЕДАЕМ ЛИМИТ ПОДКЛЮЧЕНИЙ
            );

            if (empty($userData['links'])) {
                throw new RuntimeException('Failed to get user links from Marzban API');
            }

            $serverUser = new ServerUser();
            $serverUser->id = $userId;
            $serverUser->panel_id = $panel->id;
            $serverUser->is_free = false;
            $serverUser->keys = json_encode($userData['links']);

            if (!$serverUser->save()) {
                throw new RuntimeException('Failed to save server user');
            }

            // Создаем запись key_activate_user
            $keyActivateUserService = new KeyActivateUserService();
            try {
                $keyActivateUserService->create(
                    $serverUser->id,
                    $key_activate_id,
                    $panel->server->location_id
                );
            } catch (Exception $e) {
                // Если не удалось создать key_activate_user, удаляем созданного пользователя
                $serverUser->delete();
                throw new RuntimeException('Failed to create key activate user: ' . $e->getMessage());
            }

            Log::info('Server user created successfully', [
                'user_id' => $userId,
                'panel_id' => $panel_id,
                'source' => 'panel'
            ]);

            return $serverUser;
        } catch (RuntimeException $r) {
            Log::error('Runtime error while creating server user', [
                'error' => $r->getMessage(),
                'trace' => $r->getTraceAsString(),
                'source' => 'panel'
            ]);
            throw $r;
        } catch (Exception $e) {
            Log::error('Error while creating server user', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'source' => 'panel'
            ]);
            throw new RuntimeException($e->getMessage());
        }
    }

    /**
     * Перенос пользователя с одной панели на другую
     *
     * @param int $sourcePanel_id ID исходной панели
     * @param int $targetPanel_id ID целевой панели
     * @param string $serverUser_id ID пользователя сервера
     * @return ServerUser|null Обновленный пользователь сервера
     * @throws RuntimeException|GuzzleException
     */
    public function transferUser(int $sourcePanel_id, int $targetPanel_id, string $serverUser_id): ServerUser
    {
        // Увеличиваем лимит памяти для операции переноса
        $originalMemoryLimit = ini_get('memory_limit');
        ini_set('memory_limit', '256M');

        try {
            // Загружаем только необходимые поля панелей
            // api_address - это accessor, используем panel_adress для select
            /** @var Panel $sourcePanel */
            $sourcePanel = Panel::select('id', 'panel', 'panel_adress', 'auth_token', 'server_id')
                ->findOrFail($sourcePanel_id);
            /** @var Panel $targetPanel */
            $targetPanel = Panel::select('id', 'panel', 'panel_adress', 'auth_token', 'server_id')
                ->findOrFail($targetPanel_id);

            // Оптимизированная загрузка: получаем server_user_id напрямую
            $keyActivateUser = KeyActivateUser::select('server_user_id')
                ->where('key_activate_id', $serverUser_id)
                ->firstOrFail();

            // Загружаем только необходимые поля пользователя сервера
            /** @var ServerUser $serverUser */
            $serverUser = ServerUser::select('id', 'panel_id', 'keys')
                ->where('id', $keyActivateUser->server_user_id)
                ->firstOrFail();

            // Загружаем ключ только для отправки уведомления (только необходимые поля)
            $key_activate = KeyActivate::select('id', 'user_tg_id', 'module_salesman_id', 'pack_salesman_id')
                ->findOrFail($serverUser_id);

            // Создаем API клиенты для обеих панелей
            $sourceMarzbanApi = new MarzbanAPI($sourcePanel->api_address);
            $targetMarzbanApi = new MarzbanAPI($targetPanel->api_address);

            // 1. Получаем данные пользователя с исходной панели
            $sourcePanel = self::updateMarzbanToken($sourcePanel->id);
            $userData = $sourceMarzbanApi->getUser($sourcePanel->auth_token, $serverUser->id);

            // 2. Создаем пользователя на новой панели с теми же настройками
//            $newUserData = [
//                'proxies' => $userData['proxies'] ?? ['vmess', 'vless'], // Используем существующие прокси или дефолтные
//                'data_limit' => $userData['data_limit'] ?? 0,
//                'expire' => $userData['expire'] ?? 0,
//                'status' => $userData['status'] ?? 'active'
//            ];
            $targetPanel = self::updateMarzbanToken($targetPanel->id);
            $newUser = $targetMarzbanApi->createUser(
                $targetPanel->auth_token,
                $serverUser->id,
                $userData['data_limit'] - $userData['used_traffic'] ?? 0,
                $userData['expire'] ?? 0
            );

            // 3. Обновляем данные в БД
            DB::beginTransaction();
            try {
                // Сохраняем старые ключи для логирования
                $oldKeys = $serverUser->keys;

                // Обновляем данные пользователя сервера
                $serverUser->panel_id = $targetPanel_id;
//                $serverUser->server_id = $targetPanel->server_id;
                $serverUser->keys = json_encode($newUser['links']); // Новые ключи подключения
                $serverUser->save();

                // Логируем изменения
                Log::info('User transfer completed', [
                    'user_id' => $serverUser_id,
                    'old_panel' => $sourcePanel_id,
                    'new_panel' => $targetPanel_id,
                    'source' => 'panel',
                    'old_keys' => $oldKeys,
                    'new_keys' => $newUser['subscription_url']
                ]);

                // 4. Удаляем пользователя со старой панели
                $sourceMarzbanApi->deleteUser($sourcePanel->auth_token, $serverUser->id);

                DB::commit();

                // Отправляем сообщение через FatherBot
                $message = "⚠️ Ваш ключ доступа: " . "<code>{$key_activate->id}</code> " . "был перемещен на новый сервер!\n\n";
                $message .= "🔗 Для продолжения работы:\n";
                $message .= "• Заново вставьте ссылку-подключение в клиент VPN, или\n";
                $message .= "• При выключенном VPN нажмите кнопку обновления конфигурации\n\n";
                $message .= "🔗 Ссылка: https://vpn-telegram.com/config/{$key_activate->id}";

                try {
                    if (!is_null($key_activate->module_salesman_id)) {
                        // Загружаем только необходимые поля для модульного продавца
                        // module_salesman_id ссылается на Salesman, у которого есть botModule через module_bot_id
                        $salesman = Salesman::select('id', 'module_bot_id')
                            ->where('id', $key_activate->module_salesman_id)
                            ->first();
                        
                        if ($salesman && $salesman->module_bot_id) {
                            $botModule = BotModule::select('id', 'token', 'username')
                                ->where('id', $salesman->module_bot_id)
                                ->first();
                            
                            if ($botModule) {
                                BottApi::senModuleMessage(BotModuleFactory::fromEntity($botModule), $key_activate->user_tg_id, $message);
                            }
                        }
                    } else {
                        // Загружаем только необходимые поля для продавца пакетов
                        $packSalesman = PackSalesman::select('salesman_id')
                            ->where('id', $key_activate->pack_salesman_id)
                            ->first();

                        if ($packSalesman) {
                            $salesman = Salesman::select('id', 'token', 'telegram_id')
                                ->where('id', $packSalesman->salesman_id)
                                ->first();

                            if ($salesman) {
                                $telegram = new Api($salesman->token);
                                $telegram->sendMessage([
                                    'chat_id' => $key_activate->user_tg_id,
                                    'text' => $message,
                                    'parse_mode' => 'HTML'
                                ]);
                            }
                        }
                    }
                } catch (Exception $e) {
                    Log::error('Ошибка при отправке сообщения через FatherBot', [
                        'error' => $e->getMessage(),
                        'key_id' => $key_activate->id,
                        'source' => 'panel'
                    ]);
                }

                // Освобождаем память
                unset($key_activate, $sourcePanel, $targetPanel, $sourceMarzbanApi, $targetMarzbanApi, $userData, $newUser, $oldKeys);

                return $serverUser;
            } catch (Exception $e) {
                DB::rollBack();
                throw new RuntimeException('Failed to update database records: ' . $e->getMessage());
            }
        } catch (Exception $e) {
            Log::error('Failed to transfer user', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'source_panel' => $sourcePanel_id,
                'source' => 'panel',
                'target_panel' => $targetPanel_id,
                'user_id' => $serverUser_id
            ]);
            throw new RuntimeException('Failed to transfer user: ' . $e->getMessage());
        } finally {
            // Восстанавливаем оригинальный лимит памяти
            if (isset($originalMemoryLimit)) {
                ini_set('memory_limit', $originalMemoryLimit);
            }
        }
    }

    /**
     * Получить текстовое представление статуса по коду
     *
     * @param int $statusCode
     * @return string
     */
    private function getStatusTextByCode(int $statusCode): string
    {
        switch ($statusCode) {
            case KeyActivate::EXPIRED:
                return 'EXPIRED (Просрочен)';
            case KeyActivate::ACTIVE:
                return 'ACTIVE (Активирован)';
            case KeyActivate::PAID:
                return 'PAID (Оплачен)';
            case KeyActivate::DELETED:
                return 'DELETED (Удален)';
            default:
                return "Unknown ({$statusCode})";
        }
    }
}
