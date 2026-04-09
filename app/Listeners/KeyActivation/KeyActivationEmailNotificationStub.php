<?php

namespace App\Listeners\KeyActivation;

use App\Events\KeyActivation\KeyActivated;
use Illuminate\Support\Facades\Log;

/**
 * Заготовка: письмо на email ключа при успешной активации (как дополнение к TG).
 * Полная отправка (Mailable/Notification/очередь) — позже.
 */
class KeyActivationEmailNotificationStub
{
    public function handle(KeyActivated $event): void
    {
        $email = trim((string) ($event->key->email ?? ''));
        if ($email === '') {
            return;
        }

        if (! (bool) config('key_activation.email.stub_log', false)) {
            return;
        }

        Log::debug('Key activation email notification (stub, отправка не выполняется)', [
            'key_id' => $event->key->id,
            'email' => $email,
            'source' => $event->source,
        ]);
    }
}
