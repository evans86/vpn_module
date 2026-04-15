<?php

namespace App\Console\Commands;

use App\Dto\Bot\BotModuleFactory;
use App\Models\Bot\BotModule;
use App\Services\External\BottApi;
use App\Services\Key\KeyActivateService;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

/**
 * Проверка сценария «покупка ключа через веб-модуль» (как API buy-key / activate-key).
 */
class TestWebModuleBuyKeyCommand extends Command
{
    /** @var KeyActivateService */
    private $keyActivateService;

    protected $signature = 'key-activate:test-web-module-buy
                            {--public-key= : public_key модуля (bot_module.public_key)}
                            {--user-tg-id= : Telegram ID пользователя Bott}
                            {--user-secret-key= : секрет пользователя для checkUser}
                            {--product-id=1 : тариф: 1, 3, 6 или 12 (месяцы)}
                            {--dry-run : только модуль + checkUser + баланс, без заказа и активации}
                            {--execute : выполнить реальную покупку в Bott и активацию (без этого флага при отсутствии --dry-run команда завершится с подсказкой)}';

    protected $description = 'Тест покупки/активации ключа через веб-модуль (как buy-key). По умолчанию — dry-run; реальная покупка только с --execute.';

    public function __construct(KeyActivateService $keyActivateService)
    {
        parent::__construct();
        $this->keyActivateService = $keyActivateService;
    }

    public function handle(): int
    {
        $publicKey = (string) $this->option('public-key');
        $userTgId = $this->option('user-tg-id');
        $userSecretKey = (string) $this->option('user-secret-key');
        $productId = (int) $this->option('product-id');
        $dryRun = (bool) $this->option('dry-run');
        $execute = (bool) $this->option('execute');

        if ($publicKey === '' || $userTgId === null || $userTgId === '' || $userSecretKey === '') {
            $this->error('Укажите --public-key, --user-tg-id и --user-secret-key');

            return self::FAILURE;
        }

        if (! in_array($productId, [1, 3, 6, 12], true)) {
            $this->error('product_id должен быть 1, 3, 6 или 12');

            return self::FAILURE;
        }

        if (! $dryRun && ! $execute) {
            $this->warn('Реальная покупка не выполнена. Используйте --dry-run для проверки Bott или --execute для полного сценария (списание + активация).');

            return self::FAILURE;
        }

        $botModule = BotModule::where('public_key', $publicKey)->first();
        if (! $botModule) {
            $this->error('Модуль бота не найден по public_key');

            return self::FAILURE;
        }

        $this->info('Модуль: id=' . $botModule->id);

        try {
            $userBottData = $this->checkBottUserAndBalance($botModule, $userTgId, $userSecretKey);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $money = $userBottData['money'] ?? 0;
        $this->line('Bott: checkUser OK, money=' . $money . ' (коп.)');

        if ($dryRun) {
            $this->info('Dry-run: заказ и активация не выполнялись.');

            return self::SUCCESS;
        }

        $botModuleDto = BotModuleFactory::fromEntity($botModule);

        try {
            $this->info('Покупка ключа (Bott)...');
            $key = $this->keyActivateService->buyKey($botModuleDto, $productId, $userBottData);
            $this->line('Ключ создан/получен: ' . $key->id);

            $this->info('Активация в системе...');
            $activated = $this->keyActivateService->activateModuleKey($key, (int) $userTgId);
            $this->info('Готово. status=' . $activated->status . ', finish_at=' . ($activated->finish_at ?? 'null'));
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Как {@see \App\Http\Controllers\Api\v1\KeyActivateController}: checkUser + ненулевой баланс.
     *
     * @return array<string, mixed>
     */
    private function checkBottUserAndBalance(BotModule $botModule, $userTgId, string $userSecretKey): array
    {
        $userCheck = BottApi::checkUser(
            $userTgId,
            $userSecretKey,
            $botModule->public_key,
            $botModule->private_key
        );
        if (! $userCheck['result']) {
            throw new RuntimeException($userCheck['message'] ?? 'Ошибка авторизации пользователя Bott');
        }
        $data = $userCheck['data'] ?? [];
        if (! is_array($data)) {
            $data = [];
        }
        if (($data['money'] ?? 0) == 0) {
            throw new RuntimeException('Пополните баланс в боте');
        }

        return $data;
    }
}
