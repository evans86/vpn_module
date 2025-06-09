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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class BotModuleController extends Controller
{
    private BotModuleService $botModuleService;

    public function __construct()
    {
        $this->middleware('api');
        $this->botModuleService = new BotModuleService();
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
            $botModule = $this->botModuleService->create(
                $request->public_key,
                $request->private_key,
                $request->bot_id
            );

            return ApiHelpers::success(BotModuleFactory::fromEntity($botModule)->getArray());
        } catch (RuntimeException $r) {
            return ApiHelpers::error($r->getMessage());
        } catch (Exception $e) {
            Log::error($e->getMessage());
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
            $botModule = BotModule::query()->where('public_key', $request->public_key)->where('private_key', $request->private_key)->first();
            if (empty($botModule))
                return ApiHelpers::error('Not found module.');
            return ApiHelpers::success(BotModuleFactory::fromEntity($botModule)->getArray());
        } catch (RuntimeException $r) {
            return ApiHelpers::error($r->getMessage());
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ApiHelpers::error('Module creation error');
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
            if (is_null($request->public_key))
                return ApiHelpers::error('Not found params: public_key');
            $botModule = BotModule::query()->where('public_key', $request->public_key)->first();
            if (empty($botModule))
                throw new RuntimeException('Not found module.');

            return ApiHelpers::success(BotModuleFactory::fromEntity($botModule)->getSettings());

        } catch (RuntimeException $r) {
            return ApiHelpers::error($r->getMessage());
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ApiHelpers::error('Module creation error');
        }
    }

    /**
     * Обновление настроек модуля бота
     *
     * @param BotUpdateRequest $request
     * @return array|string
     */
    public function update(BotUpdateRequest $request)
    {
        try {
            $botModule = $this->botModuleService->update($request->getDto());
            $botModule = BotModule::query()->where('public_key', $botModule->public_key)->where('private_key', $botModule->private_key)->first();
            return ApiHelpers::success(BotModuleFactory::fromEntity($botModule)->getArray());
        } catch (RuntimeException $r) {
            return ApiHelpers::error($r->getMessage());
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ApiHelpers::error('Module creation error');
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
            $this->botModuleService->delete($request->public_key, $request->private_key);
            return ApiHelpers::success('OK');
        } catch (RuntimeException $r) {
            return ApiHelpers::error($r->getMessage());
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return ApiHelpers::error('Module creation error');
        }
    }
}
