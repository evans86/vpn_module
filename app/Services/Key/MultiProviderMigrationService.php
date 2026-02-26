<?php

namespace App\Services\Key;

use App\Models\KeyActivate\KeyActivate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Запуск одной порции миграции на мульти-провайдер (без HTTP).
 * Используется и контроллером (run-batch), и фоновой очередью (Job).
 */
class MultiProviderMigrationService
{
    public function runOneBatch(int $offset, int $batchSize, bool $dryRun, ?int $maxTotal = null): array
    {
        $slots = config('panel.multi_provider_slots', []);
        $slots = is_array($slots) ? $slots : [];
        if (empty($slots)) {
            return [
                'success' => false,
                'done' => true,
                'processed' => 0,
                'added_total' => 0,
                'next_offset' => $offset,
                'remaining' => 0,
                'total' => 0,
                'errors' => [],
                'message' => 'Мульти-провайдер отключён.',
            ];
        }

        $limit = $maxTotal !== null ? min($batchSize, $maxTotal - $offset) : $batchSize;
        if ($limit < 1 && $maxTotal !== null) {
            return [
                'success' => true,
                'done' => true,
                'processed' => 0,
                'added_total' => 0,
                'next_offset' => $offset,
                'remaining' => 0,
                'total' => $offset,
                'errors' => [],
                'message' => 'Достигнут лимит (max_total).',
            ];
        }

        $ids = DB::table('key_activate as ka')
            ->where('ka.status', KeyActivate::ACTIVE)
            ->whereNotNull('ka.user_tg_id')
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('key_activate_user')
                    ->whereColumn('key_activate_user.key_activate_id', 'ka.id');
            })
            ->orderBy('ka.id')
            ->offset($offset)
            ->limit($limit)
            ->pluck('ka.id');

        $total = null;
        if ($maxTotal === null || $offset === 0) {
            $total = (int) DB::table('key_activate as ka')
                ->where('ka.status', KeyActivate::ACTIVE)
                ->whereNotNull('ka.user_tg_id')
                ->whereExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('key_activate_user')
                        ->whereColumn('key_activate_user.key_activate_id', 'ka.id');
                })
                ->count();
        }

        $keyActivateService = app(KeyActivateService::class);
        $addedTotal = 0;
        $errors = [];
        $processed = 0;

        foreach ($ids as $keyId) {
            $key = KeyActivate::query()->where('id', $keyId)->first();
            if (!$key) {
                continue;
            }
            try {
                $added = $keyActivateService->addMissingProviderSlots($key, $dryRun);
                $addedTotal += $added;
            } catch (\Throwable $e) {
                Log::warning('Multi-provider migration batch: failed key', [
                    'key_activate_id' => $keyId,
                    'error' => $e->getMessage(),
                ]);
                $errors[] = ['key_id' => $keyId, 'message' => $e->getMessage()];
            }
            $processed++;
            unset($key);
        }

        $nextOffset = $offset + $processed;
        if ($total === null) {
            $total = $nextOffset;
        }
        $remaining = max(0, $total - $nextOffset);
        $isTestRun = $maxTotal !== null && $processed >= $maxTotal;
        $done = $remaining <= 0 || $isTestRun;

        return [
            'success' => true,
            'done' => $done,
            'processed' => $processed,
            'added_total' => $addedTotal,
            'next_offset' => $nextOffset,
            'remaining' => $remaining,
            'total' => $total,
            'errors' => $errors,
            'message' => $dryRun
                ? "Проверка: обработано {$processed} ключей, было бы добавлено слотов: {$addedTotal}."
                : "Обработано {$processed} ключей, добавлено слотов: {$addedTotal}. Осталось ключей: {$remaining}.",
        ];
    }
}
