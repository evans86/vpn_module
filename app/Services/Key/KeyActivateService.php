<?php

namespace App\Services\Key;

use App\Models\KeyActivate\KeyActivate;
use App\Models\Panel\Panel;
use App\Services\Panel\PanelStrategy;
use App\Logging\DatabaseLogger;
use RuntimeException;
use Exception;

class KeyActivateService
{
    private $logger;
    private $panelStrategy;

    public function __construct(DatabaseLogger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Активация ключа
     *
     * @param KeyActivate $key
     * @param int $userTgId
     * @return KeyActivate
     * @throws RuntimeException
     */
    public function activate(KeyActivate $key, int $userTgId): KeyActivate
    {
        try {
            // Проверяем текущий статус
            if ($key->status != KeyActivate::PAID) {
                throw new RuntimeException('Ключ не может быть активирован (неверный статус)');
            }

            // Проверяем, не истек ли срок для активации
            if ($key->deleted_at && time() > $key->deleted_at) {
                throw new RuntimeException('Срок активации ключа истек');
            }

            // Проверяем, не занят ли уже ключ другим пользователем
            if ($key->user_tg_id && $key->user_tg_id !== $userTgId) {
                throw new RuntimeException('Ключ уже используется другим пользователем');
            }

            // Получаем активную панель Marzban
            $panel = Panel::where('panel_status', Panel::PANEL_CONFIGURED)
                ->where('panel', Panel::MARZBAN)
                ->first();

            if (!$panel) {
                throw new RuntimeException('Активная панель Marzban не найдена');
            }

            // Создаем стратегию для работы с панелью
            $this->panelStrategy = new PanelStrategy(Panel::MARZBAN);

            // Добавляем пользователя на сервер
            $serverUser = $this->panelStrategy->addServerUser(
                $panel->id,
                $key->traffic_limit,
                $key->finish_at
            );

            // Устанавливаем данные активации
            $key->user_tg_id = $userTgId;
            $key->status = KeyActivate::ACTIVE;
            $key->deleted_at = null;

            if (!$key->save()) {
                throw new RuntimeException('Ошибка при сохранении ключа');
            }

            $this->logger->info('Ключ успешно активирован', [
                'source' => 'key_activate',
                'action' => 'activate',
                'key_id' => $key->id,
                'user_tg_id' => $userTgId,
                'server_user_id' => $serverUser->id
            ]);

            return $key;
        } catch (Exception $e) {
            $this->logger->error('Ошибка при активации ключа', [
                'source' => 'key_activate',
                'action' => 'activate',
                'key_id' => $key->id,
                'user_tg_id' => $userTgId,
                'error' => $e->getMessage()
            ]);

            throw new RuntimeException($e->getMessage());
        }
    }

    /**
     * Проверка и обновление статуса ключа
     *
     * @param KeyActivate $key
     * @return KeyActivate
     */
    public function checkAndUpdateStatus(KeyActivate $key): KeyActivate
    {
        $currentTime = time();

        // Проверяем срок действия для активных ключей
        if ($key->status === KeyActivate::ACTIVE && $currentTime > $key->finish_at) {
            $key->status = KeyActivate::EXPIRED;
            $key->save();

            $this->logger->info('Статус ключа обновлен на EXPIRED (истек срок действия)', [
                'source' => 'key_activate',
                'action' => 'update_status',
                'key_id' => $key->id
            ]);
        }

        // Проверяем срок активации для оплаченных ключей
        if ($key->status === KeyActivate::PAID && $key->deleted_at && $currentTime > $key->deleted_at) {
            $key->status = KeyActivate::EXPIRED;
            $key->save();

            $this->logger->info('Статус ключа обновлен на EXPIRED (истек срок активации)', [
                'source' => 'key_activate',
                'action' => 'update_status',
                'key_id' => $key->id
            ]);
        }

        return $key;
    }
}
