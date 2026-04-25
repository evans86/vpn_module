<?php

namespace App\Services\Panel\marzban;

use App\Helpers\TrafficLimitHelper;
use App\Dto\Server\ServerDto;
use App\Dto\Server\ServerFactory;
use App\Models\KeyActivate\KeyActivate;
use App\Models\KeyActivateUser\KeyActivateUser;
use App\Models\Panel\Panel;
use App\Models\Server\Server;
use App\Models\ServerMonitoring\ServerMonitoring;
use App\Models\ServerUser\ServerUser;
use App\Services\External\MarzbanAPI;
use App\Services\Notification\TelegramNotificationService;
use App\Services\Key\KeyActivateUserService;
use App\Services\Panel\PanelStrategy;
use Carbon\Carbon;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Exception;
use phpseclib3\Net\SFTP;
use phpseclib3\Net\SSH2;
use RuntimeException;

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

            // Патчим скрипт: ufw выполнять только если установлен (на CentOS/минимальных образах ufw нет)
            $ssh->exec("sed -i 's/^ufw disable$/command -v ufw \\&>\\/dev\\/null \\&\\& ufw disable \\|\\| true/' install_marzban.sh 2>&1");
            Log::info('Patched install script to make ufw optional', ['source' => 'panel']);

            // Патчим скрипт: после загрузки docker-compose.yml подменяем образ на ghcr.io (обход лимита Docker Hub)
            $patchCmd = "sed -i '/File saved in.*docker-compose.yml/a sed -i '\\''s|gozargah/marzban|ghcr.io/gozargah/marzban|g'\\'' \"\$APP_DIR/docker-compose.yml\"' install_marzban.sh";
            $ssh->exec($patchCmd);
            Log::info('Patched install script to use ghcr.io image', ['source' => 'panel']);

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
                // Проверяем, связана ли ошибка с Docker daemon
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
     * Проверка и запуск Docker daemon. При отсутствии Docker — установка через get.docker.com
     *
     * @param SSH2 $ssh
     * @return void
     * @throws RuntimeException
     */
    private function ensureDockerRunning(SSH2 $ssh): void
    {
        try {
            Log::info('Checking Docker daemon status', ['source' => 'panel']);

            // Проверяем, установлен ли docker и запущен ли daemon
            $dockerCheck = $ssh->exec('docker info > /dev/null 2>&1; echo $?');
            $dockerCheck = trim($dockerCheck);

            if ($dockerCheck === '0') {
                Log::info('Docker daemon is running', ['source' => 'panel']);
                return;
            }

            Log::warning('Docker daemon is not running, attempting to start', ['source' => 'panel']);

            $startDockerOutput = $ssh->exec('sudo systemctl start docker 2>&1');
            $startDockerStatus = $ssh->getExitStatus();
            sleep(3);

            $dockerCheck = $ssh->exec('docker info > /dev/null 2>&1; echo $?');
            $dockerCheck = trim($dockerCheck);

            if ($dockerCheck === '0') {
                Log::info('Docker daemon is now running', ['source' => 'panel']);
                return;
            }

            // Если unit docker.service не найден — Docker не установлен, ставим
            $dockerNotFound = (stripos($startDockerOutput, 'not found') !== false || stripos($startDockerOutput, 'does not exist') !== false);
            if ($dockerNotFound) {
                $this->waitForAptLock($ssh, 300);
                $maxInstallAttempts = 2;
                $installStatus = -1;
                $installOutput = '';
                for ($attempt = 1; $attempt <= $maxInstallAttempts; $attempt++) {
                    Log::info('Docker install attempt ' . $attempt . '/' . $maxInstallAttempts, ['source' => 'panel']);
                    $installOutput = $ssh->exec('curl -fsSL https://get.docker.com | sudo sh 2>&1');
                    $installStatus = $ssh->getExitStatus();
                    Log::info('Docker install script finished', [
                        'attempt' => $attempt,
                        'exit_status' => $installStatus,
                        'output_preview' => substr($installOutput, -500),
                        'source' => 'panel'
                    ]);
                    if ($installStatus === 0) {
                        break;
                    }
                    $isLockError = (stripos($installOutput, 'Could not get lock') !== false
                        || stripos($installOutput, 'Unable to acquire') !== false
                        || stripos($installOutput, 'is another process using it') !== false);
                    if ($isLockError && $attempt < $maxInstallAttempts) {
                        Log::warning('Docker install failed due to apt/dpkg lock, waiting 60s and retrying', ['source' => 'panel']);
                        sleep(60);
                        $this->waitForAptLock($ssh, 120);
                    } else {
                        break;
                    }
                }
                if ($installStatus !== 0) {
                    throw new RuntimeException(
                        'Docker installation failed. Exit code: ' . $installStatus . '. ' .
                        'Last output: ' . substr($installOutput, -800)
                    );
                }
                sleep(5);
            }

            // Включаем автозапуск и запускаем
            $enableDockerOutput = $ssh->exec('sudo systemctl enable docker && sudo systemctl start docker 2>&1');
            $enableDockerStatus = $ssh->getExitStatus();
            Log::info('Docker enable and start executed', [
                'exit_status' => $enableDockerStatus,
                'output' => substr($enableDockerOutput, 0, 500),
                'source' => 'panel'
            ]);
            sleep(3);

            $dockerCheck = $ssh->exec('docker info > /dev/null 2>&1; echo $?');
            $dockerCheck = trim($dockerCheck);

            if ($dockerCheck !== '0') {
                throw new RuntimeException(
                    "Docker daemon could not be started. " .
                    "Start output: " . substr($startDockerOutput, 0, 400) . ". " .
                    "Enable output: " . substr($enableDockerOutput, 0, 400)
                );
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
     * Ожидание освобождения блокировки apt/dpkg на сервере (другой процесс apt может обновлять пакеты).
     *
     * @param SSH2 $ssh
     * @param int $timeoutSeconds Максимум ждать (секунды)
     * @return void
     */
    private function waitForAptLock(SSH2 $ssh, int $timeoutSeconds = 300): void
    {
        $interval = 15;
        $elapsed = 0;
        while ($elapsed < $timeoutSeconds) {
            // fuser возвращает 0, если процесс держит файл; 1 — если никто не держит
            $check = trim($ssh->exec('sudo fuser /var/lib/dpkg/lock-frontend /var/lib/dpkg/lock /var/lib/apt/lists/lock 2>/dev/null; echo $?'));
            $lockHeld = ($check === '0');
            if (!$lockHeld) {
                Log::info('Apt/dpkg lock is free', ['source' => 'panel', 'waited' => $elapsed]);
                return;
            }
            Log::info('Waiting for apt/dpkg lock', ['source' => 'panel', 'elapsed' => $elapsed, 'timeout' => $timeoutSeconds]);
            sleep($interval);
            $elapsed += $interval;
        }
        Log::warning('Apt lock wait timeout reached, proceeding anyway', ['source' => 'panel', 'timeout' => $timeoutSeconds]);
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
            $port = $serverDto->ssh_port ?? 22;
            $ssh = new SSH2($serverDto->ip, $port);
            $ssh->setTimeout(100000);

            if (!$ssh->login($serverDto->login, $serverDto->password)) {
                throw new RuntimeException('SSH authentication failed');
            }

            return $ssh;
        } catch (Exception $e) {
            Log::error('SSH connection failed', [
                'source' => 'panel',
                'ip' => $serverDto->ip,
                'port' => $serverDto->ssh_port ?? 22,
                'error' => $e->getMessage()
            ]);
            throw new RuntimeException('SSH connection failed: ' . $e->getMessage());
        }
    }

    /**
     * SSH + SFTP: автоустановка локального SOCKS5 (sing-box + wgcf) в WARP на сервере панели.
     * Скрипты: scripts/marzban-warp-socks-install.sh, scripts/marzban-warp-socks-config.py
     *
     * @return string Вывод на удалённой машине (лог установки)
     * @throws RuntimeException
     */
    public function installWarpSocksOnServer(Panel $panel, int $port): string
    {
        if ($panel->panel !== Panel::MARZBAN) {
            throw new RuntimeException('Автоустановка WARP доступна только для Marzban.');
        }
        $panel->load('server');
        if (! $panel->server) {
            throw new RuntimeException('К панели не привязан сервер (нужен SSH).');
        }
        if ($port < 1 || $port > 65535) {
            throw new RuntimeException('Некорректный порт SOCKS.');
        }
        $installPath = base_path('scripts/marzban-warp-socks-install.sh');
        $configPyPath = base_path('scripts/marzban-warp-socks-config.py');
        if (! is_readable($installPath) || ! is_readable($configPyPath)) {
            throw new RuntimeException('Не найдены scripts/marzban-warp-socks-install.sh или scripts/marzban-warp-socks-config.py в каталоге приложения.');
        }

        $serverDto = ServerFactory::fromEntity($panel->server);
        $ssh = $this->connectSshAdapter($serverDto);
        $oldTimeout = $ssh->getTimeout() ?? 100000;
        $ssh->setTimeout(600);

        try {
            $sftp = new SFTP($serverDto->ip, $serverDto->ssh_port ?? 22);
            if (! $sftp->login($serverDto->login, $serverDto->password)) {
                throw new RuntimeException('SFTP: не удалось войти (проверьте логин/пароль сервера).');
            }
            $remoteSh = '/tmp/marzban-warp-socks-install.sh';
            $remotePy = '/tmp/marzban-warp-socks-config.py';
            if (! $sftp->put($remoteSh, $installPath, SFTP::SOURCE_LOCAL_FILE)) {
                throw new RuntimeException('Не удалось загрузить install-скрипт на сервер.');
            }
            if (! $sftp->put($remotePy, $configPyPath, SFTP::SOURCE_LOCAL_FILE)) {
                throw new RuntimeException('Не удалось загрузить config helper на сервер.');
            }
            $ssh->exec('chmod 700 '.escapeshellarg($remoteSh).' 2>&1');
            $ssh->exec('chmod 600 '.escapeshellarg($remotePy).' 2>&1');
            $out = $ssh->exec('CONFIG_HELPER='.escapeshellarg($remotePy).' bash '.escapeshellarg($remoteSh).' '.(int) $port.' 2>&1');
            if ($ssh->getExitStatus() !== 0) {
                $msg = trim($out) !== '' ? $out : 'код выхода '.$ssh->getExitStatus();
                throw new RuntimeException('Автоустановка WARP на ноде не завершена: '.Str::limit($msg, 4000, '…'));
            }

            Log::info('WARP SOCKS auto-install done', [
                'source' => 'panel',
                'panel_id' => $panel->id,
                'port' => $port,
            ]);

            return $out;
        } finally {
            $ssh->setTimeout($oldTimeout);
        }
    }

    /**
     * Установка WARP (SOCKS) на ноде, сохранение полей панели и переприменение Xray-конфига.
     * Предполагается вызов из app()->terminating() после HTTP-ответа, чтобы не держать Cloudflare/браузер минуты.
     */
    public function installWarpSocksOnNodeAndReapplyConfig(int $panelId, int $port, bool $enableWarpRouting): void
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }
        $panel = Panel::with('server')->find($panelId);
        if (! $panel instanceof Panel) {
            Log::warning('WARP (фон): панель не найдена', ['panel_id' => $panelId, 'source' => 'panel']);

            return;
        }
        $installLog = '';
        try {
            $installLog = $this->installWarpSocksOnServer($panel, $port);
        } catch (\Throwable $e) {
            Log::error('WARP (фон): автоустановка на ноде', [
                'source' => 'panel',
                'panel_id' => $panelId,
                'error' => $e->getMessage(),
            ]);

            return;
        }
        try {
            $panel->refresh();
            // Marzban у нас в Docker; 127.0.0.1 в Xray = loopback контейнера, не sing-box на хосте
            $panel->warp_socks_host = (string) config('panel.warp_default_socks_host', '172.17.0.1');
            $panel->warp_socks_port = $port;
            if ($enableWarpRouting) {
                $panel->warp_routing_enabled = true;
                $panel->warp_routing_all = false;
            }
            $panel->save();
            (new PanelStrategy($panel->panel))->reapplyCurrentConfiguration($panel->id);
        } catch (\Throwable $e) {
            Log::error('WARP (фон): переприменение конфига Marzban', [
                'source' => 'panel',
                'panel_id' => $panelId,
                'error' => $e->getMessage(),
            ]);

            return;
        }
        Log::info('WARP (фон): установка и конфиг применены', [
            'source' => 'panel',
            'panel_id' => $panelId,
            'port' => $port,
            'install_log_len' => strlen($installLog),
        ]);
    }

    /**
     * С ноды (SSH) проверяем, отвечает ли SOCKS5 по адресу из настроек панели
     * (тот же хост/порт, что уходит в Xray — важно для Docker: не 127.0.0.1 из контейнера).
     *
     * @return array{ok: bool, message: string, detail: string}
     */
    public function checkWarpSocksOnNode(Panel $panel): array
    {
        if ($panel->panel !== Panel::MARZBAN) {
            return [
                'ok' => false,
                'message' => 'Проверка WARP только для панелей Marzban.',
                'detail' => '',
            ];
        }
        $panel->load('server');
        if (! $panel->server) {
            return [
                'ok' => false,
                'message' => 'К панели не привязан сервер — нет SSH для проверки на ноде.',
                'detail' => '',
            ];
        }

        $defHost = (string) config('panel.warp_default_socks_host', '127.0.0.1');
        $host = trim((string) ($panel->warp_socks_host ?: $defHost));
        if ($host === '') {
            $host = $defHost;
        }
        $port = $panel->warp_socks_port ?? (int) config('panel.warp_default_socks_port', 40000);
        if ($port < 1 || $port > 65535) {
            $port = (int) config('panel.warp_default_socks_port', 40000);
        }
        $endpoint = $host.':'.$port;

        $serverDto = ServerFactory::fromEntity($panel->server);
        $ssh = $this->connectSshAdapter($serverDto);
        $oldTimeout = $ssh->getTimeout() ?? 100000;
        $ssh->setTimeout(45);
        $detail = '';
        try {
            $checkCurl = (string) $ssh->exec('command -v curl >/dev/null 2>&1 && echo OK || echo NO');
            if (trim($checkCurl) !== 'OK') {
                return [
                    'ok' => false,
                    'message' => 'На ноде нет команды curl — установите curl или проверяйте SOCKS вручную с сервера.',
                    'detail' => trim($checkCurl),
                ];
            }
            $remoteLine = 'curl -sS -o /dev/null -w "%{http_code}" --connect-timeout 8 --max-time 25 --socks5-hostname '
                .escapeshellarg($endpoint)
                .' https://www.cloudflare.com/cdn-cgi/trace 2>&1; echo; echo "ssh_exit:$?"';
            $detail = (string) $ssh->exec($remoteLine);
        } finally {
            $ssh->setTimeout($oldTimeout);
        }

        $detailTrim = trim($detail);
        $lines = preg_split('/\R/', $detailTrim);
        $firstLine = isset($lines[0]) ? trim((string) $lines[0]) : '';
        $httpCode = 0;
        if (preg_match('/^(\d{3})$/', trim($firstLine), $m)) {
            $httpCode = (int) $m[1];
        }
        $ok = $httpCode >= 200 && $httpCode < 400;

        if ($ok) {
            return [
                'ok' => true,
                'message' => 'SOCKS '.$endpoint.' на ноде отвечает (HTTP '.$httpCode.' через прокси). WARP-магистраль доступна с точки зрения сервера.',
                'detail' => Str::limit($detailTrim, 500, '…'),
            ];
        }

        $hint = ' Проверьте сервис на ноде, хост/порт (для Docker — IP хоста, не 127.0.0.1) и фаервол.';
        if ($httpCode === 0) {
            return [
                'ok' => false,
                'message' => 'SOCKS '.$endpoint.' не дал ожидаемого HTTP-ответа. Возможны таймауты или порт закрыт.'.$hint,
                'detail' => Str::limit($detailTrim, 500, '…'),
            ];
        }

        return [
            'ok' => false,
            'message' => 'Запрос через SOCKS завершился с HTTP '.$httpCode.'.'.$hint,
            'detail' => Str::limit($detailTrim, 500, '…'),
        ];
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
     * Бесплатный ключ с split_traffic_across_slots: общий пул traffic_limit на всех слотах Marzban.
     * Остаток поровну делится между слотами; на каждой панели data_limit = used_i + доля_остатка,
     * чтобы не «отваливались» слоты по очереди при общем лимите 5 ГБ.
     */
    public function syncFreeKeySharedTrafficPool(KeyActivate $key): void
    {
        if (!$key->isFreeIssuedKey() || !($key->split_traffic_across_slots ?? false)) {
            return;
        }
        if (!$key->relationLoaded('keyActivateUsers')) {
            $key->load(['keyActivateUsers.serverUser.panel']);
        }
        $ordered = $key->keyActivateUsers->sortBy('id')->values();
        if ($ordered->isEmpty()) {
            return;
        }
        $poolCap = (int) ($key->traffic_limit ?? 0);
        if ($poolCap <= 0) {
            return;
        }
        $finishAtDb = (int) ($key->finish_at ?? 0);

        $slotRows = [];
        foreach ($ordered as $kau) {
            $su = $kau->serverUser;
            if (!$su || !$su->panel) {
                continue;
            }
            $panel = $su->panel;
            $panelType = $panel->panel ?? Panel::MARZBAN;
            if ($panelType === '') {
                $panelType = Panel::MARZBAN;
            }
            try {
                $panelStrategy = new PanelStrategy($panelType);
                $info = $panelStrategy->getSubscribeInfo($panel->id, $su->id);
                $slotRows[] = [
                    'panel_id' => $panel->id,
                    'server_user_id' => $su->id,
                    'used' => (int) ($info['used_traffic'] ?? 0),
                    'expire' => $info['expire'] ?? null,
                    'current_data_limit' => (int) ($info['data_limit'] ?? 0),
                ];
            } catch (\Throwable $e) {
                Log::warning('syncFreeKeySharedTrafficPool: getSubscribeInfo failed', [
                    'key_id' => $key->id,
                    'server_user_id' => $su->id,
                    'error' => $e->getMessage(),
                    'source' => 'panel',
                ]);
            }
        }
        if ($slotRows === []) {
            return;
        }

        $n = count($slotRows);
        $totalUsed = 0;
        foreach ($slotRows as $r) {
            $totalUsed += $r['used'];
        }
        $remainingTotal = max(0, $poolCap - $totalUsed);
        $shares = TrafficLimitHelper::distributeAcrossSlots($remainingTotal, $n);

        foreach ($slotRows as $i => $row) {
            $newLimit = $row['used'] + (int) ($shares[$i] ?? 0);
            if ($newLimit === $row['current_data_limit']) {
                continue;
            }
            $expire = $this->normalizeMarzbanExpireForUpdate($row['expire'], $finishAtDb);
            try {
                $this->updateUserDataLimitAndExpire((int) $row['panel_id'], (string) $row['server_user_id'], $expire, $newLimit, null);
            } catch (\Throwable $e) {
                Log::warning('syncFreeKeySharedTrafficPool: updateUser failed', [
                    'key_id' => $key->id,
                    'server_user_id' => $row['server_user_id'],
                    'error' => $e->getMessage(),
                    'source' => 'panel',
                ]);
            }
        }
    }

    public function updateUserDataLimitAndExpire(int $panel_id, string $user_id, int $expire, int $data_limit, ?array $inbounds = null): void
    {
        $panel = $this->updateMarzbanToken($panel_id);
        $marzbanApi = new MarzbanAPI($panel->api_address);
        $marzbanApi->updateUser($panel->auth_token, $user_id, $expire, $data_limit, $inbounds);
    }

    private function normalizeMarzbanExpireForUpdate($expire, int $finishAtFromDb): int
    {
        if ($expire !== null && $expire !== '') {
            $exp = (int) $expire;
            if ($exp > 2147483647) {
                $exp = (int) ($exp / 1000);
            }
            if ($exp > 0) {
                return $exp;
            }
        }
        if ($finishAtFromDb > 0) {
            return $finishAtFromDb;
        }

        return time() + 86400;
    }

    /**
     * @throws GuzzleException
     * @throws Exception
     */
    public function getServerStats(): void
    {
        try {
            // По одной порции панелей — иначе при сотнях панелей коллекция + ответы API раздувают память
            Panel::query()
                ->where('panel_status', Panel::PANEL_CONFIGURED)
                ->where('panel', Panel::MARZBAN)
                ->orderBy('id')
                ->chunk(30, function ($panels) {
                    foreach ($panels as $panel) {
                        $panel = $this->updateMarzbanToken($panel->id);
                        $marzbanApi = new MarzbanAPI($panel->api_address);
                        $serverStats = $marzbanApi->getServerStats($panel->auth_token);
                        $statistics = json_encode($serverStats);
                        ServerMonitoring::create([
                            'panel_id' => $panel->id,
                            'statistics' => $statistics,
                        ]);
                        unset($serverStats, $statistics, $marzbanApi);
                    }
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
            // Проверяем, является ли путь локальным (на сервере Laravel) или удаленным (на сервере Marzban)
            $isLocalPath = str_starts_with($panel->tls_certificate_path, storage_path()) || 
                          str_starts_with($panel->tls_certificate_path, base_path());
            
            if ($isLocalPath) {
                // Для локальных путей проверяем существование файлов
                $certExists = @file_exists($panel->tls_certificate_path);
                $keyExists = @file_exists($panel->tls_key_path);
                
                // Если файлы существуют - используем их
                if ($certExists && $keyExists) {
                    return [
                        'cert' => $panel->tls_certificate_path,
                        'key' => $panel->tls_key_path
                    ];
                }
            } else {
                // Для удаленных путей (на сервере Marzban) не проверяем существование
                // так как open_basedir не позволяет это сделать
                // Полагаемся на то, что файлы были скопированы через SFTP
                return [
                    'cert' => $panel->tls_certificate_path,
                    'key' => $panel->tls_key_path
                ];
            }
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
    private function getSecuritySettings(?Panel $panel = null, bool $forceTls = false): array
    {
        // Если панель указана и у неё включен TLS - используем TLS
        // Или если forceTls = true (для протоколов, которые требуют TLS, например Trojan)
        if ($panel && ($panel->use_tls || $forceTls)) {
            // Если forceTls = true, но use_tls = false, проверяем наличие сертификатов
            if ($forceTls && !$panel->use_tls) {
                $certPaths = $this->getTlsCertificatePaths($panel);
                // Если сертификаты не найдены, возвращаем none (протокол не будет работать)
                $isLocalPath = str_starts_with($certPaths['cert'], storage_path()) || 
                              str_starts_with($certPaths['cert'], base_path());
                if ($isLocalPath) {
                    $certExists = @file_exists($certPaths['cert']);
                    $keyExists = @file_exists($certPaths['key']);
                    if (!$certExists || !$keyExists) {
                        return ['security' => 'none'];
                    }
                }
            }
            
            $certPaths = $this->getTlsCertificatePaths($panel);
            
            // Проверяем, является ли путь локальным или удаленным
            $isLocalPath = str_starts_with($certPaths['cert'], storage_path()) || 
                          str_starts_with($certPaths['cert'], base_path());
            
            if ($isLocalPath) {
                // Для локальных путей проверяем существование файлов
                $certExists = @file_exists($certPaths['cert']);
                $keyExists = @file_exists($certPaths['key']);
                
                if (!$certExists || !$keyExists) {
                    Log::error('TLS сертификаты не найдены по указанным путям', [
                        'panel_id' => $panel->id,
                        'cert_path' => $certPaths['cert'],
                        'key_path' => $certPaths['key'],
                        'cert_exists' => $certExists,
                        'key_exists' => $keyExists,
                        'source' => 'panel'
                    ]);
                    
                    // Возвращаем none вместо TLS, если файлы не найдены
                    return [
                        'security' => 'none'
                    ];
                }
            }
            
            // Получаем домен из адреса панели для SNI
            $sni = null;
            if ($panel && $panel->panel_adress) {
                $parsedUrl = parse_url($panel->panel_adress);
                if (isset($parsedUrl['host'])) {
                    $sni = $parsedUrl['host'];
                } elseif (str_contains($panel->panel_adress, '://')) {
                    // Если адрес содержит протокол, извлекаем домен
                    $parts = parse_url($panel->panel_adress);
                    $sni = $parts['host'] ?? null;
                } else {
                    // Если адрес без протокола, используем как есть
                    $sni = str_replace(['http://', 'https://', '/dashboard'], '', $panel->panel_adress);
                    $sni = trim($sni, '/');
                }
            }
            
            $tlsSettings = [
                'allowInsecure' => false, // false для валидных сертификатов (Let's Encrypt)
                'minVersion' => '1.2',
                'certificates' => [
                    [
                        'certificateFile' => $certPaths['cert'],
                        'keyFile' => $certPaths['key']
                    ]
                ]
            ];
            
            // Добавляем SNI, если домен определен
            if ($sni) {
                $tlsSettings['serverName'] = $sni;
            }
            
            return [
                'security' => 'tls',
                'tlsSettings' => $tlsSettings
            ];
        }

        // По умолчанию используем none для обратной совместимости
        
        return [
            'security' => 'none'
        ];
    }

    /**
     * Список domain для маршрута WARP (узкий режим): только Gemini (config/vpn.php) + extras из .env.
     *
     * @return list<string>
     */
    private function getWarpSelectiveDomainRules(): array
    {
        $list = [];
        foreach (config('vpn.gemini_warp_full_hosts', []) as $h) {
            $h = is_string($h) ? trim($h) : '';
            if ($h === '') {
                continue;
            }
            $list[] = 'full:'.$h;
        }
        foreach (config('vpn.gemini_warp_domain_suffixes', []) as $s) {
            $s = is_string($s) ? trim($s) : '';
            if ($s === '') {
                continue;
            }
            $list[] = 'domain:'.ltrim($s, '.');
        }
        foreach (config('panel.warp_routing_geosite_extra', []) as $g) {
            $g = is_string($g) ? trim($g) : '';
            if ($g === '') {
                continue;
            }
            $list[] = $g;
        }
        foreach (config('panel.warp_routing_domain_extra', []) as $d) {
            $d = is_string($d) ? trim($d) : '';
            if ($d === '') {
                continue;
            }
            if (preg_match('/^(geosite:|domain:|full:|regexp:|keyword:)/i', $d) === 1) {
                $list[] = $d;
            } else {
                $list[] = 'domain:' . ltrim($d, '.');
            }
        }

        return array_values(array_unique($list));
    }

    /**
     * Построение базовой конфигурации (общая часть)
     *
     * @param Panel|null $panel Панель для проверки настроек прокси
     * @return array
     */
    private function buildBaseConfig(?Panel $panel = null): array
    {
        $directOutbound = [
            'protocol' => 'freedom',
            'tag' => 'DIRECT',
            'settings' => [
                'domainStrategy' => 'UseIPv4',
            ],
        ];

        $routingRules = [
            [
                'type' => 'field',
                'ip' => [
                    'geoip:private',
                ],
                'outboundTag' => 'DIRECT',
            ],
        ];

        $outbounds = [$directOutbound];

        if ($panel && $panel->warp_routing_enabled) {
            $defHost = (string) config('panel.warp_default_socks_host', '127.0.0.1');
            $host = trim((string) ($panel->warp_socks_host ?: $defHost));
            if ($host === '') {
                $host = $defHost;
            }
            $port = $panel->warp_socks_port ?? (int) config('panel.warp_default_socks_port', 40000);
            if ($port < 1 || $port > 65535) {
                $port = (int) config('panel.warp_default_socks_port', 40000);
            }

            $warpOutbound = [
                'protocol' => 'socks',
                'tag' => 'WARP',
                'settings' => [
                    'servers' => [
                        [
                            'address' => $host,
                            'port' => $port,
                            // иначе часть трафика (QUIC, VoIP) без UDP через SOCKS — таймауты/обрывы
                            'udp' => true,
                        ],
                    ],
                ],
            ];

            // Полный туннель: при сбое «0.0.0.0/0»/доменного матча Xray берёт ПЕРВЫЙ outbound — должен быть WARP, не freedom.
            if (! empty($panel->warp_routing_all)) {
                $outbounds = [ $warpOutbound, $directOutbound ];
            } else {
                $outbounds[] = $warpOutbound;
            }

            if (! empty($panel->warp_routing_all)) {
                $routingRules[] = [
                    'type' => 'field',
                    'ip' => [
                        '0.0.0.0/0',
                        '::/0',
                    ],
                    'outboundTag' => 'WARP',
                ];
            } else {
                $routingRules[] = [
                    'type' => 'field',
                    'domain' => $this->getWarpSelectiveDomainRules(),
                    'outboundTag' => 'WARP',
                ];
            }
        }

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
                    ], $this->getSecuritySettings($panel, true)), // Trojan всегда требует TLS
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
                    "streamSettings" => array_merge([
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
                    ], $this->getSecuritySettings($panel)),
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
    /**
     * SS + Trojan + VLESS-WS (без VMess). Для комбинированного пресета с двумя REALITY.
     */
    private function buildStableBasicInbounds(?Panel $panel = null): array
    {
        return [
            [
                'tag' => 'VLESS-WS',
                'listen' => '0.0.0.0',
                'port' => 2087,
                'protocol' => 'vless',
                'settings' => ['clients' => [], 'decryption' => 'none', 'level' => 0],
                'streamSettings' => array_merge([
                    'network' => 'ws',
                    'wsSettings' => ['path' => '/vless'],
                ], $this->getSecuritySettings($panel)),
                'sniffing' => ['enabled' => true, 'destOverride' => ['http', 'tls']],
            ],
            [
                'tag' => 'TROJAN-WS',
                'listen' => '0.0.0.0',
                'port' => 2097,
                'protocol' => 'trojan',
                'settings' => ['clients' => [], 'level' => 0],
                'streamSettings' => array_merge([
                    'network' => 'ws',
                    'wsSettings' => ['path' => '/trojan'],
                ], $this->getSecuritySettings($panel, true)),
                'sniffing' => ['enabled' => true, 'destOverride' => ['http', 'tls']],
            ],
            [
                'tag' => 'Shadowsocks-TCP',
                'listen' => '0.0.0.0',
                'port' => 8388,
                'protocol' => 'shadowsocks',
                'settings' => ['clients' => [], 'network' => 'tcp,udp', 'level' => 0],
            ],
        ];
    }

    /**
     * Только Trojan + SS (без VLESS-WS). Для смешанного пресета, где все VLESS — REALITY.
     */
    private function buildStableBasicInboundsForMixed(?Panel $panel = null): array
    {
        return [
            [
                'tag' => 'TROJAN-WS',
                'listen' => '0.0.0.0',
                'port' => 2097,
                'protocol' => 'trojan',
                'settings' => ['clients' => [], 'level' => 0],
                'streamSettings' => array_merge([
                    'network' => 'ws',
                    'wsSettings' => ['path' => '/trojan'],
                ], $this->getSecuritySettings($panel, true)),
                'sniffing' => ['enabled' => true, 'destOverride' => ['http', 'tls']],
            ],
            [
                'tag' => 'Shadowsocks-TCP',
                'listen' => '0.0.0.0',
                'port' => 8388,
                'protocol' => 'shadowsocks',
                'settings' => ['clients' => [], 'network' => 'tcp,udp', 'level' => 0],
            ],
        ];
    }

    /**
     * Два стабильных VLESS REALITY: TCP (Microsoft) и TCP ALT (Google).
     */
    private function buildTwoRealityInbounds(string $privateKey, string $shortId, string $xhttpShortId): array
    {
        return [
            [
                'tag' => 'VLESS TCP REALITY',
                'listen' => '0.0.0.0',
                'port' => 8443,
                'protocol' => 'vless',
                'settings' => ['clients' => [], 'decryption' => 'none', 'level' => 0],
                'streamSettings' => [
                    'network' => 'tcp',
                    'tcpSettings' => ['acceptProxyProtocol' => false, 'header' => ['type' => 'none']],
                    'security' => 'reality',
                    'realitySettings' => [
                        'show' => false,
                        'dest' => 'www.microsoft.com:443',
                        'xver' => 0,
                        'serverNames' => ['www.microsoft.com', 'microsoft.com', 'www.cloudflare.com', 'cloudflare.com'],
                        'privateKey' => $privateKey,
                        'shortIds' => ['', $shortId],
                    ],
                ],
                'sniffing' => ['enabled' => true, 'destOverride' => ['http', 'tls', 'quic']],
            ],
            [
                'tag' => 'VLESS TCP REALITY ALT',
                'listen' => '0.0.0.0',
                'port' => 2083,
                'protocol' => 'vless',
                'settings' => ['clients' => [], 'decryption' => 'none', 'level' => 0],
                'streamSettings' => [
                    'network' => 'tcp',
                    'security' => 'reality',
                    'realitySettings' => [
                        'show' => false,
                        'dest' => 'www.google.com:443',
                        'xver' => 0,
                        'serverNames' => ['www.google.com', 'google.com', 'www.amazon.com', 'amazon.com'],
                        'privateKey' => $privateKey,
                        'shortIds' => ['', $xhttpShortId],
                    ],
                ],
                'sniffing' => ['enabled' => true, 'destOverride' => ['http', 'tls', 'quic']],
            ],
        ];
    }

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
            // VLESS XHTTP REALITY - комбинация Reality + XHTTP для обхода блокировок
            // Порт 8444 (альтернативный HTTPS)
            [
                "tag" => "VLESS XHTTP REALITY",
                "listen" => "0.0.0.0",
                "port" => 8444,
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
     * Синхронизация Host Settings: выставить address = домен панели для всех inbounds,
     * чтобы в ссылках подписки (vless:// и т.д.) отображался домен, а не IP.
     */
    private function syncHostsAddressToPanelDomain(Panel $panel, MarzbanAPI $marzbanApi): void
    {
        // Домен для Marzban Host Settings: из адреса панели в БД (часто совпадает с зеркалом конфигов).
        $domain = $panel->formatted_address;
        if (empty($domain) || str_starts_with($domain, 'http')) {
            $domain = parse_url($panel->panel_adress ?? '', PHP_URL_HOST);
        }
        if (empty($domain)) {
            $domain = parse_url(rtrim((string) config('app.config_public_url'), '/'), PHP_URL_HOST);
        }
        if (empty($domain)) {
            Log::debug('Panel has no domain for hosts sync', ['panel_id' => $panel->id, 'source' => 'panel']);
            return;
        }

        $hosts = $marzbanApi->getHosts($panel->auth_token);
        if (empty($hosts)) {
            Log::debug('No hosts returned from Marzban, skip address sync', ['panel_id' => $panel->id, 'source' => 'panel']);
            return;
        }

        $updated = [];
        foreach ($hosts as $inboundTag => $configs) {
            if (!is_array($configs)) {
                continue;
            }
            foreach ($configs as $i => $item) {
                if (is_array($item) && isset($item['address'])) {
                    $hosts[$inboundTag][$i]['address'] = $domain;
                    $updated[$inboundTag] = true;
                }
            }
        }
        if (empty($updated)) {
            return;
        }

        try {
            $marzbanApi->updateHosts($panel->auth_token, $hosts);
            Log::info('Hosts address synced to panel domain', [
                'panel_id' => $panel->id,
                'domain' => $domain,
                'inbounds' => array_keys($updated),
                'source' => 'panel',
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to sync hosts address to domain', [
                'panel_id' => $panel->id,
                'domain' => $domain,
                'error' => $e->getMessage(),
                'source' => 'panel',
            ]);
        }
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

            // Выставляем в Host Settings address = домен панели, чтобы в ссылках подписки был домен, а не IP
            $this->syncHostsAddressToPanelDomain($panel, $marzbanApi);

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
     * Обновление конфигурации панели — стабильная REALITY: только REALITY inbounds (те же порты 8443, 9443, 8444, 2083 и т.д.),
     * без VMess, Trojan, Shadowsocks.
     *
     * @param int $panel_id
     * @return void
     * @throws GuzzleException
     */
    public function updateConfigurationRealityStable(int $panel_id): void
    {
        $panel = self::updateMarzbanToken($panel_id);

        Log::info('Updating configuration to stable REALITY (REALITY only, same ports)', [
            'panel_id' => $panel_id,
            'source' => 'panel',
        ]);

        $realityKeys = $this->getOrGenerateRealityKeys($panel);

        $json_config = $this->buildBaseConfig($panel);
        $json_config['inbounds'] = $this->buildRealityInbounds(
            $realityKeys['private_key'],
            $realityKeys['short_id'],
            $realityKeys['grpc_short_id'],
            $realityKeys['xhttp_short_id']
        );

        $this->applyConfiguration($panel, $json_config, Panel::CONFIG_TYPE_REALITY_STABLE);
    }

    /**
     * Обновление конфигурации панели — смешанный пресет: SS + Trojan + VLESS (VLESS-WS) + 2 стабильных VLESS REALITY.
     * Без VMess. Для теста на проде.
     *
     * @param int $panel_id
     * @return void
     * @throws GuzzleException
     */
    public function updateConfigurationMixed(int $panel_id): void
    {
        $panel = self::updateMarzbanToken($panel_id);

        Log::info('Updating configuration to mixed (SS + Trojan + 3 VLESS REALITY)', [
            'panel_id' => $panel_id,
            'source' => 'panel',
        ]);

        $realityKeys = $this->getOrGenerateRealityKeys($panel);

        $allRealityInbounds = $this->buildRealityInbounds(
            $realityKeys['private_key'],
            $realityKeys['short_id'],
            $realityKeys['grpc_short_id'],
            $realityKeys['xhttp_short_id']
        );
        // Смешанный пресет: SS + Trojan + 3 VLESS REALITY (8443, 9443, 2083 — без 8444)
        $threeReality = [
            $allRealityInbounds[0], // VLESS TCP REALITY 8443
            $allRealityInbounds[1], // VLESS GRPC REALITY 9443
            $allRealityInbounds[3], // VLESS TCP REALITY ALT 2083
        ];

        $json_config = $this->buildBaseConfig($panel);
        $json_config['inbounds'] = array_merge(
            $this->buildStableBasicInboundsForMixed($panel),
            $threeReality
        );

        $this->applyConfiguration($panel, $json_config, Panel::CONFIG_TYPE_MIXED);
    }

    /**
     * Повторно применить текущий пресет конфигурации (после смены флагов WARP и т.п.).
     *
     * @throws GuzzleException
     */
    public function reapplyConfigurationForPanel(int $panel_id): void
    {
        $panel = Panel::query()->findOrFail($panel_id);
        $type = $panel->config_type ?? Panel::CONFIG_TYPE_MIXED;

        switch ($type) {
            case Panel::CONFIG_TYPE_STABLE:
                $this->updateConfigurationStable($panel_id);
                break;
            case Panel::CONFIG_TYPE_REALITY:
                $this->updateConfigurationReality($panel_id);
                break;
            case Panel::CONFIG_TYPE_REALITY_STABLE:
                $this->updateConfigurationRealityStable($panel_id);
                break;
            case Panel::CONFIG_TYPE_MIXED:
                $this->updateConfigurationMixed($panel_id);
                break;
            default:
                $this->updateConfigurationMixed($panel_id);
                break;
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
     * Inbounds и proxies для создания пользователя в зависимости от типа конфигурации панели.
     * Возвращает [ inbounds, proxies ] для MarzbanAPI::createUser.
     *
     * @return array{0: array<string, array<string>>, 1: array<string, array>}
     */
    private function getInboundsAndProxiesForPanel(Panel $panel): array
    {
        $vlessId = Str::uuid()->toString();
        $vmessId = Str::uuid()->toString();
        $trojanPassword = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 16);
        $ssPassword = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 16);

        $configType = $panel->config_type ?? Panel::CONFIG_TYPE_STABLE;

        if ($configType === Panel::CONFIG_TYPE_REALITY_STABLE) {
            // Только REALITY inbounds (4 протокола)
            $inbounds = [
                'vless' => [
                    'VLESS TCP REALITY',
                    'VLESS GRPC REALITY',
                    'VLESS XHTTP REALITY',
                    'VLESS TCP REALITY ALT',
                ],
            ];
            $proxies = [
                'vless' => ['id' => $vlessId],
            ];
            return [$inbounds, $proxies];
        }

        if ($configType === Panel::CONFIG_TYPE_MIXED) {
            // SS + Trojan + 3 VLESS REALITY (без VLESS-WS)
            $inbounds = [
                'vless' => ['VLESS TCP REALITY', 'VLESS GRPC REALITY', 'VLESS TCP REALITY ALT'],
                'trojan' => ['TROJAN-WS'],
                'shadowsocks' => ['Shadowsocks-TCP'],
            ];
            $proxies = [
                'vless' => ['id' => $vlessId],
                'trojan' => ['password' => $trojanPassword],
                'shadowsocks' => ['password' => $ssPassword],
            ];
            return [$inbounds, $proxies];
        }

        if ($configType === Panel::CONFIG_TYPE_REALITY) {
            // REALITY + стабильные (VMess, Trojan, SS, VLESS-WS)
            $inbounds = [
                'vless' => [
                    'VLESS-WS',
                    'VLESS TCP REALITY',
                    'VLESS GRPC REALITY',
                    'VLESS XHTTP REALITY',
                    'VLESS TCP REALITY ALT',
                ],
                'vmess' => ['VMESS-WS'],
                'trojan' => ['TROJAN-WS'],
                'shadowsocks' => ['Shadowsocks-TCP'],
            ];
            $proxies = [
                'vless' => ['id' => $vlessId],
                'vmess' => ['id' => $vmessId],
                'trojan' => ['password' => $trojanPassword],
                'shadowsocks' => ['password' => $ssPassword],
            ];
            return [$inbounds, $proxies];
        }

        // CONFIG_TYPE_STABLE или неизвестный — только стабильные протоколы (4)
        $inbounds = [
            'vmess' => ['VMESS-WS'],
            'vless' => ['VLESS-WS'],
            'trojan' => ['TROJAN-WS'],
            'shadowsocks' => ['Shadowsocks-TCP'],
        ];
        $proxies = [
            'vmess' => ['id' => $vmessId],
            'vless' => ['id' => $vlessId],
            'trojan' => ['password' => $trojanPassword],
            'shadowsocks' => ['password' => $ssPassword],
        ];
        return [$inbounds, $proxies];
    }

    /**
     * Добавление пользователя и протоколов подключения на панель.
     *
     * @param int $panel_id
     * @param int $userTgId
     * @param int $data_limit
     * @param int $expire
     * @param string $key_activate_id
     * @param array $options
     * @return ServerUser
     * @throws GuzzleException
     */
    public function addServerUser(int $panel_id, int $userTgId, int $data_limit, int $expire, string $key_activate_id, array $options = []): ServerUser
    {
        try {
            // Тестовые ключи (например из panels:check-errors) не существуют в БД — не требуем KeyActivate
            $isTestKey = str_starts_with($key_activate_id, 'test-key-');
            $key_activate = $isTestKey
                ? null
                : KeyActivate::query()->where('id', $key_activate_id)->firstOrFail();

            $panel = self::updateMarzbanToken($panel_id);
            if (!$panel->server) {
                throw new RuntimeException('Server not found for panel');
            }

            $marzbanApi = new MarzbanAPI($panel->api_address);
            $userId = Str::uuid();
            $maxConnections = $options['max_connections'] ?? config('panel.max_connections', 4);

            [$inbounds, $proxies] = $this->getInboundsAndProxiesForPanel($panel);

            $userData = $marzbanApi->createUser(
                $panel->auth_token,
                $userId,
                $data_limit,
                $expire,
                $maxConnections,
                $inbounds,
                $proxies
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

            // Создаем запись key_activate_user только для реальных ключей (не для теста проверки панели)
            if ($key_activate !== null) {
                $keyActivateUserService = new KeyActivateUserService();
                $locationId = $panel->server && isset($panel->server->location_id) ? $panel->server->location_id : null;
                try {
                    $keyActivateUserService->create(
                        $serverUser->id,
                        $key_activate_id,
                        $locationId
                    );
                } catch (Exception $e) {
                    // Если не удалось создать key_activate_user, удаляем созданного пользователя
                    $serverUser->delete();
                    throw new RuntimeException('Failed to create key activate user: ' . $e->getMessage());
                }
            }

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

            // Загружаем пользователя сервера по ID (при одиночном переносе передаётся server_user_id выбранного слота)
            /** @var ServerUser $serverUser */
            $serverUser = ServerUser::select('id', 'panel_id', 'keys')
                ->where('id', $serverUser_id)
                ->firstOrFail();

            $keyActivateUser = KeyActivateUser::select('key_activate_id')
                ->where('server_user_id', $serverUser_id)
                ->first();
            $key_activate = $keyActivateUser
                ? KeyActivate::select('id', 'user_tg_id', 'module_salesman_id', 'pack_salesman_id')->find($keyActivateUser->key_activate_id)
                : null;

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
            [$targetInbounds, $targetProxies] = $this->getInboundsAndProxiesForPanel($targetPanel);
            $newUser = $targetMarzbanApi->createUser(
                $targetPanel->auth_token,
                $serverUser->id,
                $userData['data_limit'] - $userData['used_traffic'] ?? 0,
                $userData['expire'] ?? 0,
                null,
                $targetInbounds,
                $targetProxies
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

                // Уведомление пользователю (модуль Bot-T или бот продавца) — та же логика, что в TelegramNotificationService
                if ($key_activate) {
                    $message = "⚠️ Ваш ключ доступа: " . "<code>{$key_activate->id}</code> " . "был перемещен на новый сервер!\n\n";
                    $message .= "🔗 Для продолжения работы:\n";
                    $message .= "• Заново вставьте ссылку-подключение в клиент VPN, или\n";
                    $message .= "• При выключенном VPN нажмите кнопку обновления конфигурации\n\n";
                    $message .= "\n" . \App\Helpers\UrlHelper::telegramConfigLinksHtml($key_activate->id);

                    try {
                        $keyActivateFull = KeyActivate::with(['moduleSalesman.botModule', 'packSalesman.salesman'])
                            ->find($key_activate->id);
                        if ($keyActivateFull) {
                            app(TelegramNotificationService::class)->sendToUser($keyActivateFull, $message);
                        }
                    } catch (Exception $e) {
                        Log::error('Ошибка при отправке уведомления о переносе ключа на другой сервер', [
                            'error' => $e->getMessage(),
                            'key_id' => $key_activate->id,
                            'source' => 'panel',
                        ]);
                    }
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
     * Перенос одного ключа на целевую панель БЕЗ обращения к исходной панели (для массового переноса при сломанной исходной панели).
     * Данные берутся из БД: traffic_limit и finish_at из key_activate.
     * Ссылка на конфиг (key_activate_id) не меняется — пользователю достаточно обновить подписку.
     *
     * @param int $sourcePanelId Исходная панель (только для проверки, API не вызывается)
     * @param int $targetPanelId Целевая панель (должна быть доступна)
     * @param string $keyActivateId UUID ключа
     * @return ServerUser
     * @throws RuntimeException
     */
    public function transferUserWithoutSourcePanel(int $sourcePanelId, int $targetPanelId, string $keyActivateId): ServerUser
    {
        $keyActivate = KeyActivate::select('id', 'traffic_limit', 'finish_at', 'user_tg_id', 'module_salesman_id', 'pack_salesman_id', 'split_traffic_across_slots')
            ->where('id', $keyActivateId)
            ->where('status', KeyActivate::ACTIVE)
            ->firstOrFail();

        // При мульти-провайдере у ключа несколько слотов — переносим тот, что на исходной панели
        $keyActivateUser = KeyActivateUser::query()
            ->where('key_activate_id', $keyActivateId)
            ->whereHas('serverUser', fn ($q) => $q->where('panel_id', $sourcePanelId))
            ->firstOrFail();
        $serverUser = ServerUser::where('id', $keyActivateUser->server_user_id)->firstOrFail();

        $targetPanel = $this->updateMarzbanToken($targetPanelId);
        $marzbanApi = new MarzbanAPI($targetPanel->api_address);

        $dataLimit = (int) ($keyActivate->traffic_limit ?? 0);
        if ($keyActivate->split_traffic_across_slots) {
            $maxSlots = max(1, (int) config('panel.max_provider_slots', 3));
            $parts = TrafficLimitHelper::distributeAcrossSlots($dataLimit, $maxSlots);
            $orderedIds = KeyActivateUser::where('key_activate_id', $keyActivateId)->orderBy('id')->pluck('id');
            $slotIndex = $orderedIds->search($keyActivateUser->id);
            if ($slotIndex === false) {
                $slotIndex = 0;
            }
            $dataLimit = (int) ($parts[$slotIndex] ?? 0);
        }
        // finish_at в БД — Unix timestamp (секунды), Marzban API ожидает то же
        $expire = (int) ($keyActivate->finish_at ?? (time() + 30 * 86400));
        if ($expire <= time()) {
            $expire = time() + 86400; // минимум 1 день, если дата уже прошла
        }

        [$targetInbounds, $targetProxies] = $this->getInboundsAndProxiesForPanel($targetPanel);
        $newUser = $marzbanApi->createUser(
            $targetPanel->auth_token,
            $serverUser->id,
            $dataLimit,
            $expire,
            null,
            $targetInbounds,
            $targetProxies
        );

        if (empty($newUser['links'])) {
            throw new RuntimeException("Панель не вернула ссылки для ключа {$keyActivateId}");
        }

        DB::transaction(function () use ($serverUser, $targetPanelId, $newUser) {
            $serverUser->panel_id = $targetPanelId;
            $serverUser->keys = is_array($newUser['links']) ? json_encode($newUser['links']) : $newUser['links'];
            $serverUser->save();
        });

        Log::info('Mass transfer: key moved without source panel', [
            'key_activate_id' => $keyActivateId,
            'source_panel' => $sourcePanelId,
            'target_panel' => $targetPanelId,
            'server_user_id' => $serverUser->id,
            'source' => 'panel',
        ]);

        return $serverUser->fresh();
    }

    /**
     * Список ID активных ключей на панели (для массового переноса).
     *
     * @param int $panelId
     * @return \Illuminate\Support\Collection list of key_activate.id
     */
    /**
     * Условие: ключ активен по статусу и не просрочен по дате (finish_at > now или null).
     */
    private function activeKeyScope($query): void
    {
        $query->where('key_activate.status', KeyActivate::ACTIVE)
            ->where(function ($q) {
                $q->whereNull('key_activate.finish_at')
                    ->orWhere('key_activate.finish_at', '>', time());
            });
    }

    /**
     * Список ID активных (и не просроченных) ключей на панели.
     */
    public function getActiveKeyIdsOnPanel(int $panelId): \Illuminate\Support\Collection
    {
        $q = KeyActivateUser::query()
            ->join('server_user', 'key_activate_user.server_user_id', '=', 'server_user.id')
            ->join('key_activate', 'key_activate_user.key_activate_id', '=', 'key_activate.id')
            ->where('server_user.panel_id', $panelId);
        $this->activeKeyScope($q);
        return $q->pluck('key_activate.id');
    }

    /**
     * Количество активных пользователей по каждой панели Marzban (для балансировки).
     *
     * @return array<int, int> [panel_id => count]
     */
    public function getActiveUserCountPerPanel(): array
    {
        $panelIds = Panel::query()
            ->where('panel', Panel::MARZBAN)
            ->where('panel_status', Panel::PANEL_CONFIGURED)
            ->pluck('id');

        if ($panelIds->isEmpty()) {
            return [];
        }

        $q = KeyActivateUser::query()
            ->join('server_user', 'key_activate_user.server_user_id', '=', 'server_user.id')
            ->join('key_activate', 'key_activate_user.key_activate_id', '=', 'key_activate.id')
            ->whereIn('server_user.panel_id', $panelIds);
        $this->activeKeyScope($q);
        $counts = $q->selectRaw('server_user.panel_id as panel_id, count(*) as cnt')
            ->groupBy('server_user.panel_id')
            ->pluck('cnt', 'panel_id')
            ->toArray();

        foreach ($panelIds as $id) {
            if (!isset($counts[$id])) {
                $counts[$id] = 0;
            }
        }

        return $counts;
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
            case KeyActivate::ACTIVATING:
                return 'ACTIVATING (Активация)';
            case KeyActivate::DELETED:
                return 'DELETED (Удален)';
            default:
                return "Unknown ({$statusCode})";
        }
    }
}
