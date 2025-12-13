<?php

namespace App\Services\Panel\marzban;

use App\Dto\Bot\BotModuleFactory;
use App\Dto\Server\ServerDto;
use App\Dto\Server\ServerFactory;
use App\Models\KeyActivate\KeyActivate;
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

            $commands = [
                'wget ' . self::INSTALL_SCRIPT_URL,
                'chmod +x install_marzban.sh',
                './install_marzban.sh ' . $host
            ];

            foreach ($commands as $command) {
                $result = $ssh->exec($command);

                if ($ssh->getExitStatus() !== 0) {
                    throw new RuntimeException("Command failed: $command");
                }
            }
        } catch (Exception $e) {
            Log::error('Panel installation failed', [
                'host' => $host,
                'error' => $e->getMessage(),
                'source' => 'panel'
            ]);
            throw new RuntimeException('Failed to install panel: ' . $e->getMessage());
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

            $info = [
                'used_traffic' => $userData['used_traffic'],
                'data_limit' => $userData['data_limit'],
                'expire' => $userData['expire'],
                'status' => $userData['status'],
            ];

            if ($userData['status'] !== 'active') {
                $serverUser->keyActivateUser->keyActivate->status = KeyActivate::EXPIRED;
                $serverUser->keyActivateUser->keyActivate->save();
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

            // Удаляем запись из БД
            $serverUser = ServerUser::query()->where('id', $user_id)->firstOrFail();

            // Удаляем связанную запись KeyActivateUser
            if ($serverUser->keyActivateUser) {
                Log::info('Deleting KeyActivateUser', ['key_activate_user_id' => $serverUser->keyActivateUser->id, 'source' => 'panel']);
                $serverUser->keyActivateUser->delete();
            }

            if (!$serverUser->delete()) {
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

            // Валидация ключей
            if (strlen($privateKey) < 40 || strlen($publicKey) < 40) {
                throw new RuntimeException("Invalid key length generated");
            }

            if (strlen($shortId) !== 16 || strlen($grpcShortId) !== 16) {
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
                'grpc_short_id' => $grpcShortId
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
                'grpc_short_id' => $panel->reality_grpc_short_id
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
        $panel->reality_keys_generated_at = now();
        $panel->save();

        Log::info('REALITY keys saved to database', [
            'panel_id' => $panel->id,
            'source' => 'panel'
        ]);

        return $keys;
    }

    /**
     * Построение базовой конфигурации (общая часть)
     *
     * @return array
     */
    private function buildBaseConfig(): array
    {
        return [
            "log" => [
                "loglevel" => "warning",
                "access" => "/var/lib/marzban/access.log",
                "error" => "/var/lib/marzban/error.log",
                "dnsLog" => true
            ],
            "outbounds" => [
                [
                    "protocol" => "freedom",
                    "tag" => "DIRECT"
                ]
            ],
            "policy" => [
                "levels" => [
                    [
                        "handshake" => 4,
                        "connIdle" => 300,
                        "uplinkOnly" => 1,
                        "downlinkOnly" => 1,
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
     * @return array
     */
    private function buildStableInbounds(): array
    {
        return [
            [
                "tag" => "VLESS-WS",
                "listen" => "0.0.0.0",
                "port" => 2095,
                "protocol" => "vless",
                "settings" => [
                    "clients" => [],
                    "decryption" => "none",
                    "level" => 0
                ],
                "streamSettings" => [
                    "network" => "ws",
                    "security" => "none",
                    "wsSettings" => [
                        "path" => "/vless"
                    ]
                ],
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
                "streamSettings" => [
                    "network" => "ws",
                    "security" => "none",
                    "wsSettings" => [
                        "path" => "/vmess"
                    ]
                ],
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
                "streamSettings" => [
                    "network" => "ws",
                    "security" => "none",
                    "wsSettings" => [
                        "path" => "/trojan"
                    ]
                ],
                "sniffing" => [
                    "enabled" => true,
                    "destOverride" => ["http", "tls"]
                ]
            ],
            [
                "tag" => "Shadowsocks-TCP",
                "listen" => "0.0.0.0",
                "port" => 2098,
                "protocol" => "shadowsocks",
                "settings" => [
                    "clients" => [],
                    "network" => "tcp,udp",
                    "level" => 0
                ]
            ]
        ];
    }

    /**
     * Построение REALITY протоколов
     *
     * @param string $privateKey
     * @param string $shortId
     * @param string $grpcShortId
     * @return array
     */
    private function buildRealityInbounds(string $privateKey, string $shortId, string $grpcShortId): array
    {
        return [
            [
                "tag" => "VLESS TCP REALITY",
                "listen" => "0.0.0.0",
                "port" => 2040,
                "protocol" => "vless",
                "settings" => [
                    "clients" => [],
                    "decryption" => "none",
                    "level" => 0
                ],
                "streamSettings" => [
                    "network" => "tcp",
                    "tcpSettings" => [],
                    "security" => "reality",
                    "realitySettings" => [
                        "show" => false,
                        "dest" => "tradingview.com:443",
                        "xver" => 0,
                        "serverNames" => ["tradingview.com"],
                        "privateKey" => $privateKey,
                        "shortIds" => ["", $shortId]
                    ]
                ],
                "sniffing" => [
                    "enabled" => true,
                    "destOverride" => ["http", "tls", "quic"]
                ]
            ],
            [
                "tag" => "VLESS GRPC REALITY",
                "listen" => "0.0.0.0",
                "port" => 2041,
                "protocol" => "vless",
                "settings" => [
                    "clients" => [],
                    "decryption" => "none",
                    "level" => 0
                ],
                "streamSettings" => [
                    "network" => "grpc",
                    "grpcSettings" => [
                        "serviceName" => "xyz"
                    ],
                    "security" => "reality",
                    "realitySettings" => [
                        "show" => false,
                        "dest" => "discordapp.com:443",
                        "xver" => 0,
                        "serverNames" => ["cdn.discordapp.com", "discordapp.com"],
                        "privateKey" => $privateKey,
                        "shortIds" => ["", $grpcShortId]
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
            'source' => 'panel'
        ]);

        $json_config = $this->buildBaseConfig();
        $json_config['inbounds'] = $this->buildStableInbounds();

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

            $json_config = $this->buildBaseConfig();
            $json_config['inbounds'] = array_merge(
                $this->buildRealityInbounds(
                    $realityKeys['private_key'],
                    $realityKeys['short_id'],
                    $realityKeys['grpc_short_id']
                ),
                $this->buildStableInbounds()
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
            Log::info('Creating server user', [
                'panel_id' => $panel_id,
                'data_limit' => $data_limit,
                'expire' => $expire,
                'source' => 'panel',
                'key_activate_id' => $key_activate_id
            ]);

            $panel = self::updateMarzbanToken($panel_id);
            if (!$panel->server) {
                throw new RuntimeException('Server not found for panel');
            }

            /**
             * @var KeyActivate $key_activate
             */
            $key_activate = KeyActivate::query()->where('id', $key_activate_id)->firstOrFail();

            $marzbanApi = new MarzbanAPI($panel->api_address);
            $userId = Str::uuid();
            $maxConnections = $options['max_connections'] ?? 3;

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
        try {
            // Получаем исходную и целевую панели
            /** @var Panel $panel */
            $sourcePanel = Panel::findOrFail($sourcePanel_id);
            /** @var Panel $panel */
            $targetPanel = Panel::findOrFail($targetPanel_id);

            // Получаем пользователя сервера
            $key_activate = KeyActivate::findOrFail($serverUser_id);
            $serverUser = $key_activate->keyActivateUser->serverUser;

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
                $message .= "🔗 Для продолжения работы, заново вставьте Вашу ссылку-подключение в клиент VPN\n";
                $message .= "https://vpn-telegram.com/config/{$key_activate->id}";

                try {
                    if (!is_null($key_activate->module_salesman_id)) {
                        $salesman = $key_activate->moduleSalesman;

                        BottApi::senModuleMessage(BotModuleFactory::fromEntity($salesman->botModule), $key_activate->user_tg_id, $message);
                    } else {
                        $salesman = $key_activate->packSalesman->salesman;
                        $telegram = new Api($salesman->token);
                        $telegram->sendMessage([
                            'chat_id' => $key_activate->user_tg_id,
                            'text' => $message,
                            'parse_mode' => 'HTML'
                        ]);
                    }
                } catch (Exception $e) {
                    Log::error('Ошибка при отправке сообщения через FatherBot', [
                        'error' => $e->getMessage(),
                        'salesman_id' => $salesman->id,
                        'telegram_id' => $salesman->telegram_id,
                        'source' => 'panel'
                    ]);
                }

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
        }
    }
}
