<?php

namespace App\Helpers;

class OrderHelper
{
    /**
     * @param $errorMessage
     * @return string
     */
    public static function formingError($errorMessage): string
    {
        // Если ошибка не передана, возвращаем стандартное сообщение
        if (empty($errorMessage)) {
            return 'Неизвестная ошибка при создании заказа';
        }

        // Обработка числовых ошибок (когда приходит сумма)
        if (is_numeric($errorMessage)) {
            return sprintf('создатель бота должен пополнить баланс в сервисе');
        }

        // Можно добавить обработку других типов ошибок
        return self::matchErrorPatterns((string)$errorMessage);
    }

    protected static function matchErrorPatterns(string $errorMessage): string
    {
        // Приводим к нижнему регистру для удобства сравнения
        $lowerMessage = mb_strtolower($errorMessage);

        // Массив шаблонов ошибок и соответствующих сообщений
        $patterns = [
//            'баланс' => 'Недостаточно средств на балансе',
//            'недостаточно средств' => 'Недостаточно средств для выполнения операции',
//            'не найден' => 'Товар или услуга не найдена',
//            'временная ошибка' => 'Временная ошибка сервиса, попробуйте позже',
            //
        ];

        // Проверяем каждый шаблон
        foreach ($patterns as $pattern => $message) {
            if (mb_strpos($lowerMessage, $pattern) !== false) {
                return $message;
            }
        }

        // Если ни один шаблон не подошел, возвращаем оригинальное сообщение
        return $errorMessage;
    }
}
