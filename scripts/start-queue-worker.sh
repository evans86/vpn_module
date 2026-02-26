#!/bin/bash
# Запуск воркера очереди в фоне (можно закрыть консоль).
# Остановка: php artisan queue:restart

cd "$(dirname "$0")/.." || exit 1
nohup php artisan queue:work-safe >> storage/logs/queue-worker.log 2>&1 &
echo "Воркер запущен в фоне. Лог: storage/logs/queue-worker.log"
echo "Остановка: php artisan queue:restart"
