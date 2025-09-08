<?php

namespace App\Services\External;

use App\Dto\Bot\BotModuleDto;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class BottApi
{
    const HOST = 'https://api.bot-t.com/';

    /**
     * Проверка $secret_key
     *
     * @param int $telegram_id
     * @param string $secret_key
     * @param string $public_key
     * @param string $private_key
     * @return mixed
     * @throws GuzzleException
     */
    public static function checkUser(int $telegram_id, string $secret_key, string $public_key, string $private_key)
    {
        $requestParam = [
            'public_key' => $public_key,
            'private_key' => $private_key,
            'id' => $telegram_id,
            'secret_key' => $secret_key,
        ];

        $client = new Client(['base_uri' => self::HOST]);
        $response = $client->get('v1/module/user/check-secret?' . http_build_query($requestParam));

        $result = $response->getBody()->getContents();
        return json_decode($result, true);
    }

    /**
     * Получение пользователя создателя
     *
     * @param string $public_key
     * @param string $private_key
     * @return array
     * @throws GuzzleException
     */
    public static function getCreator(string $public_key, string $private_key): array
    {
        $requestParam = [
            'public_key' => $public_key,
            'private_key' => $private_key
        ];

        $client = new Client(['base_uri' => self::HOST]);
        $response = $client->get('v1/module/user/get-creator?' . http_build_query($requestParam));

        $result = $response->getBody()->getContents();
        return json_decode($result, true);
    }

    /**
     * Получение $secret_key
     *
     * @param int $telegram_id
     * @param string $public_key
     * @param string $private_key
     * @return array
     * @throws GuzzleException
     */
    public static function get(int $telegram_id, string $public_key, string $private_key): array
    {
        $requestParam = [
            'public_key' => $public_key,
            'private_key' => $private_key,
            'id' => $telegram_id,
        ];

        $client = new Client(['base_uri' => self::HOST]);
        $response = $client->get('v1/module/user/get?' . http_build_query($requestParam));

        $result = $response->getBody()->getContents();
        return json_decode($result, true);
    }

    /**
     * Создание заказа в боте по продажи ключей
     *
     * @param BotModuleDto $botDto
     * @param int $category_id
     * @param int $count
     * @return mixed
     * @throws GuzzleException
     */
    public static function createOrderSalesman(BotModuleDto $botDto, int $category_id, int $count)
    {
        $link = 'https://api.bot-t.com/v1/shopdigital/order-public/';

        $bot_id = 254886;

        $requestParam = [
            'bot_id' => $bot_id, //бот паши
            'category_id' => $category_id, //какое содержимое товара
            'count' => $count, //количество
            'user_id' => $botDto->bot_user_id, //id продавца в боте Паши
            'secret_user_key' => $botDto->secret_user_key, //секретный ключ продавца
        ];

        $client = new Client(['base_uri' => $link]);
        $response = $client->request('POST', 'create', [
            'form_params' => $requestParam,
            'headers' => [
            ]
        ]);

        $result = $response->getBody()->getContents();
        return json_decode($result, true);
    }

    /**
     * @TODO проверить отмену заказа
     *
     * Отмена заказа в боте по продажи ключей
     *
     * @param BotModuleDto $botDto
     * @param int $order_id
     * @param int $salesman_id
     * @return mixed
     * @throws GuzzleException
     */
    public static function cancelOrderSalesman(BotModuleDto $botDto, int $order_id, int $salesman_id)
    {
        $link = 'https://api.bot-t.com/v1/shopdigital/order-public/';
        $bot_id = 254886;

        $requestParam = [
            'bot_id' => $bot_id, //бот паши
            'order_id' => $order_id, //ID заказа
            'user_id' => $salesman_id, //id продавца в боте Паши
            'secret_user_key' => $botDto->secret_user_key, //секретный ключ продавца
        ];

        $client = new Client(['base_uri' => $link]);
        $response = $client->request('POST', 'cancel', [
            'form_params' => $requestParam,
            'headers' => [
            ]
        ]);

        $result = $response->getBody()->getContents();
        return json_decode($result, true);
    }

    /**
     * Списание баланса
     *
     * @param BotModuleDto $botDto
     * @param array $userData
     * @param int $amount
     * @param string $comment
     * @return mixed
     * @throws GuzzleException
     */
    public static function subtractBalance(BotModuleDto $botDto, array $userData, int $amount, string $comment)
    {
        $link = 'https://api.bot-t.com/v1/module/user/';
        $public_key = $botDto->public_key;
        $private_key = $botDto->private_key;
        $user_id = $userData['user']['telegram_id'];
        $secret_key = $userData['secret_user_key'];

        $requestParam = [
            'public_key' => $public_key,
            'private_key' => $private_key,
            'user_id' => $user_id,
            'secret_key' => $secret_key,
            'amount' => $amount,
            'comment' => $comment,
        ];

        $client = new Client(['base_uri' => $link]);
        $response = $client->request('POST', 'subtract-balance', [
            'form_params' => $requestParam,
            'headers' => [
                'User-Agent' => $comment,
            ]
        ]);

        $result = $response->getBody()->getContents();
        return json_decode($result, true);
    }

    /**
     * Пополнение баланса
     *
     * @param BotModuleDto $botDto
     * @param array $userData
     * @param int $amount
     * @param string $comment
     * @return mixed
     * @throws GuzzleException
     */
    public static function addBalance(BotModuleDto $botDto, array $userData, int $amount, string $comment)
    {
        $link = 'https://api.bot-t.com/v1/module/user/';
        $public_key = $botDto->public_key;
        $private_key = $botDto->private_key;
        $user_id = $userData['user']['telegram_id'];
        $secret_key = $userData['secret_user_key'];

        $requestParam = [
            'public_key' => $public_key,
            'private_key' => $private_key,
            'user_id' => $user_id,
            'secret_key' => $secret_key,
            'amount' => $amount,
            'comment' => $comment,
        ];

        $client = new Client(['base_uri' => $link]);
        $response = $client->request('POST', 'add-balance', [
            'form_params' => $requestParam,
            'headers' => [
                'User-Agent' => $comment,
            ]
        ]);

        $result = $response->getBody()->getContents();
        return json_decode($result, true);
    }

    public static function createOrder(BotModuleDto $botDto, array $userData, int $amount, string $product)
    {
        $link = 'https://api.bot-t.com/v1/module/shop/';
        $public_key = $botDto->public_key;
        $private_key = $botDto->private_key;
        $user_id = $userData['user']['telegram_id'];
        $secret_key = $userData['secret_user_key'];
        $category_id = $botDto->category_id;

        $requestParam = [
            'public_key' => $public_key,
            'private_key' => $private_key,
            'user_id' => $user_id,
            'secret_key' => $secret_key,
            'amount' => $amount,
            'count' => 1,
            'category_id' => $category_id,
            'product' => $product,
        ];

        $client = new Client(['base_uri' => $link]);
        $response = $client->request('POST', 'order-create', [
            'form_params' => $requestParam,
            'headers' => [
                'User-Agent' => $product,
            ]
        ]);

        $result = $response->getBody()->getContents();
        return json_decode($result, true);
    }

    /**
     * Получение Bot
     *
     * @param string $public_key
     * @return array
     * @throws GuzzleException
     */
    public static function getBot(string $public_key): array
    {
        $link = 'https://api.bot-t.com/v1/module/bot/';

        $requestParam = [
            'public_key' => $public_key,
            'type_id' => 11
        ];

        $client = new Client(['base_uri' => $link]);
        $response = $client->request('POST', 'get-by-public-key', [
            'form_params' => $requestParam,
            'headers' => [
            ]
        ]);

        $result = $response->getBody()->getContents();
        return json_decode($result, true);
    }

    /**
     * @param BotModuleDto $botDto
     * @param int $user_tg_id
     * @param string $text
     * @return array
     * @throws GuzzleException
     */
    public static function senModuleMessage(BotModuleDto $botDto, int $user_tg_id, string $text): array
    {
        $link = 'https://api.bot-t.com/v1/module/user/';

        $requestParam = [
            'public_key' => $botDto->public_key,
            'private_key' => $botDto->private_key,
            'user_tg_id' => $user_tg_id,
            'method' => 'sendMessage',
            'params' => [
                'text' => $text,
                'parse_mode' => 'HTML'
            ]
        ];

        $client = new Client(['base_uri' => $link]);
        $response = $client->request('POST', 'send-request', [
            'form_params' => $requestParam,
            'headers' => [
            ]
        ]);

        $result = $response->getBody()->getContents();
        return json_decode($result, true);
    }
}
