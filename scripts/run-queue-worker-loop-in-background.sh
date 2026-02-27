#!/bin/bash
# Запуск воркера в режиме «цикл» в фоне. Вызывайте эту команду без & в конце.
# Остановка: pkill -f "start-queue-worker-loop"

cd "$(dirname "$0")/.." || exit 1
nohup bash scripts/start-queue-worker-loop.sh >> storage/logs/queue-worker.log 2>&1 &
echo "Воркер (цикл) запущен в фоне. Лог: storage/logs/queue-worker.log"
echo "Остановка: pkill -f start-queue-worker-loop"
