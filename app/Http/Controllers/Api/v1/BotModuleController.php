<?php

namespace App\Http\Controllers\Api\v1;

use App\Dto\Bot\BotModuleFactory;
use App\Helpers\ApiHelpers;
use App\Http\Controllers\Controller;
use App\Http\Requests\BotModule\BotUpdateRequest;
use App\Http\Requests\BotModule\BotCreateRequest;
use App\Http\Requests\BotModule\BotGetRequest;
use App\Models\Bot\BotModule;
use App\Services\Bot\BotModuleService;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class BotModuleController extends Controller
{
    private BotModuleService $botModuleService;

    public function __construct(BotModuleService $botModuleService)
    {
        $this->middleware('api');
        $this->botModuleService = $botModuleService;
    }

    /**
     * Запрос проверки доступности сервиса
     *
     * @return array
     */
    public function ping()
    {
        return ApiHelpers::successStr('OK');
    }

    /**
     * Создание нового модуля для бота
     *
     * @param BotCreateRequest $request
     * @return array|string
     */
    public function create(BotCreateRequest $request)
    {
        try {
            // Проверяем, существует ли уже модуль с таким bot_id
            /** @var BotModule|null $existingModule */
            $existingModule = BotModule::query()
                ->where('bot_id', $request->bot_id)
                ->first();

            if ($existingModule instanceof BotModule) {
                // Если модуль уже существует, возвращаем его
                Log::info('Модуль с bot_id уже существует, возвращаем существующий', [
                    'bot_id' => $request->bot_id,
                    'module_id' => $existingModule->id
                ]);
                return ApiHelpers::success(BotModuleFactory::fromEntity($existingModule)->getArray());
            }

            // Проверяем уникальность public_key и private_key
            /** @var BotModule|null $existingByKeys */
            $existingByKeys = BotModule::query()
                ->where('public_key', $request->public_key)
                ->orWhere('private_key', $request->private_key)
                ->first();

            if ($existingByKeys instanceof BotModule) {
                Log::warning('Попытка создать модуль с существующими ключами', [
                    'bot_id' => $request->bot_id,
                    'existing_bot_id' => $existingByKeys->bot_id
                ]);
                return ApiHelpers::error('Module with these keys already exists');
            }

            // Создаем новый модуль
            $botModule = $this->botModuleService->create(
                $request->public_key,
                $request->private_key,
                $request->bot_id
            );

            return ApiHelpers::success(BotModuleFactory::fromEntity($botModule)->getArray());
        } catch (RuntimeException $r) {
            return ApiHelpers::error($r->getMessage());
        } catch (Exception $e) {
            Log::error('Ошибка при создании модуля', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'public_key' => $request->public_key ?? null,
                'bot_id' => $request->bot_id ?? null
            ]);
            return ApiHelpers::error('Module creation error');
        }
    }

    /**
     * Получение информации о модуле бота
     *
     * @param BotGetRequest $request
     * @return array|string
     */
    public function get(BotGetRequest $request)
    {
        try {
            /**
             * @var BotModule|null $botModule
             */
            $botModule = BotModule::query()
                ->where('public_key', $request->public_key)
                ->where('private_key', $request->private_key)
                ->first();
            
            if (!$botModule) {
                return ApiHelpers::error('Not found module.');
            }
            
            return ApiHelpers::success(BotModuleFactory::fromEntity($botModule)->getArray());
        } catch (RuntimeException $r) {
            return ApiHelpers::error($r->getMessage());
        } catch (Exception $e) {
            Log::error('Ошибка при получении модуля', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'public_key' => $request->public_key ?? null
            ]);
            return ApiHelpers::error('Module get error');
        }
    }

    /**
     * Получение настроек модуля бота
     *
     * @param Request $request
     * @return array|string
     */
    public function getSettings(Request $request)
    {
        try {
            $request->validate([
                'public_key' => 'required|string',
            ]);
            /**
             * @var BotModule|null $botModule
             */
            $botModule = BotModule::query()->where('public_key', $request->public_key)->first();
            
            if (!$botModule) {
                throw new RuntimeException('Not found module.');
            }

            return ApiHelpers::success(BotModuleFactory::fromEntity($botModule)->getSettings());

        } catch (ValidationException $v) {
            return ApiHelpers::error($v->getMessage());
        } catch (RuntimeException $r) {
            return ApiHelpers::error($r->getMessage());
        } catch (Exception $e) {
            Log::error('Ошибка при получении настроек модуля', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'public_key' => $request->public_key ?? null
            ]);
            return ApiHelpers::error('Module get settings error');
        }
    }

    /**
     * Обновление настроек модуля бота
     *
     * @param BotUpdateRequest $request
     * @return array|string
     * @throws GuzzleException
     */
    public function update(BotUpdateRequest $request)
    {
        try {
            $botModule = $this->botModuleService->update($request->getDto());
            /**
             * @var BotModule|null $botModule
             */
            $botModule = BotModule::query()
                ->where('public_key', $botModule->public_key)
                ->where('private_key', $botModule->private_key)
                ->first();
            
            if (!$botModule) {
                return ApiHelpers::error('Module not found after update.');
            }
            
            return ApiHelpers::success(BotModuleFactory::fromEntity($botModule)->getArray());
        } catch (RuntimeException $r) {
            return ApiHelpers::error($r->getMessage());
        } catch (Exception $e) {
            Log::error('Ошибка при обновлении модуля', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'id' => $request->id ?? null
            ]);
            return ApiHelpers::error('Module update error');
        }
    }

    /**
     * Удаление модуля бота
     *
     * @param Request $request
     * @return array|string
     */
    public function delete(Request $request)
    {
        try {
            $validated = $request->validate([
                'public_key' => 'required|string',
                'private_key' => 'required|string',
            ]);

            $this->botModuleService->delete($validated['public_key'], $validated['private_key']);
            return ApiHelpers::success('OK');
        } catch (RuntimeException $r) {
            return ApiHelpers::error($r->getMessage());
        } catch (Exception $e) {
            Log::error('Ошибка при удалении модуля', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'public_key' => $validated['public_key'] ?? null
            ]);
            return ApiHelpers::error('Module delete error');
        }
    }
}
