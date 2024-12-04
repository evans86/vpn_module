## VPN MODULE

# VPN проект

## Требования
- PHP 7.4
- Composer 2.x
- MySQL 5.7+

## Шаги по развертыванию

### 1. Клонирование репозитория в текущую папку
```bash
git clone https://github.com/evans86/vpn_module.git .
```

### 2. Установка зависимостей
```bash
composer install
```

### 3. Настройка окружения
```bash
cp .env.example .env
php artisan key:generate
```

Отредактируйте файл `.env`, установив правильные значения для:
- Подключения к базе данных (DB_*)
- TELEGRAM_BOT_TOKEN
- TELEGRAM_WEBHOOK_URL
- CLOUDFLARE_API_KEY (если используется)
- CLOUDFLARE_EMAIL (если используется)

### 4. Настройка базы данных
```bash
php artisan migrate --seed
```

### 5. Настройка прав доступа
```bash
chmod -R 755 storage bootstrap/cache
```

### 6. Настройка webhook для Telegram бота
```bash
curl -F "url=https://ваш-домен.com/webhook/telegram" https://api.telegram.org/botВАШ_ТОКЕН/setWebhook
```

### 7. Обновление
```bash
git pull
composer install
php artisan migrate --seed
php artisan optimize:clear
```

## Проверка работоспособности
После развертывания проверьте:
1. Доступность сайта по настроенному домену
2. Работу авторизации
3. Основной функционал
4. Логи на наличие ошибок (storage/logs)

## Поддержка
При возникновении проблем проверьте:
- Права доступа к файлам и папкам
- Настройки .env файла
- Логи Laravel (storage/logs/laravel.log)
- Логи веб-сервера
