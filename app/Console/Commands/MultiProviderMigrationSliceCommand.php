<?php

namespace App\Console\Commands;

use App\Services\Key\MultiProviderMigrationService;
use Illuminate\Console\Command;

/**
 * Один "срез" миграции на мульти-провайдер: обрабатывает ключи в диапазоне [offset, offset+max).
 * Для ускорения запускайте несколько терминалов с разными --offset и --max (параллельно).
 *
 * Пример (10 воркеров по ~8400 ключей при 84043 кандидатах):
 *   Терминал 1: php artisan multi-provider-migration:slice --offset=0      --max=8405
 *   Терминал 2: php artisan multi-provider-migration:slice --offset=8405   --max=8405
 *   ...
 *   Терминал 10: php artisan multi-provider-migration:slice --offset=75645 --max=8398
 *
 * Рекомендуется --batch=200..500 (меньше — меньше риск таймаута и нагрузки на API панелей).
 */
class MultiProviderMigrationSliceCommand extends Command
{
    protected $signature = 'multi-provider-migration:slice
                            {--offset=0 : Начальный сдвиг (индекс первого ключа в выборке)}
                            {--max=10000 : Сколько ключей обработать в этом срезе}
                            {--batch=500 : Размер одной порции (меньше — стабильнее при параллельном запуске)}
                            {--dry-run : Только проверка, слоты не создаются}';

    protected $description = 'Обработать один срез ключей для мульти-провайдерной миграции (для параллельного запуска в нескольких терминалах)';

    public function handle(MultiProviderMigrationService $service): int
    {
        $offset = (int) $this->option('offset');
        $max = (int) $this->option('max');
        $batch = (int) $this->option('batch');
        $dryRun = (bool) $this->option('dry-run');

        if ($max < 1 || $batch < 1) {
            $this->error('--max и --batch должны быть >= 1.');
            return 1;
        }

        $totalCount = $service->getTotalCount();
        if ($totalCount === 0) {
            $this->warn('Нет ключей-кандидатов для миграции (мульти-провайдер отключён или выборка пуста).');
            return 0;
        }

        $endOffset = $offset + $max;
        $this->info("Срез: ключи с offset {$offset} по " . ($endOffset - 1) . " (макс. {$max} ключей), порция {$batch}, dry-run=" . ($dryRun ? 'да' : 'нет') . '.');
        $this->newLine();

        $sliceProcessed = 0;
        $sliceAdded = 0;
        $currentOffset = $offset;

        while (true) {
            $result = $service->runOneBatch($currentOffset, $batch, $dryRun, $endOffset);

            if (!($result['success'] ?? false)) {
                $this->error($result['message'] ?? 'Ошибка выполнения порции.');
                return 1;
            }

            $sliceProcessed += $result['processed'];
            $sliceAdded += $result['added_total'] ?? 0;

            $this->line(sprintf(
                '  offset %d: обработано %d, добавлено слотов %d; всего в срезе: %d ключей, %d слотов. %s',
                $currentOffset,
                $result['processed'],
                $result['added_total'] ?? 0,
                $sliceProcessed,
                $sliceAdded,
                $result['message'] ?? ''
            ));

            if (!empty($result['errors'])) {
                foreach ($result['errors'] as $err) {
                    $this->warn('    Ошибка key_id=' . ($err['key_id'] ?? '?') . ': ' . ($err['message'] ?? ''));
                }
            }

            if ($result['done'] ?? false) {
                break;
            }

            $currentOffset = $result['next_offset'];
            if ($currentOffset >= $endOffset) {
                break;
            }
        }

        $this->newLine();
        $this->info("Срез завершён: обработано ключей {$sliceProcessed}, добавлено слотов {$sliceAdded}.");

        return 0;
    }
}
