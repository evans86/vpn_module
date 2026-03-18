<?php

namespace App\Http\Controllers\Module;

use App\Http\Controllers\Controller;
use App\Models\Broadcast\BroadcastCampaign;
use App\Services\Broadcast\BroadcastService;
use App\Services\Notification\TelegramNotificationService;
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
        $eligibleCount = $this->broadcastService->getEligibleRecipientsCount();

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
        $recipientsForTest = [];
        if ($broadcast->isDraft()) {
            $recipientsForTest = $broadcast->recipients()
                ->with('keyActivate')
                ->limit(200)
                ->get();
        }

        return view('module.broadcast.show', [
            'campaign' => $broadcast,
            'recipientsForTest' => $recipientsForTest,
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

    /**
     * Остановить рассылку (статусы «В очереди» или «Выполняется»).
     */
    public function cancel(BroadcastCampaign $broadcast): RedirectResponse
    {
        if (!$broadcast->isRunning()) {
            return redirect()
                ->route('admin.module.broadcast.show', $broadcast)
                ->with('error', 'Остановить можно только рассылку в статусе «В очереди» или «Выполняется».');
        }

        $broadcast->update([
            'status' => BroadcastCampaign::STATUS_CANCELLED,
            'completed_at' => now(),
        ]);

        return redirect()
            ->route('admin.module.broadcast.show', $broadcast)
            ->with('success', 'Рассылка остановлена. Уже отправленные сообщения остаются доставленными.');
    }

    /**
     * Отправить тестовую рассылку нескольким получателям (без изменения кампании).
     */
    public function testSend(BroadcastCampaign $broadcast, Request $request, TelegramNotificationService $notification): RedirectResponse
    {
        if (!$broadcast->isDraft()) {
            return redirect()
                ->route('admin.module.broadcast.show', $broadcast)
                ->with('error', 'Тестовая отправка доступна только для рассылки в статусе «Черновик».');
        }

        $recipientIds = $request->input('recipient_ids', []);
        if (is_array($recipientIds) && count($recipientIds) > 0) {
            $recipientIds = array_slice(array_filter(array_map('intval', $recipientIds)), 0, 20);
            $recipients = $broadcast->recipients()
                ->with('keyActivate')
                ->whereIn('id', $recipientIds)
                ->get();
        } else {
            $request->validate([
                'count' => ['required', 'integer', 'min' => 1, 'max' => 20],
            ], [
                'count.required' => 'Укажите количество получателей или выберите их в списке.',
                'count.min' => 'Минимум 1 получатель.',
                'count.max' => 'Не более 20 получателей для теста.',
            ]);
            $recipients = $broadcast->recipients()
                ->with('keyActivate')
                ->limit((int) $request->input('count'))
                ->get();
        }

        $delivered = 0;
        $failed = 0;

        foreach ($recipients as $recipient) {
            $key = $recipient->keyActivate;
            if (!$key) {
                $failed++;
                continue;
            }
            $result = $notification->sendToUserWithResult($key, $broadcast->message, null);
            if ($result->shouldCountAsSent) {
                $delivered++;
            } else {
                $failed++;
            }
        }

        $total = $delivered + $failed;
        $message = $total === 0
            ? 'Нет получателей для теста.'
            : "Тестовая рассылка отправлена: доставлено {$delivered}, не доставлено {$failed} (всего {$total}).";

        return redirect()
            ->route('admin.module.broadcast.show', $broadcast)
            ->with('success', $message);
    }
}
