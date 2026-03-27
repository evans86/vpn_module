<?php

namespace App\Services\Bot;

use App\Models\Salesman\Salesman;

/**
 * Inline-кнопки со ссылками под сообщением об успешной активации (бот активации).
 */
final class ActivationSuccessKeyboard
{
    /**
     * @return list<array{text: string, url: string}>
     */
    public static function defaultRows(): array
    {
        return [
            ['text' => '🤖 Android', 'url' => 'https://teletype.in/@bott_manager/C0WFg-Bsren'],
            ['text' => '🍏 iOS', 'url' => 'https://teletype.in/@bott_manager/8jEexiKqjlEWQ'],
            ['text' => '🪟️ Windows', 'url' => 'https://teletype.in/@bott_manager/kJaChoXUqmZ'],
            ['text' => '💻 MacOS', 'url' => 'https://teletype.in/@bott_manager/Q8vOQ-_lnQ_'],
            ['text' => '📺 AndroidTV', 'url' => 'https://teletype.in/@bott_manager/OIc2Dwer6jV'],
        ];
    }

    /**
     * Формат для Telegram sendMessage (inline_keyboard).
     *
     * @return array{inline_keyboard: list<list<array{text: string, url: string}>>}
     */
    public static function telegramKeyboard(Salesman $salesman): array
    {
        $rows = self::normalizedRows($salesman);

        $inline = [];
        foreach ($rows as $row) {
            $inline[] = [['text' => $row['text'], 'url' => $row['url']]];
        }

        return ['inline_keyboard' => $inline];
    }

    /**
     * @return list<array{text: string, url: string}>
     */
    public static function normalizedRows(Salesman $salesman): array
    {
        $raw = $salesman->activation_success_keyboard_links;
        if (! is_array($raw) || count($raw) === 0) {
            return self::defaultRows();
        }

        $out = [];
        foreach ($raw as $row) {
            $text = trim((string) ($row['text'] ?? ''));
            $url = trim((string) ($row['url'] ?? ''));
            if ($text === '' || $url === '') {
                continue;
            }
            $out[] = ['text' => $text, 'url' => $url];
        }

        return count($out) > 0 ? $out : self::defaultRows();
    }
}
