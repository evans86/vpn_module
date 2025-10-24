<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Ресурсы, которые "должны" открываться
    |--------------------------------------------------------------------------
    | Ссылки НЕ показываются пользователю в интерфейсе — мы выводим только label.
    | Здесь лучше указывать максимально стабильные точки.
    */
    'resources_must' => [
        ['label' => 'Cloudflare (trace)', 'url' => 'https://www.cloudflare.com/cdn-cgi/trace'],
        ['label' => 'Google (204)',       'url' => 'https://www.gstatic.com/generate_204'],
        ['label' => 'Apple (204)',        'url' => 'https://captive.apple.com/hotspot-detect.html'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Ресурсы, которые часто блокируются
    |--------------------------------------------------------------------------
    */
    'resources_often_blocked' => [
        ['label' => 'YouTube',    'url' => 'https://www.youtube.com/robots.txt'],
        ['label' => 'Instagram',  'url' => 'https://www.instagram.com/robots.txt'],
        ['label' => 'Facebook',   'url' => 'https://www.facebook.com/robots.txt'],
        ['label' => 'TikTok',     'url' => 'https://www.tiktok.com/robots.txt'],
        ['label' => 'VK',         'url' => 'https://vk.com/robots.txt'],
        ['label' => 'Twitter/X',  'url' => 'https://twitter.com/robots.txt'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Размеры тестовых загрузок с вашего бэкенда (/netcheck/payload/{size})
    |--------------------------------------------------------------------------
    */
    'download_sizes' => ['1mb', '5mb', '10mb'],

    /*
    |--------------------------------------------------------------------------
    | DoH (DNS over HTTPS) домены для проверки
    |--------------------------------------------------------------------------
    */
    'doh_domains' => ['google.com', 'cloudflare.com', 'yandex.ru', 'vk.com', 't.me', 'whatsapp.com'],

    /*
    |--------------------------------------------------------------------------
    | Региональные пробы (для оценки "дальности" маршрута)
    |--------------------------------------------------------------------------
    */
    'regional_probes' => [
        ['label' => 'Европа (Cloudflare)',   'url' => 'https://www.cloudflare.com/cdn-cgi/trace'],
        ['label' => 'США (CloudFront)',      'url' => 'https://d1czd1c8nvr2l2.cloudfront.net/robots.txt'],
        ['label' => 'Азия (Akamai)',         'url' => 'https://www.akamai.com/site/en/documents/akamai/akamai-technologies-robots-txt.txt'],
    ],

    /*
    |--------------------------------------------------------------------------
    | YouTube точки
    |--------------------------------------------------------------------------
    */
    'youtube' => [
        ['label' => 'YouTube (204)',   'url' => 'https://www.youtube.com/generate_204'],
        ['label' => 'YT Images (204)', 'url' => 'https://i.ytimg.com/generate_204'],
    ],

    /*
    |--------------------------------------------------------------------------
    | .ru / банки / госуслуги
    |--------------------------------------------------------------------------
    */
    'ru_services' => [
        ['label' => 'Yandex',      'url' => 'https://yandex.ru/robots.txt'],
        ['label' => 'VK',          'url' => 'https://vk.com/robots.txt'],
        ['label' => 'Gosuslugi',   'url' => 'https://www.gosuslugi.ru/favicon.ico'],
        ['label' => 'Sberbank',    'url' => 'https://www.sberbank.ru/'],
        ['label' => 'Tinkoff',     'url' => 'https://www.tinkoff.ru/robots.txt'],
        ['label' => 'VTB',         'url' => 'https://www.vtb.ru/robots.txt'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Мессенджеры (проверка веб/статических/медиа-точек)
    |--------------------------------------------------------------------------
    | Telegram и WhatsApp включены. Список можно расширять.
    */
    'messengers' => [
        // Telegram
        ['label' => 'Telegram (web)',   'url' => 'https://web.telegram.org/'],
        ['label' => 'Telegram (api)',   'url' => 'https://api.telegram.org/'],
        ['label' => 'Telegram (t.me)',  'url' => 'https://t.me/'],
        ['label' => 'Telegram (cdn)',   'url' => 'https://telegram.org/img/t_logo.svg'],

        // WhatsApp
        ['label' => 'WhatsApp (web)',   'url' => 'https://web.whatsapp.com/'],
        ['label' => 'WhatsApp (static)','url' => 'https://static.whatsapp.net/'],
        ['label' => 'WhatsApp (media)', 'url' => 'https://mmg.whatsapp.net/'],

        // Дополнительно (при желании раскомментировать)
        // ['label' => 'Viber (web)',      'url' => 'https://www.viber.com/'],
        // ['label' => 'Signal (site)',    'url' => 'https://signal.org/'],
        // ['label' => 'Skype (web)',      'url' => 'https://web.skype.com/'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Соцсети (отдельный блок помимо "часто блокируемых")
    |--------------------------------------------------------------------------
    */
    'socials' => [
        ['label' => 'VK',          'url' => 'https://vk.com/'],
        ['label' => 'Facebook',    'url' => 'https://www.facebook.com/'],
        ['label' => 'Instagram',   'url' => 'https://www.instagram.com/'],
        ['label' => 'Twitter/X',   'url' => 'https://twitter.com/'],
        ['label' => 'TikTok',      'url' => 'https://www.tiktok.com/'],
        ['label' => 'YouTube',     'url' => 'https://www.youtube.com/'],
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP (порт 80) пробы — детект блокировок plain HTTP
    |--------------------------------------------------------------------------
    */
    'http_probe' => [
        ['label' => 'Example.com',   'url' => 'http://example.com/'],
        ['label' => 'neverssl.com',  'url' => 'http://neverssl.com/'],
    ],

    /*
    |--------------------------------------------------------------------------
    | .ru-скорость — статический файл(ы) на .ru-домене с ИЗВЕСТНЫМ размером
    |--------------------------------------------------------------------------
    | ВАЖНО: укажите свой стабильный .ru-asset и точный размер в байтах.
    | Пример ниже закомментирован. Если массив пустой — .ru-скорость просто не будет измеряться.
    |
    | Пример:
    | 'ru_speed_assets' => [
    |     ['label' => 'Ваш CDN (.ru) 1MB', 'url' => 'https://cdn.yourdomain.ru/test/1mb.bin', 'bytes' => 1048576],
    |     ['label' => 'Ваш CDN (.ru) 5MB', 'url' => 'https://cdn.yourdomain.ru/test/5mb.bin', 'bytes' => 5242880],
    | ],
    */
    'ru_speed_assets' => [
        // заполняется в проде
    ],
];
