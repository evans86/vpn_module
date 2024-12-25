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

            // Проверяем и устанавливаем Docker если его нет
            if (!str_contains($ssh->exec('docker --version'), 'Docker version')) {
                Log::info('Installing Docker');
                $ssh->exec('curl -fsSL https://get.docker.com -o get-docker.sh');
                $ssh->exec('sh get-docker.sh');
                $ssh->exec('systemctl start docker');
                $ssh->exec('systemctl enable docker');
                
                if (!str_contains($ssh->exec('docker --version'), 'Docker version')) {
                    throw new RuntimeException('Failed to install Docker');
                }
            }

            // Проверяем и устанавливаем Docker Compose если его нет
            if (!str_contains($ssh->exec('docker-compose --version'), 'docker-compose version')) {
                Log::info('Installing Docker Compose');
                
                // Определяем дистрибутив
                $osInfo = $ssh->exec('cat /etc/os-release');
                Log::debug('OS Info', ['info' => $osInfo]);
                
                if (str_contains($osInfo, 'Ubuntu') || str_contains($osInfo, 'Debian')) {
                    // Для Ubuntu/Debian
                    $installCommands = [
                        'apt-get update',
                        'apt-get install -y docker-compose'
                    ];
                } elseif (str_contains($osInfo, 'CentOS') || str_contains($osInfo, 'Red Hat')) {
                    // Для CentOS/RHEL
                    $installCommands = [
                        'yum install -y epel-release',
                        'yum install -y docker-compose'
                    ];
                } else {
                    // Для других систем пробуем через pip
                    $installCommands = [
                        'curl -fsSL https://bootstrap.pypa.io/get-pip.py -o get-pip.py',
                        'python3 get-pip.py',
                        'pip3 install docker-compose'
                    ];
                }
                
                foreach ($installCommands as $command) {
                    $result = $ssh->exec($command);
                    Log::debug('Docker Compose installation command', [
                        'command' => $command,
                        'result' => $result
                    ]);
                }
                
                // Проверяем установку
                $composeVersion = $ssh->exec('docker-compose --version');
                Log::info('Docker Compose version check', ['version' => $composeVersion]);
                
                if (!str_contains($composeVersion, 'docker-compose version')) {
                    // Пробуем альтернативный способ установки через curl
                    Log::info('Trying alternative Docker Compose installation method');
                    
                    $alternativeCommands = [
                        'curl -L "https://github.com/docker/compose/releases/download/v2.23.3/docker-compose-linux-x86_64" -o /usr/local/bin/docker-compose',
                        'chmod +x /usr/local/bin/docker-compose',
                        'ln -sf /usr/local/bin/docker-compose /usr/bin/docker-compose'
                    ];
                    
                    foreach ($alternativeCommands as $command) {
                        $result = $ssh->exec($command);
                        Log::debug('Alternative installation command', [
                            'command' => $command,
                            'result' => $result
                        ]);
                    }
                    
                    // Финальная проверка
                    if (!str_contains($ssh->exec('docker-compose --version'), 'docker-compose version')) {
                        throw new RuntimeException('Failed to install Docker Compose after multiple attempts');
                    }
                }
            }

            // Проверяем статус Docker
            $dockerStatus = $ssh->exec('systemctl status docker');
            if (!str_contains($dockerStatus, 'active (running)')) {
                Log::info('Starting Docker service');
                $ssh->exec('systemctl start docker');
                sleep(5);
            }

            // Очищаем старые файлы установки
            $ssh->exec('rm -f install_marzban.sh get-docker.sh get-pip.py');
            
            $commands = [
                'wget ' . self::INSTALL_SCRIPT_URL,
                'chmod +x install_marzban.sh',
                './install_marzban.sh ' . $host
            ];

            foreach ($commands as $command) {
                Log::debug('Executing command', ['command' => $command]);
                $result = $ssh->exec($command);
                Log::debug('Command result', ['command' => $command, 'result' => $result]);

                if ($ssh->getExitStatus() !== 0) {
                    throw new RuntimeException("Command failed: $command\nOutput: $result");
                }
            }

            // Проверяем статус Docker контейнеров
            $dockerPs = $ssh->exec('cd /opt/marzban && docker-compose ps');
            Log::info('Docker containers status', ['status' => $dockerPs]);

            // Проверяем логи контейнеров
            $dockerLogs = $ssh->exec('cd /opt/marzban && docker-compose logs --tail=50');
            Log::info('Docker containers logs', ['logs' => $dockerLogs]);

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
        Log::info('Verifying panel installation', ['server_id' => $server->id]);

        // Проверяем наличие конфигурационного файла
        if (str_contains($ssh->exec('stat ' . self::PANEL_ENV_PATH), 'No such file')) {
            throw new RuntimeException('Panel configuration file not found');
        }

        // Проверяем установку Docker
        $dockerVersion = $ssh->exec('docker --version');
        Log::info('Docker version', [
            'server_id' => $server->id,
            'version' => $dockerVersion
        ]);

        if (!str_contains($dockerVersion, 'Docker version')) {
            throw new RuntimeException('Docker is not installed or not accessible');
        }

        // Проверяем установку Docker Compose
        $composeVersion = $ssh->exec('docker-compose --version');
        Log::info('Docker Compose version', [
            'server_id' => $server->id,
            'version' => $composeVersion
        ]);

        if (!str_contains($composeVersion, 'docker-compose version')) {
            throw new RuntimeException('Docker Compose is not installed or not accessible');
        }

        // Проверяем статус Docker сервиса
        $dockerStatus = $ssh->exec('systemctl status docker');
        Log::info('Docker service status', [
            'server_id' => $server->id,
            'status' => $dockerStatus
        ]);

        if (!str_contains($dockerStatus, 'active (running)')) {
            // Пробуем запустить Docker
            Log::warning('Docker service is not running, attempting to start', [
                'server_id' => $server->id
            ]);
            $ssh->exec('systemctl start docker');
            sleep(5);
            
            $dockerStatus = $ssh->exec('systemctl status docker');
            if (!str_contains($dockerStatus, 'active (running)')) {
                throw new RuntimeException('Failed to start Docker service');
            }
        }

        // Проверяем наличие docker-compose.yml
        $composeFile = $ssh->exec('cat /opt/marzban/docker-compose.yml');
        Log::info('Docker Compose file check', [
            'server_id' => $server->id,
            'exists' => !empty($composeFile)
        ]);

        if (empty($composeFile)) {
            throw new RuntimeException('Docker Compose file not found');
        }

        // Проверяем статус Docker контейнеров
        $dockerPs = $ssh->exec('cd /opt/marzban && docker-compose ps');
        Log::info('Initial Docker containers status', [
            'server_id' => $server->id,
            'status' => $dockerPs
        ]);

        if (!str_contains($dockerPs, 'running')) {
            // Проверяем логи перед перезапуском
            $dockerLogs = $ssh->exec('cd /opt/marzban && docker-compose logs --tail=50');
            Log::info('Docker logs before restart', [
                'server_id' => $server->id,
                'logs' => $dockerLogs
            ]);

            // Проверяем использование портов
            $portCheck = $ssh->exec('netstat -tulpn | grep -E ":80|:443"');
            Log::info('Port usage check', [
                'server_id' => $server->id,
                'ports' => $portCheck
            ]);

            // Пробуем перезапустить контейнеры
            Log::warning('Docker containers are not running, attempting to restart', [
                'server_id' => $server->id,
                'docker_status' => $dockerPs
            ]);
            
            $ssh->exec('cd /opt/marzban && docker-compose down --remove-orphans');
            sleep(5); // Ждем полной остановки
            
            // Проверяем и удаляем старые контейнеры
            $ssh->exec('docker rm -f $(docker ps -aq) 2>/dev/null || true');
            
            $ssh->exec('cd /opt/marzban && docker-compose pull');
            $ssh->exec('cd /opt/marzban && docker-compose up -d');
            sleep(15); // Ждем запуска
            
            // Проверяем статус снова
            $dockerPs = $ssh->exec('cd /opt/marzban && docker-compose ps');
            $dockerLogs = $ssh->exec('cd /opt/marzban && docker-compose logs --tail=50');
            
            Log::info('Docker status after restart', [
                'server_id' => $server->id,
                'status' => $dockerPs,
                'logs' => $dockerLogs
            ]);

            if (!str_contains($dockerPs, 'running')) {
                throw new RuntimeException('Failed to start Docker containers after installation. Logs: ' . $dockerLogs);
            }
        }

        $envContent = $ssh->exec('cat ' . self::PANEL_ENV_PATH);
        $config = $this->parseEnvFile($envContent);

        if (empty($config['SUDO_USERNAME']) || empty($config['SUDO_PASSWORD'])) {
            throw new RuntimeException('Invalid panel configuration');
        }

        // Проверяем доступность веб-интерфейса
        $curlCheck = $ssh->exec("curl -k -I https://{$server->host}/dashboard");
        Log::info('Web interface accessibility check', [
            'server_id' => $server->id,
            'curl_result' => $curlCheck
        ]);

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
