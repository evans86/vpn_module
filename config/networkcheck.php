<?php

return [
    'resources_must' => [
        ['label' => 'Google (204)',        'url' => 'https://www.gstatic.com/generate_204',  'mode' => 'nocors'],
        ['label' => 'Wikipedia robots',    'url' => 'https://en.wikipedia.org/robots.txt',   'mode' => 'nocors'],
        ['label' => 'Telegram Web',        'url' => 'https://web.telegram.org/',             'mode' => 'nocors'],
        ['label' => 'Cloudflare Trace',    'url' => 'https://www.cloudflare.com/cdn-cgi/trace','mode'=>'nocors'],
        ['label' => 'ipify (IP API)',      'url' => 'https://api.ipify.org?format=json',     'mode' => 'json'],
    ],

    'resources_often_blocked' => [
        ['label' => 'YouTube (204)',       'url' => 'https://www.youtube.com/generate_204',  'mode' => 'nocors'],
        ['label' => 'Instagram robots',    'url' => 'https://www.instagram.com/robots.txt',  'mode' => 'nocors'],
        ['label' => 'Facebook robots',     'url' => 'https://www.facebook.com/robots.txt',   'mode' => 'nocors'],
        ['label' => 'TikTok robots',       'url' => 'https://www.tiktok.com/robots.txt',     'mode' => 'nocors'],
        ['label' => 'VK robots',           'url' => 'https://vk.com/robots.txt',             'mode' => 'nocors'],
    ],

    // Размеры теста скачивания
    'download_sizes' => [
        '1 MB' => 1048576,
        '5 MB' => 5242880,
    ],

    // DoH-домены (без изменений)
    'doh_domains' => ['google.com', 'wikipedia.org', 'telegram.org', 'youtube.com', 'vk.com'],

    // Региональные пробы — заменены на стабильные robots.txt
    'regional_probes' => [
        ['label' => 'EU (BBC)',          'url' => 'https://www.bbc.co.uk/robots.txt',    'mode' => 'nocors'],
        ['label' => 'US (NYTimes)',      'url' => 'https://www.nytimes.com/robots.txt',  'mode' => 'nocors'],
        ['label' => 'JP (Yahoo.co.jp)',  'url' => 'https://www.yahoo.co.jp/robots.txt',  'mode' => 'nocors'],
        ['label' => 'DE (DW)',           'url' => 'https://www.dw.com/robots.txt',       'mode' => 'nocors'],
        ['label' => 'TR (Hurriyet)',     'url' => 'https://www.hurriyet.com.tr/robots.txt','mode'=>'nocors'],
        ['label' => 'RU (Yandex)',       'url' => 'https://yandex.ru/robots.txt',        'mode' => 'nocors'],
        ['label' => 'BR (Globo)',        'url' => 'https://www.globo.com/robots.txt',    'mode' => 'nocors'],
    ],
];
