# Заглушка панели в Docker (только 80/443, статика + опционально fcgi)

Цель: проще обновлять `index.html` и скрипты (смонтировать каталог `stub-assets` с хоста), не трогая системный nginx.

## Быстрый старт

```bash
cd deploy/docker-panel-stub
# Положите SSL-файлы рядом или смонтируйте свои пути в docker-compose.yml
openssl req -x509 -nodes -days 3650 -newkey rsa:2048 \
  -keyout stub.key -out stub.crt -subj "/CN=localhost"
docker compose up -d
```

Проверка: `curl -sI http://127.0.0.1:8080/` (порт смотрите в `docker-compose.yml`).

## Ограничения

- Полный `/test-speed` и `server-probe-light` через **fcgiwrap** в контейнере не настраиваются в минимальном compose по умолчанию — только статика. Для CGI добавьте образ с `nginx` + `fcgiwrap` или используйте хостовый nginx по `deploy/nginx/panel-stub.default-server.conf`.

## Обновление контента

Меняйте файлы в `../stub-assets/` — volume примонтирован в `/usr/share/nginx/html`.
