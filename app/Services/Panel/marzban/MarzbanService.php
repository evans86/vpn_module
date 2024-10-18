<?php

namespace App\Services\Panel\marzban;

use App\Dto\Server\ServerDto;
use App\Dto\Server\ServerFactory;
use App\Models\KeyProtocols\KeyProtocols;
use App\Models\Panel\Panel;
use App\Models\Server\Server;
use App\Models\ServerUser\ServerUser;
use App\Services\External\MarzbanAPI;
use Illuminate\Support\Str;
use phpseclib\Net\SSH2;

class MarzbanService
{
    /**
     * Создание панели на сервере, без обновления bearer token
     *
     * @param int $server_id
     * @return void
     */
    public function create(int $server_id): void
    {
        /**
         * @var Server $server
         */
        $server = Server::query()->where('id', $server_id)->first();

        //команды для установки панели на сервер
        $firstCommand = 'wget https://raw.githubusercontent.com/mozaroc/bash-hooks/main/install_marzban.sh';
        //загрузка файла marzban.sh
        $secondCommand = 'chmod +x install_marzban.sh';
        //установка файла marzban.sh
        $thirdCommand = './install_marzban.sh ' . $server->host;

        //подключение по SSH
        $ssh_connect = self::connectSshAdapter(ServerFactory::fromEntity($server));

        //проверить статус сервера на провайдере

        //выполнение команд на сервере
        $ssh_connect->exec($firstCommand);
        $ssh_connect->exec($secondCommand);
        $ssh_connect->exec($thirdCommand);

        //проверка существования файла .env
        if (str_contains($ssh_connect->exec('stat /opt/marzban/.env'), 'No such file')) {
            throw new \RuntimeException('No such panel');
        } else {
            $settingFile = 'cat /opt/marzban/.env';
            $output = $ssh_connect->exec($settingFile);

            //достаем данные из файла .env и записываем массив данных
            $outputs = explode("\n", $output);
            $data = [];
            foreach ($outputs as $output) {
                $formate = str_replace(array("'", '"'), ' ', $output);
                $formate = preg_replace('/\s+/', '', $formate);

                $result = explode("=", $formate);
                if (isset($result[1]))
                    $data[$result[0]] = $result[1];
            }

            $panel = new Panel();

            $panel->server_id = $server->id;
            $panel->panel = Panel::MARZBAN;
            $panel->panel_status = Panel::PANEL_CONFIGURED;
            $panel->panel_adress = $data['XRAY_SUBSCRIPTION_URL_PREFIX'];
            $panel->panel_login = $data['SUDO_USERNAME'];
            $panel->panel_password = $data['SUDO_PASSWORD'];

            $panel->save();
        }
    }

    /**
     * Адаптер для работы SSH connect
     *
     * @param ServerDto $serverDto
     * @return SSH2
     */
    public function connectSshAdapter(ServerDto $serverDto): SSH2
    {
        $ssh_connect = new SSH2($serverDto->ip);
        $ssh_connect->setTimeout(100000);

        //переделать
        if (!$ssh_connect->login($serverDto->login, $serverDto->password)) {
            throw new \RuntimeException('SSH connection failed');
        } else {
            $output = $ssh_connect;
        }

        return $output;
    }

    /**
     * Обновление bearer token для панели
     *
     * @param int $panel_id
     * @return Panel|null
     */
    public function updateMarzbanToken(int $panel_id)
    {
        /**
         * @var Panel $panel
         */
        $panel = Panel::query()->where('id', $panel_id)->first();

        if (is_null($panel->auth_token) || $panel->token_died_time <= time()) {
            $marzbanApi = new MarzbanAPI($panel->panel_adress);
            $panel->auth_token = $marzbanApi->getToken($panel->panel_login, $panel->panel_password);
            $panel->token_died_time = time() + 85400;
            $panel->save();
        }

        return $panel;
    }

    /**
     * Обновление конфигурации панели
     *
     * @param int $panel_id
     * @return void
     */
    public function updateConfiguration(int $panel_id)
    {
        $panel = self::updateMarzbanToken($panel_id);

        $marzbanApi = new MarzbanAPI($panel->panel_adress);

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

    public function addServerUser(int $panel_id)
    {
        $panel = self::updateMarzbanToken($panel_id);

        $marzbanApi = new MarzbanAPI($panel->panel_adress);
        $userId = Str::uuid();

        $userData = $marzbanApi->createUser($panel->auth_token, $userId);

        $serverUser = new ServerUser();
        $serverUser->id = $userId;
        $serverUser->panel_id = $panel->id;
        $serverUser->is_free = false;

        $serverUser->save();

        $key_protocols = new KeyProtocols();
        $key_protocols->user_id = $userId;
        $key_protocols->key = $userData['subscription_url'];

        $key_protocols->save();

        dd($userData);
    }

    /**
     * Удаление пользователя панели
     *
     * @param int $panel_id
     * @param string $user_id
     * @return void
     */
    public function deleteServerUser(int $panel_id, string $user_id)
    {
        $panel = self::updateMarzbanToken($panel_id);

        $marzbanApi = new MarzbanAPI($panel->panel_adress);
        $deleteData = $marzbanApi->deleteUser($panel->auth_token, $user_id);

        $serverUser = ServerUser::query()->where('id', $user_id)->first();

        if(empty($deleteData)){
            if (!$serverUser->delete())
                throw new \RuntimeException('Server User dont delete');
        }else{
            throw new \RuntimeException('Error delete user in the panel.');
        }

        dd('Success delete user in the panel.');
    }
}
