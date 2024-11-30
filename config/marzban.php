<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Marzban Panel Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration for the Marzban panel service
    |
    */

    // Путь к файлу конфигурации панели на сервере
    'env_path' => '/opt/marzban/.env',

    // URL для скачивания скрипта установки
    'install_script_url' => 'https://raw.githubusercontent.com/mozaroc/bash-hooks/main/install_marzban.sh',

    // Время жизни токена в секундах (24 часа)
    'token_lifetime' => 85400,

    // Время ожидания для SSH подключения в секундах
    'ssh_timeout' => 100000,

    // Интервал проверки онлайн статуса в секундах
    'online_check_interval' => 60,

    // Конфигурация по умолчанию для панели
    'default_config' => [
        'log' => [
            'loglevel' => 'info'
        ],
        'inbounds' => [
            [
                'tag' => 'VLESS HTTPUPGRADE NoTLS',
                'listen' => '0.0.0.0',
                'port' => 2095,
                'protocol' => 'vless',
                'settings' => [
                    'clients' => [],
                    'decryption' => 'none'
                ],
                'streamSettings' => [
                    'network' => 'httpupgrade',
                    'httpupgradeSettings' => [
                        'path' => '/',
                        'host' => ''
                    ],
                    'security' => 'none'
                ],
                'sniffing' => [
                    'enabled' => true,
                    'destOverride' => [
                        'http',
                        'tls',
                        'quic'
                    ]
                ]
            ],
            [
                'tag' => 'VMESS HTTPUPGRADE NoTLS',
                'listen' => '0.0.0.0',
                'port' => 2095,
                'protocol' => 'vmess',
                'settings' => [
                    'clients' => []
                ],
                'streamSettings' => [
                    'network' => 'httpupgrade',
                    'httpupgradeSettings' => [
                        'path' => '/',
                        'host' => ''
                    ],
                    'security' => 'none'
                ],
                'sniffing' => [
                    'enabled' => true,
                    'destOverride' => [
                        'http',
                        'tls',
                        'quic'
                    ]
                ]
            ],
            [
                'tag' => 'TROJAN WS NOTLS',
                'listen' => '0.0.0.0',
                'port' => 8080,
                'protocol' => 'trojan',
                'settings' => [
                    'clients' => []
                ],
                'streamSettings' => [
                    'network' => 'ws',
                    'wsSettings' => [
                        'path' => '/'
                    ],
                    'security' => 'none'
                ],
                'sniffing' => [
                    'enabled' => true,
                    'destOverride' => [
                        'http',
                        'tls',
                        'quic'
                    ]
                ]
            ],
            [
                'tag' => 'Shadowsocks TCP',
                'listen' => '0.0.0.0',
                'port' => 1080,
                'protocol' => 'shadowsocks',
                'settings' => [
                    'clients' => [],
                    'network' => 'tcp,udp'
                ]
            ]
        ],
        'outbounds' => [
            [
                'protocol' => 'freedom',
                'tag' => 'DIRECT'
            ],
            [
                'protocol' => 'blackhole',
                'tag' => 'BLOCK'
            ]
        ],
        'routing' => [
            'rules' => [
                [
                    'ip' => [
                        'geoip:private'
                    ],
                    'domain' => [
                        'geosite:private'
                    ],
                    'protocol' => [
                        'bittorrent'
                    ],
                    'outboundTag' => 'BLOCK',
                    'type' => 'field'
                ]
            ]
        ]
    ]
];
