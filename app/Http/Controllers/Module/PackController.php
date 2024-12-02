<?php

namespace App\Http\Controllers\Module;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pack\StorePackRequest;
use App\Http\Requests\Pack\UpdatePackRequest;
use App\Services\Pack\PackService;
use App\Logging\DatabaseLogger;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class PackController extends Controller
{
    /**
     * @var PackService
     */
    private PackService $packService;

    /**
     * @var DatabaseLogger
     */
    private DatabaseLogger $logger;

    /**
     * @param PackService $packService
     * @param DatabaseLogger $logger
     */
    public function __construct(PackService $packService, DatabaseLogger $logger)
    {
        $this->packService = $packService;
        $this->logger = $logger;
    }

    /**
     * Display a listing of the packs.
     *
     * @return View|RedirectResponse
     */
    public function index()
    {
        try {
            $packs = $this->packService->getAllPaginated();
            $this->logger->info('Просмотр списка пакетов', [
                'source' => 'pack',
                'action' => 'view',
                'user_id' => auth()->id(),
                'total_packs' => $packs->total()
            ]);
            return view('module.pack.index', compact('packs'));
        } catch (\Exception $e) {
            $this->logger->error('Ошибка при загрузке списка пакетов', [
                'source' => 'pack',
                'action' => 'view',
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return back()->withErrors(['msg' => 'Ошибка при загрузке пакетов']);
        }
    }

    /**
     * Store a newly created pack.
     *
     * @param StorePackRequest $request
     * @return RedirectResponse
     */
    public function store(StorePackRequest $request): RedirectResponse
    {
        try {
            $data = $request->validated();
            $this->packService->create($data);

            $this->logger->info('Пакет успешно создан', [
                'source' => 'pack',
                'action' => 'create',
                'user_id' => auth()->id(),
                'pack_data' => array_diff_key($data, array_flip(['password'])) // Исключаем чувствительные данные
            ]);

            return redirect()->route('module.pack.index')
                ->with('success', 'Пакет успешно создан');
        } catch (Exception $e) {
            $this->logger->error('Ошибка при создании пакета', [
                'source' => 'pack',
                'action' => 'create',
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => array_diff_key($request->validated(), array_flip(['password'])) // Исключаем чувствительные данные
            ]);
            return back()->withErrors(['msg' => 'Ошибка при создании пакета']);
        }
    }

    /**
     * Update the specified pack.
     *
     * @param UpdatePackRequest $request
     * @param int $id
     * @return RedirectResponse
     */
    public function update(UpdatePackRequest $request, int $id): RedirectResponse
    {
        try {
            $data = $request->validated();
            $this->packService->update($id, $data);

            $this->logger->info('Пакет успешно обновлен', [
                'source' => 'pack',
                'action' => 'update',
                'user_id' => auth()->id(),
                'pack_id' => $id,
                'pack_data' => array_diff_key($data, array_flip(['password'])) // Исключаем чувствительные данные
            ]);

            return redirect()->route('module.pack.index')
                ->with('success', 'Пакет успешно обновлен');
        } catch (Exception $e) {
            $this->logger->error('Ошибка при обновлении пакета', [
                'source' => 'pack',
                'action' => 'update',
                'user_id' => auth()->id(),
                'pack_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => array_diff_key($request->validated(), array_flip(['password'])) // Исключаем чувствительные данные
            ]);
            return back()->withErrors(['msg' => 'Ошибка при обновлении пакета']);
        }
    }

    /**
     * Remove the specified pack.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $this->packService->delete($id);

            $this->logger->info('Пакет успешно удален', [
                'source' => 'pack',
                'action' => 'delete',
                'user_id' => auth()->id(),
                'pack_id' => $id
            ]);

            return response()->json(['success' => true]);
        } catch (Exception $e) {
            $this->logger->error('Ошибка при удалении пакета', [
                'source' => 'pack',
                'action' => 'delete',
                'user_id' => auth()->id(),
                'pack_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при удалении пакета'
            ], 500);
        }
    }
}
