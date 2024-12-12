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
use RuntimeException;

class PackController extends Controller
{
    private PackService $packService;
    private DatabaseLogger $logger;

    public function __construct(PackService $packService, DatabaseLogger $logger)
    {
        $this->packService = $packService;
        $this->logger = $logger;
    }

    /**
     * Display a listing of the packs.
     * @return View|RedirectResponse
     * @throws Exception
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
        } catch (Exception $e) {
            $this->logger->error('Ошибка при загрузке списка пакетов', [
                'source' => 'pack',
                'action' => 'view',
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return back()->withErrors(['msg' => $e->getMessage()]);
        }
    }

    /**
     * Store a newly created pack.
     * @param StorePackRequest $request
     * @return RedirectResponse
     * @throws Exception
     */
    public function store(StorePackRequest $request)
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
        } catch (RuntimeException $e) {
            $this->logger->error('Ошибка при создании пакета', [
                'source' => 'pack',
                'action' => 'create',
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => array_diff_key($request->validated(), array_flip(['password'])) // Исключаем чувствительные данные
            ]);

            return back()->withErrors(['msg' => $e->getMessage()]);
        }
    }

    /**
     * Update the specified pack.
     * @param UpdatePackRequest $request
     * @param int $id
     * @return RedirectResponse
     * @throws Exception
     */
    public function update(UpdatePackRequest $request, int $id)
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
        } catch (RuntimeException $e) {
            $this->logger->error('Ошибка при обновлении пакета', [
                'source' => 'pack',
                'action' => 'update',
                'user_id' => auth()->id(),
                'pack_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => array_diff_key($request->validated(), array_flip(['password'])) // Исключаем чувствительные данные
            ]);

            return back()->withErrors(['msg' => $e->getMessage()]);
        }
    }

    /**
     * Remove the specified pack.
     * @param int $id
     * @return JsonResponse
     * @throws Exception
     */
    public function destroy(int $id)
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
        } catch (RuntimeException $e) {
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
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
