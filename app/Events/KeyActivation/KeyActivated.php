<?php

namespace App\Events\KeyActivation;

use App\Models\KeyActivate\KeyActivate;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Успешная активация ключа. Слушатели: заготовка письма на поле email у ключа (если задано).
 * Диспатч вызывается из KeyActivateService после сохранения статуса (вне долгих транзакций).
 */
class KeyActivated
{
    use Dispatchable;
    use SerializesModels;

    /** @var KeyActivate */
    public $key;

    /** @var string activate, activate_with_finish_at, activate_module_key, renew, finalize_stuck_activation */
    public $source;

    public function __construct(KeyActivate $key, string $source)
    {
        $this->key = $key;
        $this->source = $source;
    }
}
