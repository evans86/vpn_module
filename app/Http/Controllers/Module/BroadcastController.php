<?php

namespace App\Http\Controllers\Module;

use App\Http\Controllers\Controller;
use App\Models\Broadcast\BroadcastCampaign;
use App\Models\KeyActivate\KeyActivate;
use App\Models\TelegramUser\TelegramUser;
use App\Services\Broadcast\BroadcastService;
use App\Services\Notification\TelegramNotificationService;
use Illuminate\Http\JsonResponse;
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
        return view('module.broadcast.show', [
            'campaign' => $broadcast,
        ]);
    }

    /**
     * Поиск пользователей для тестовой рассылки (по Telegram ID или username). Без перезагрузки.
     */
    public function searchUsers(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));
        if ($q === '') {
            return response()->json([]);
        }

        $like = '%' . $q . '%';

        $byTg = KeyActivate::query()
            ->where('status', KeyActivate::ACTIVE)
            ->whereNotNull('user_tg_id')
            ->where('user_tg_id', 'like', $like)
            ->selectRaw('MIN(id) as id, user_tg_id')
            ->groupBy('user_tg_id')
            ->limit(25)
            ->get();

        $tgIdsFromUsername = TelegramUser::query()
            ->where('username', 'like', $like)
            ->pluck('telegram_id')
            ->unique()
            ->filter()
            ->values()
            ->all();

        $byUsername = collect();
        if (count($tgIdsFromUsername) > 0) {
            $byUsername = KeyActivate::query()
                ->where('status', KeyActivate::ACTIVE)
                ->whereNotNull('user_tg_id')
                ->whereIn('user_tg_id', $tgIdsFromUsername)
                ->selectRaw('MIN(id) as id, user_tg_id')
                ->groupBy('user_tg_id')
                ->limit(25)
                ->get();
        }

        $merged = $byTg->concat($byUsername)->unique('id')->take(50)->values();
        $userTgIds = $merged->pluck('user_tg_id')->unique()->values()->all();

        $usernames = TelegramUser::query()
            ->whereIn('telegram_id', $userTgIds)
            ->pluck('username', 'telegram_id')
            ->all();

        $result = $merged->map(function ($row) use ($usernames) {
            return [
                'key_activate_id' => $row->id,
                'user_tg_id' => $row->user_tg_id,
                'username' => $usernames[$row->user_tg_id] ?? null,
            ];
        })->all();

        return response()->json($result);
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

        $keyIds = $request->input('key_activate_ids', []);
        if (is_string($keyIds)) {
            $keyIds = json_decode($keyIds, true) ?: [];
        }
        if (!is_array($keyIds)) {
            $keyIds = [];
        }
        $keyIds = array_slice(array_filter(array_map('strval', $keyIds)), 0, 20);

        if (count($keyIds) === 0) {
            return redirect()
                ->route('admin.module.broadcast.show', $broadcast)
                ->with('error', 'Выберите хотя бы одного получателя для теста.');
        }

        $keys = KeyActivate::query()
            ->whereIn('id', $keyIds)
            ->where('status', KeyActivate::ACTIVE)
            ->whereNotNull('user_tg_id')
            ->get();

        $delivered = 0;
        $failed = 0;

        foreach ($keys as $key) {
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
