<?php

namespace App\Http\Controllers\Module;

use App\Models\KeyActivate\KeyActivate;
use App\Logging\DatabaseLogger;
use App\Http\Controllers\Controller;
use App\Repositories\KeyActivate\KeyActivateRepository;
use App\Services\Key\KeyActivateService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Exception;
use RuntimeException;
use Illuminate\Http\RedirectResponse;

class KeyActivateController extends Controller
{
    private DatabaseLogger $logger;
    private KeyActivateService $keyActivateService;
    private KeyActivateRepository $keyActivateRepository;

    public function __construct(
        DatabaseLogger $logger,
        KeyActivateService $keyActivateService,
        KeyActivateRepository $keyActivateRepository
    ) {
        $this->logger = $logger;
        $this->keyActivateService = $keyActivateService;
        $this->keyActivateRepository = $keyActivateRepository;
    }

    /**
     * Display a listing of key activates
     * @return View|RedirectResponse
     * @throws Exception
     */
    public function index()
    {
        try {
            $activate_keys = $this->keyActivateRepository->getPaginatedWithPack();

            $this->logger->info('Просмотр списка активированных ключей', [
                'source' => 'key_activate',
                'action' => 'view_list',
                'user_id' => auth()->id(),
                'total_keys' => $activate_keys->total(),
                'page' => $activate_keys->currentPage()
            ]);

            return view('module.key-activate.index', compact('activate_keys'));
        } catch (Exception $e) {
            $this->logger->error('Ошибка при получении списка ключей', [
                'source' => 'key_activate',
                'action' => 'view_list',
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return back()->withErrors(['msg' => $e->getMessage()]);
        }
    }

    /**
     * Remove the specified key activate
     * @param KeyActivate $key
     * @return JsonResponse
     */
    public function destroy(KeyActivate $key)
    {
        try {
            $this->keyActivateRepository->delete($key);

            $this->logger->info('Удаление ключа активации', [
                'source' => 'key_activate',
                'action' => 'delete',
                'user_id' => auth()->id(),
                'key_id' => $key->id
            ]);

            return response()->json(['message' => 'Ключ успешно удален']);
        } catch (Exception $e) {
            $this->logger->error('Ошибка при удалении ключа активации', [
                'source' => 'key_activate',
                'action' => 'delete',
                'user_id' => auth()->id(),
                'key_id' => $key->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['message' => 'Ошибка при удалении ключа'], 500);
        }
    }

    /**
     * Test activation of the key (development only)
     * @param KeyActivate $key
     * @return JsonResponse
     */
    public function testActivate(KeyActivate $key)
    {
        try {
            // Проверяем статус ключа
            if (!$this->keyActivateRepository->hasCorrectStatusForActivation($key)) {
                $this->logger->warning('Попытка активации ключа с неверным статусом', [
                    'source' => 'key_activate',
                    'action' => 'test_activate',
                    'user_id' => auth()->id(),
                    'key_id' => $key->id,
                    'current_status' => $key->status
                ]);
                return response()->json(['message' => 'Ключ не может быть активирован (неверный статус)'], 400);
            }

            // Тестовый Telegram ID для активации
            $testTgId = rand(10000000, 99999999);

            $this->logger->info('Начало тестовой активации ключа', [
                'source' => 'key_activate',
                'action' => 'test_activate',
                'user_id' => auth()->id(),
                'key_id' => $key->id,
                'test_tg_id' => $testTgId,
                'key_status' => $key->status,
                'deleted_at' => $key->deleted_at
            ]);

            $activatedKey = $this->keyActivateService->activate($key, $testTgId);

            $this->logger->info('Тестовая активация ключа выполнена успешно', [
                'source' => 'key_activate',
                'action' => 'test_activate',
                'user_id' => auth()->id(),
                'key_id' => $key->id,
                'test_tg_id' => $testTgId,
                'new_status' => $activatedKey->status
            ]);

            return response()->json([
                'message' => 'Ключ успешно активирован',
                'key' => [
                    'id' => $activatedKey->id,
                    'status' => $activatedKey->status,
                    'user_tg_id' => $activatedKey->user_tg_id,
                    'deleted_at' => $activatedKey->deleted_at,
                    'activated_at' => $activatedKey->activated_at
                ]
            ]);
        } catch (RuntimeException $e) {
            $this->logger->error('Ошибка при тестовой активации ключа', [
                'source' => 'key_activate',
                'action' => 'test_activate',
                'user_id' => auth()->id(),
                'key_id' => $key->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'key_status' => $key->status,
                'deleted_at' => $key->deleted_at
            ]);

            return response()->json([
                'message' => $e->getMessage(),
                'key' => [
                    'id' => $key->id,
                    'status' => $key->status,
                    'deleted_at' => $key->deleted_at
                ]
            ], 400);
        }
    }
}
