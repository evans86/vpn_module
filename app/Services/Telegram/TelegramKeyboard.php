<?php

namespace App\Services\Telegram;

class TelegramKeyboard
{
    private $menu;
    private $resize_keyboard;
    private $one_time_keyboard;
    private $lineButtons = [];

    public function __construct($resize = true, $one_time = false)
    {
        $this->resize_keyboard = $resize;
        $this->one_time_keyboard = $one_time;
        $this->menu = [];
    }

    public function addButtonColumns(array $buttons, int $column = 1, bool $test = true)
    {
        $i = 0;
        foreach ($buttons as $button) {
            $i++;
            if ($test) {
                array_push($this->lineButtons, $button);
                if ($i == $column) {
                    $this->endLine();
                    $i = 0;
                }
            } else {
                array_push($this->lineButtons, $button[0]);
                if ($i == $column) {
                    $this->endLine();
                    $i = 0;
                }
            }
        }
        if ($i != 0)
            $this->endLine();
    }

    public function getButtons(): array
    {
        return $this->menu;
    }

    public function addInLine(array $button)
    {
        array_push($this->lineButtons, $button);
        return $this;
    }

    public function endLine()
    {
        if (count($this->lineButtons) != 0)
            $this->addButtons($this->lineButtons);
        $this->lineButtons = [];
        return $this;
    }

    public function setButtons(array $buttons)
    {
        $this->menu = $buttons;
    }

    public function addButtons(array $buttons)
    {
        $this->menu[] = $buttons;
    }

    public function merge(array $buttons): void
    {
        $this->menu = array_merge($this->menu, $buttons);
    }

    public function mergeFirst(array $buttons)
    {
        $this->menu = array_merge($buttons, $this->menu);
    }

    public function getMenu(): string
    {
        return json_encode([
            'keyboard' => $this->menu,
            'resize_keyboard' => $this->resize_keyboard,
            'one_time_keyboard' => $this->one_time_keyboard]);
    }

    public function getInline(): string
    {
        if (count($this->lineButtons) != 0)
            $this->endLine();
        // Надо ключи сбрасывать
        foreach ($this->menu as $key => $line) {
            $this->menu[$key] = array_values($this->menu[$key]);
        }
        if (count($this->menu) == 0)
            return '';


        return json_encode([
            "inline_keyboard" => $this->menu,
        ]);
    }

    public function checkLine()
    {
        if (count($this->lineButtons) != 0)
            $this->endLine();
        return $this;
    }

    public function existButtons(): bool
    {
        return count($this->menu) != 0;
    }

    public function countLineButtons(): int
    {
        return count($this->lineButtons);
    }

    public function countButtons(): int
    {
        return count($this->menu);
    }
}
