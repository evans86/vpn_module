<?php

namespace App\Http\Controllers\Module;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pack\StorePackRequest;
use App\Http\Requests\Pack\UpdatePackRequest;
use App\Services\Pack\PackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class PackController extends Controller
{
    /** @var PackService */
    private $packService;

    /**
     * @param PackService $packService
     */
    public function __construct(PackService $packService)
    {
        $this->packService = $packService;
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
            return view('module.pack.index', compact('packs'));
        } catch (\Exception $e) {
            Log::error('Failed to fetch packs', ['error' => $e->getMessage()]);
            return back()->withErrors(['msg' => 'Ошибка при загрузке пакетов']);
        }
    }

    /**
     * Store a newly created pack.
     *
     * @param StorePackRequest $request
     * @return RedirectResponse
     */
    public function store(StorePackRequest $request)
    {
        try {
            $this->packService->create($request->validated());
            return redirect()->route('module.pack.index')
                ->with('success', 'Пакет успешно создан');
        } catch (\Exception $e) {
            Log::error('Failed to create pack', ['error' => $e->getMessage()]);
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
    public function update(UpdatePackRequest $request, int $id)
    {
        try {
            $this->packService->update($id, $request->validated());
            return redirect()->route('module.pack.index')
                ->with('success', 'Пакет успешно обновлен');
        } catch (\Exception $e) {
            Log::error('Failed to update pack', ['error' => $e->getMessage(), 'pack_id' => $id]);
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
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error('Failed to delete pack', ['error' => $e->getMessage(), 'pack_id' => $id]);
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при удалении пакета'
            ], 500);
        }
    }
}
