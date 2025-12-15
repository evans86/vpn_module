<?php

namespace App\Exceptions;

use Exception;

class KeyReplacedException extends Exception
{
    protected $newKeyId;

    public function __construct(string $message = "", string $newKeyId = "", int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->newKeyId = $newKeyId;
    }

    public function getNewKeyId(): string
    {
        return $this->newKeyId;
    }
}

