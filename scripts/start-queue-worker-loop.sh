#!/bin/bash
# Воркер очереди в цикле: по одному джобу за запуск, затем новый процесс.
# Память не накапливается — после каждой задачи процесс завершается.
# Остановка: pkill -f "start-queue-worker-loop" или убить основной процесс.

cd "$(dirname "$0")/.." || exit 1
LOG="storage/logs/queue-worker.log"

echo "[$(date '+%Y-%m-%d %H:%M:%S')] Запуск воркера в режиме «один джоб — один процесс»." >> "$LOG"

while true; do
    php artisan queue:work-safe --max-jobs=1 --stop-when-empty >> "$LOG" 2>&1
    sleep 2
done
