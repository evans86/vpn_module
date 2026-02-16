<?php

namespace App\Http\Controllers\Module;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pack\StorePackRequest;
use App\Http\Requests\Pack\UpdatePackRequest;
use App\Services\Pack\PackService;
use App\Logging\DatabaseLogger;
use Exception;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

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

    public function __construct(PackService $packService, DatabaseLogger $logger)
    {
        $this->packService = $packService;
        $this->logger = $logger;
    }

    /**
     * Display a listing of the packs.
     *
     * @param Request $request
     * @return Application|Factory|View
     * @throws Exception
     */
    public function index(Request $request)
    {
        try {
            $filters = array_filter($request->only(['id', 'status', 'title']));

            $packs = $this->packService->getAllPaginated($filters);

            return view('module.pack.index', compact('packs', 'filters'));
        } catch (Exception $e) {
            $this->logger->error('Ошибка при загрузке списка пакетов', [
                'source' => 'pack',
                'action' => 'view',
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * Store a newly created pack.
     *
     * @param StorePackRequest $request
     * @return RedirectResponse
     * @throws Exception
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
                'pack_data' => array_diff_key($data, array_flip(['password']))
            ]);

            return redirect('/admin/module/pack')
                ->with('success', 'Пакет успешно создан');
        } catch (RuntimeException $e) {
            $this->logger->error('Ошибка при создании пакета', [
                'source' => 'pack',
                'action' => 'create',
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => array_diff_key($data, array_flip(['password']))
            ]);

            return back()->withErrors(['msg' => $e->getMessage()]);
        }
    }

    /**
     * Update the specified pack.
     *
     * @param UpdatePackRequest $request
     * @param int $id
     * @return RedirectResponse
     * @throws Exception
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
                'pack_data' => array_diff_key($data, array_flip(['password']))
            ]);

            return redirect()->route('admin.module.pack.index')
                ->with('success', 'Пакет успешно обновлен');
        } catch (RuntimeException $e) {
            $this->logger->error('Ошибка при обновлении пакета', [
                'source' => 'pack',
                'action' => 'update',
                'user_id' => auth()->id(),
                'pack_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => array_diff_key($request->validated(), array_flip(['password']))
            ]);

            return back()->withErrors(['msg' => $e->getMessage()]);
        }
    }

    /**
     * Remove the specified pack.
     *
     * @param int $id
     * @return RedirectResponse
     */
    public function destroy(int $id): RedirectResponse
    {
        try {
            $this->packService->delete($id);

            $this->logger->info('Пакет успешно удален', [
                'source' => 'pack',
                'action' => 'delete',
                'user_id' => auth()->id(),
                'pack_id' => $id
            ]);

            return redirect('/admin/module/pack')
                ->with('success', 'Пакет успешно удален');
        } catch (Exception $e) {
            $this->logger->error('Ошибка при удалении пакета', [
                'source' => 'pack',
                'action' => 'delete',
                'user_id' => auth()->id(),
                'pack_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return back()->withErrors(['msg' => $e->getMessage()]);
        }
    }
}
