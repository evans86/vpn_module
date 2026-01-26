<?php

namespace App\Console\Commands;

use App\Models\Order\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class CleanOldOrderProofs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:clean-proofs 
                            {--days=90 : Количество дней, после которых удалять фотографии}
                            {--dry-run : Показать, что будет удалено, без фактического удаления}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Удаляет старые фотографии подтверждения оплаты для обработанных заказов';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $days = (int) $this->option('days');
        $dryRun = $this->option('dry-run');
        
        $this->info("Очистка фотографий подтверждения оплаты старше {$days} дней...");
        
        if ($dryRun) {
            $this->warn('РЕЖИМ ПРОВЕРКИ (DRY RUN) - файлы не будут удалены');
        }

        // Находим заказы, которые были обработаны (одобрены или отклонены) более N дней назад
        $cutoffDate = now()->subDays($days);
        
        $orders = Order::whereNotNull('payment_proof')
            ->whereIn('status', [Order::STATUS_APPROVED, Order::STATUS_REJECTED])
            ->where('updated_at', '<', $cutoffDate)
            ->get();

        $this->info("Найдено заказов для обработки: " . $orders->count());

        $deletedCount = 0;
        $errorCount = 0;
        $totalSize = 0;

        foreach ($orders as $order) {
            if (!$order->payment_proof) {
                continue;
            }

            $filePath = storage_path('app/public/' . $order->payment_proof);
            
            if (!File::exists($filePath)) {
                $this->warn("Файл не найден: {$order->payment_proof}");
                continue;
            }

            $fileSize = File::size($filePath);
            $totalSize += $fileSize;

            if ($dryRun) {
                $this->line("  [DRY RUN] Будет удален: {$order->payment_proof} (Заказ #{$order->id}, размер: " . $this->formatBytes($fileSize) . ")");
                $deletedCount++;
            } else {
                try {
                    File::delete($filePath);
                    
                    // Очищаем поле payment_proof в базе данных
                    $order->payment_proof = null;
                    $order->save();
                    
                    $deletedCount++;
                    $this->line("  Удален: {$order->payment_proof} (Заказ #{$order->id})");
                } catch (\Exception $e) {
                    $errorCount++;
                    $this->error("  Ошибка при удалении {$order->payment_proof}: " . $e->getMessage());
                    Log::error('Error deleting order proof', [
                        'order_id' => $order->id,
                        'file' => $order->payment_proof,
                        'error' => $e->getMessage(),
                        'source' => 'cron'
                    ]);
                }
            }
        }

        $this->newLine();
        $this->info("Результаты:");
        $this->info("  Удалено файлов: {$deletedCount}");
        $this->info("  Освобождено места: " . $this->formatBytes($totalSize));
        
        if ($errorCount > 0) {
            $this->warn("  Ошибок: {$errorCount}");
        }

        if ($dryRun) {
            $this->newLine();
            $this->warn('Это был режим проверки. Для реального удаления запустите команду без флага --dry-run');
        }

        Log::info('Order proofs cleanup completed', [
            'deleted_count' => $deletedCount,
            'total_size' => $totalSize,
            'days' => $days,
            'dry_run' => $dryRun,
            'source' => 'cron'
        ]);

        return Command::SUCCESS;
    }

    /**
     * Форматирует размер файла в читаемый вид
     */
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}

