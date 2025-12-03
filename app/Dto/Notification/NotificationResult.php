<?php

namespace App\Dto\Notification;

class NotificationResult
{
    const STATUS_SUCCESS = 'success';
    const STATUS_BLOCKED = 'blocked'; // Пользователь заблокировал бота
    const STATUS_TECHNICAL_ERROR = 'technical_error'; // Техническая ошибка
    const STATUS_USER_NOT_FOUND = 'user_not_found'; // Пользователь не найден

    public string $status;
    public ?string $errorMessage;
    public bool $shouldCountAsSent; // Считать ли как отправленное

    public function __construct(string $status, ?string $errorMessage = null, bool $shouldCountAsSent = false)
    {
        $this->status = $status;
        $this->errorMessage = $errorMessage;
        $this->shouldCountAsSent = $shouldCountAsSent;
    }

    public function isSuccess(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }

    public function isBlocked(): bool
    {
        return $this->status === self::STATUS_BLOCKED;
    }

    public function isTechnicalError(): bool
    {
        return $this->status === self::STATUS_TECHNICAL_ERROR;
    }

    public static function success(): self
    {
        return new self(self::STATUS_SUCCESS, null, true);
    }

    public static function blocked(string $errorMessage = null): self
    {
        return new self(self::STATUS_BLOCKED, $errorMessage, true); // Считаем как отправленное
    }

    public static function technicalError(string $errorMessage): self
    {
        return new self(self::STATUS_TECHNICAL_ERROR, $errorMessage, false); // Не считаем как отправленное
    }

    public static function userNotFound(): self
    {
        return new self(self::STATUS_USER_NOT_FOUND, 'User not found', false);
    }
}

