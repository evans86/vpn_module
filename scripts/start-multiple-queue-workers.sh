#!/bin/bash
# Запуск нескольких воркеров очереди в фоне (для параллельной миграции).
# Использование: bash scripts/start-multiple-queue-workers.sh [N]
# По умолчанию N=5. Каждый воркер — цикл (один джоб — один процесс), лог общий.
# Остановка: pkill -f "start-queue-worker-loop"  (убивает все циклы)

cd "$(dirname "$0")/.." || exit 1
N="${1:-5}"
LOG="storage/logs/queue-worker.log"

if ! [[ "$N" =~ ^[0-9]+$ ]] || [ "$N" -lt 1 ] || [ "$N" -gt 20 ]; then
    echo "Использование: $0 [N]   — N от 1 до 20 (по умолчанию 5)"
    exit 1
fi

echo "[$(date '+%Y-%m-%d %H:%M:%S')] Запуск $N воркеров (цикл)." >> "$LOG"
for i in $(seq 1 "$N"); do
    nohup bash scripts/start-queue-worker-loop.sh >> "$LOG" 2>&1 &
done
echo "Запущено воркеров: $N. Лог: $LOG"
echo "Остановка всех: pkill -f start-queue-worker-loop"
