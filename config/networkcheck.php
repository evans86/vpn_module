<?php

return [
    'resources_must' => [
        ['label' => 'Cloudflare (204)', 'url' => 'https://www.cloudflare.com/cdn-cgi/trace'],
        ['label' => 'Google robots',    'url' => 'https://www.google.com/robots.txt'],
    ],
    'resources_often_blocked' => [
        ['label' => 'YouTube robots',   'url' => 'https://www.youtube.com/robots.txt'],
        ['label' => 'Instagram robots', 'url' => 'https://www.instagram.com/robots.txt'],
        ['label' => 'Facebook robots',  'url' => 'https://www.facebook.com/robots.txt'],
        ['label' => 'TikTok robots',    'url' => 'https://www.tiktok.com/robots.txt'],
        ['label' => 'VK robots',        'url' => 'https://vk.com/robots.txt'],
    ],
    'download_sizes' => ['1mb','5mb','10mb'],

    'doh_domains' => ['google.com','cloudflare.com','yandex.ru','vk.com','t.me','whatsapp.com'],

    'regional_probes' => [
        ['label' => 'Европа (CF)',     'url' => 'https://www.cloudflare.com/cdn-cgi/trace'],
        ['label' => 'США (Cloudfront)','url' => 'https://d1czd1c8nvr2l2.cloudfront.net/robots.txt'],
        ['label' => 'Азия (Akamai)',   'url' => 'https://www.akamai.com/site/en/documents/akamai/akamai-technologies-robots-txt.txt'],
    ],

    'youtube' => [
        ['label' => 'YouTube (204)',   'url' => 'https://www.youtube.com/generate_204'],
        ['label' => 'YT Images (204)', 'url' => 'https://i.ytimg.com/generate_204'],
    ],

    'ru_services' => [
        ['label' => 'Yandex',      'url' => 'https://yandex.ru/robots.txt'],
        ['label' => 'VK',          'url' => 'https://vk.com/robots.txt'],
        ['label' => 'Gosuslugi',   'url' => 'https://www.gosuslugi.ru/favicon.ico'],
        ['label' => 'Sberbank',    'url' => 'https://www.sberbank.ru/'],
        ['label' => 'Tinkoff',     'url' => 'https://www.tinkoff.ru/robots.txt'],
        ['label' => 'VTB',         'url' => 'https://www.vtb.ru/robots.txt'],
    ],

    'messengers' => [
        ['label' => 'Telegram Web', 'url' => 'https://web.telegram.org/'],
        ['label' => 'Telegram CDN', 'url' => 'https://cdn4.telesco.pe/'],
        ['label' => 't.me',         'url' => 'https://t.me/'],
        ['label' => 'WhatsApp Web', 'url' => 'https://web.whatsapp.com/'],
        ['label' => 'WA Static',    'url' => 'https://static.whatsapp.net/'],
    ],

    'http_probe' => [
        ['label' => 'Example.com', 'url' => 'http://example.com/'],
    ],
];
