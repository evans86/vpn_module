<?php

namespace App\Services\Panel\marzban;

use App\Dto\Server\ServerDto;
use App\Dto\Server\ServerFactory;
use App\Models\Panel\Panel;
use App\Models\Server\Server;
use App\Models\ServerUser\ServerUser;
use App\Services\External\MarzbanAPI;
use App\Services\Key\KeyActivateUserService;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use phpseclib\Net\SSH2;
use Exception;
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
            Log::info('Starting panel creation', ['server_id' => $server_id]);
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

            Log::info('Panel created successfully', ['server_id' => $server_id]);
        } catch (Exception $e) {
            Log::error('Failed to create panel', [
                'server_id' => $server_id,
                'error' => $e->getMessage(),
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
        Log::info('Checking server status', ['server_id' => $server->id]);

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
            Log::info('Installing panel', ['host' => $host]);
            
            // Скачиваем и запускаем скрипт установки
            $commands = [
                'wget ' . self::INSTALL_SCRIPT_URL,
                'chmod +x install_marzban.sh',
                './install_marzban.sh ' . $host,
                // Перезапускаем панель после установки
                'cd /opt/marzban',
                'docker-compose down',
                'docker-compose up -d'
            ];

            foreach ($commands as $command) {
                $result = $ssh->exec($command);
                Log::debug('Command result', ['command' => $command, 'result' => $result]);
            }

            // Ждем пока панель запустится
            sleep(10);

            // Проверяем что панель запущена
            $status = $ssh->exec('cd /opt/marzban && docker-compose ps');
            if (!str_contains($status, 'running')) {
                // Смотрим логи если панель не запустилась
                $logs = $ssh->exec('cd /opt/marzban && docker-compose logs --tail=50');
                Log::error('Panel failed to start', ['logs' => $logs]);
                throw new RuntimeException('Panel is not running after installation. Logs: ' . $logs);
            }

            // Проверяем доступность веб-интерфейса
            $curl = $ssh->exec("curl -k -I https://{$host}/dashboard");
            if (!str_contains($curl, '200 OK') && !str_contains($curl, '301 Moved Permanently')) {
                Log::error('Web interface not accessible', ['curl' => $curl]);
                throw new RuntimeException('Panel web interface is not accessible');
            }

        } catch (Exception $e) {
            Log::error('Panel installation failed', [
                'host' => $host,
                'error' => $e->getMessage()
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
        Log::info('Creating panel record', ['server_id' => $server->id]);

        $envContent = $ssh->exec('cat ' . self::PANEL_ENV_PATH);
        $config = $this->parseEnvFile($envContent);

        $panel = new Panel();
        $panel->server_id = $server->id;
        $panel->panel = Panel::MARZBAN;
        $panel->panel_status = Panel::PANEL_CREATED;
        $panel->panel_adress = "https://{$server->host}/dashboard";
        $panel->panel_login = $config['SUDO_USERNAME'];
        $panel->panel_password = $config['SUDO_PASSWORD'];
        $panel->save();

        Log::info('Panel record created', [
            'panel_id' => $panel->id,
            'panel_address' => $panel->panel_adress
        ]);
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
        Log::info('Connecting to server via SSH', ['ip' => $serverDto->ip]);

        try {
            $ssh = new SSH2($serverDto->ip);
            $ssh->setTimeout(100000);

            if (!$ssh->login($serverDto->login, $serverDto->password)) {
                throw new RuntimeException('SSH authentication failed');
            }

            Log::info('SSH connection established', ['ip' => $serverDto->ip]);
            return $ssh;
        } catch (Exception $e) {
            Log::error('SSH connection failed', [
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
        Log::info('Updating panel token', ['panel_id' => $panel_id]);

        try {
            /**
             * @var Panel $panel
             */
            $panel = Panel::query()->where('id', $panel_id)->firstOrFail();

            if (is_null($panel->auth_token) || $panel->token_died_time <= time()) {
                $marzbanApi = new MarzbanAPI($panel->api_address);
                $panel->auth_token = $marzbanApi->getToken($panel->panel_login, $panel->panel_password);
                $panel->token_died_time = time() + 85400;
                $panel->save();

                Log::info('Panel token updated', ['panel_id' => $panel_id]);
            }

            return $panel;
        } catch (Exception $e) {
            Log::error('Failed to update panel token', [
                'panel_id' => $panel_id,
                'error' => $e->getMessage()
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
        Log::info('Checking user online status', ['panel_id' => $panel_id, 'user_id' => $user_id]);

        try {
            $panel = $this->updateMarzbanToken($panel_id);
            $marzbanApi = new MarzbanAPI($panel->api_address);
            $userData = $marzbanApi->getUser($panel->auth_token, $user_id);

            $timeOnline = strtotime($userData['online_at']);
            $status = $this->determineUserStatus($timeOnline);

            Log::info('User status checked', [
                'panel_id' => $panel_id,
                'user_id' => $user_id,
                'status' => $status['status']
            ]);

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
        Log::info('Deleting server user', ['panel_id' => $panel_id, 'user_id' => $user_id]);

        try {
            $panel = $this->updateMarzbanToken($panel_id);
            $marzbanApi = new MarzbanAPI($panel->api_address);

            // Удаляем пользователя из панели
            $deleteData = $marzbanApi->deleteUser($panel->auth_token, $user_id);

            if (!empty($deleteData)) {
                throw new RuntimeException('Failed to delete user from panel');
            }

            // Удаляем запись из БД
            $serverUser = ServerUser::query()->where('id', $user_id)->firstOrFail();
            if (!$serverUser->delete()) {
                throw new RuntimeException('Failed to delete user record from database');
            }

            Log::info('Server user deleted successfully', [
                'panel_id' => $panel_id,
                'user_id' => $user_id
            ]);
        } catch (Exception $e) {
            Log::error('Failed to delete server user', [
                'panel_id' => $panel_id,
                'user_id' => $user_id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Обновление конфигурации панели
     *
     * @param int $panel_id
     * @return void
     * @throws GuzzleException
     */
    public function updateConfiguration(int $panel_id): void
    {
        $panel = self::updateMarzbanToken($panel_id);
        $panel->panel_status = Panel::PANEL_CONFIGURED;
        $panel->save();

        $marzbanApi = new MarzbanAPI($panel->api_address);

        $json_config = [
            "log" => [
                "loglevel" => "info"
            ],
            "inbounds" => [
                [
                    "tag" => "VLESS HTTPUPGRADE NoTLS",
                    "listen" => "0.0.0.0",
                    "port" => 2095,
                    "protocol" => "vless",
                    "settings" => [
                        "clients" => [
                        ],
                        "decryption" => "none"
                    ],
                    "streamSettings" => [
                        "network" => "httpupgrade",
                        "httpupgradeSettings" => [
                            "path" => "/",
                            "host" => ""
                        ],
                        "security" => "none"
                    ],
                    "sniffing" => [
                        "enabled" => true,
                        "destOverride" => [
                            "http",
                            "tls",
                            "quic"
                        ]
                    ]
                ],
                [
                    "tag" => "VMESS HTTPUPGRADE NoTLS",
                    "listen" => "0.0.0.0",
                    "port" => 2095,
                    "protocol" => "vmess",
                    "settings" => [
                        "clients" => [
                        ]
                    ],
                    "streamSettings" => [
                        "network" => "httpupgrade",
                        "httpupgradeSettings" => [
                            "path" => "/",
                            "host" => ""
                        ],
                        "security" => "none"
                    ],
                    "sniffing" => [
                        "enabled" => true,
                        "destOverride" => [
                            "http",
                            "tls",
                            "quic"
                        ]
                    ]
                ],
                [
                    "tag" => "TROJAN WS NOTLS",
                    "listen" => "0.0.0.0",
                    "port" => 8080,
                    "protocol" => "trojan",
                    "settings" => [
                        "clients" => [
                        ]
                    ],
                    "streamSettings" => [
                        "network" => "ws",
                        "wsSettings" => [
                            "path" => "/"
                        ],
                        "security" => "none"
                    ],
                    "sniffing" => [
                        "enabled" => true,
                        "destOverride" => [
                            "http",
                            "tls",
                            "quic"
                        ]
                    ]
                ],
                [
                    "tag" => "Shadowsocks TCP",
                    "listen" => "0.0.0.0",
                    "port" => 1080,
                    "protocol" => "shadowsocks",
                    "settings" => [
                        "clients" => [
                        ],
                        "network" => "tcp,udp"
                    ]
                ]
            ],
            "outbounds" => [
                [
                    "protocol" => "freedom",
                    "tag" => "DIRECT"
                ],
                [
                    "protocol" => "blackhole",
                    "tag" => "BLOCK"
                ]
            ],
            "routing" => [
                "rules" => [
                    [
                        "ip" => [
                            "geoip:private"
                        ],
                        "domain" => [
                            "geosite:private"
                        ],
                        "protocol" => [
                            "bittorrent"
                        ],
                        "outboundTag" => "BLOCK",
                        "type" => "field"
                    ]
                ]
            ]
        ];

        $marzbanApi->modifyConfig($panel->auth_token, $json_config);
    }

    /**
     * Добавление пользователи и протоколов подключения
     *
     * @param int $panel_id
     * @param int $data_limit
     * @param int $expire
     * @param string $key_activate_id
     * @return ServerUser
     * @throws GuzzleException
     */
    public function addServerUser(int $panel_id, int $data_limit, int $expire, string $key_activate_id): ServerUser
    {
        try {
            Log::info('Creating server user', [
                'panel_id' => $panel_id,
                'data_limit' => $data_limit,
                'expire' => $expire,
                'key_activate_id' => $key_activate_id
            ]);

            $panel = self::updateMarzbanToken($panel_id);
            if (!$panel->server) {
                throw new RuntimeException('Server not found for panel');
            }

            $marzbanApi = new MarzbanAPI($panel->api_address);
            $userId = Str::uuid();

            $userData = $marzbanApi->createUser($panel->auth_token, $userId, $data_limit, $expire);
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
                'panel_id' => $panel_id
            ]);

            return $serverUser;
        } catch (RuntimeException $r) {
            Log::error('Runtime error while creating server user', [
                'error' => $r->getMessage(),
                'trace' => $r->getTraceAsString()
            ]);
            throw $r;
        } catch (Exception $e) {
            Log::error('Error while creating server user', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new RuntimeException($e->getMessage());
        }
    }
}
