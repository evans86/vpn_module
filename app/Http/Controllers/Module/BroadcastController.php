<?php

namespace App\Http\Controllers\Module;

use App\Http\Controllers\Controller;
use App\Models\Broadcast\BroadcastCampaign;
use App\Services\Broadcast\BroadcastService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BroadcastController extends Controller
{
    /** @var BroadcastService */
    private $broadcastService;

    public function __construct(BroadcastService $broadcastService)
    {
        $this->broadcastService = $broadcastService;
    }

    /**
     * Список рассылок.
     */
    public function index(): View
    {
        $campaigns = BroadcastCampaign::query()
            ->orderByDesc('created_at')
            ->paginate(15);

        return view('module.broadcast.index', [
            'campaigns' => $campaigns,
        ]);
    }

    /**
     * Форма создания рассылки.
     */
    public function create(): View
    {
        $eligibleCount = count($this->broadcastService->getEligibleKeyActivateIds());

        return view('module.broadcast.create', [
            'eligibleCount' => $eligibleCount,
        ]);
    }

    /**
     * Сохранить рассылку (черновик) и перейти к просмотру.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:4096'],
        ], [
            'name.required' => 'Введите название рассылки.',
            'message.required' => 'Введите текст сообщения.',
        ]);

        $campaign = $this->broadcastService->createCampaignWithRecipients(
            $validated['name'],
            $validated['message']
        );

        return redirect()
            ->route('admin.module.broadcast.show', $campaign)
            ->with('success', 'Рассылка создана. Получателей: ' . $campaign->total_recipients . '. Запустите рассылку, когда будете готовы.');
    }

    /**
     * Просмотр рассылки (статус, доставлено/не доставлено, текст).
     */
    public function show(BroadcastCampaign $broadcast): View
    {
        return view('module.broadcast.show', [
            'campaign' => $broadcast,
        ]);
    }

    /**
     * Запустить рассылку (поставить в очередь).
     */
    public function start(BroadcastCampaign $broadcast): RedirectResponse
    {
        if (!$broadcast->isDraft()) {
            return redirect()
                ->route('admin.module.broadcast.show', $broadcast)
                ->with('error', 'Запустить можно только рассылку в статусе «Черновик».');
        }

        $this->broadcastService->startCampaign($broadcast);

        return redirect()
            ->route('admin.module.broadcast.show', $broadcast)
            ->with('success', 'Рассылка поставлена в очередь. Сообщения будут отправляться в фоне.');
    }
}
