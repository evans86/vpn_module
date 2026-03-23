<?php

namespace App\Support;

use Illuminate\Contracts\Cache\Lock as CacheLockContract;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Исключает параллельную активацию одного ключа в Telegram без Redis:
 * MySQL/MariaDB — GET_LOCK (работает между PHP-процессами на одном сервере БД).
 * Иначе — Cache::lock (file/database cache).
 */
final class KeyActivationMutex
{
    /** @var bool */
    private $useMysql;

    /** @var string */
    private $mysqlLockName;

    /** @var CacheLockContract|null */
    private $cacheLock;

    private function __construct(bool $useMysql, string $mysqlLockName, ?CacheLockContract $cacheLock = null)
    {
        $this->useMysql = $useMysql;
        $this->mysqlLockName = $mysqlLockName;
        $this->cacheLock = $cacheLock;
    }

    public function release(): void
    {
        if ($this->useMysql) {
            DB::select('SELECT RELEASE_LOCK(?)', [$this->mysqlLockName]);

            return;
        }

        if ($this->cacheLock !== null) {
            $this->cacheLock->release();
        }
    }

    /**
     * @return self|null null — блок занят другим запросом
     */
    public static function tryAcquire(string $keyId, int $chatId, int $ttlSeconds = 300): ?self
    {
        $driver = DB::connection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $name = self::mysqlLockName($keyId, $chatId);
            $row = DB::selectOne('SELECT GET_LOCK(?, 0) AS acquired', [$name]);
            $ok = isset($row->acquired) && (int) $row->acquired === 1;
            if (!$ok) {
                return null;
            }

            return new self(true, $name, null);
        }

        $cacheLock = Cache::lock('telegram_key_activation:' . $keyId . ':' . $chatId, $ttlSeconds);
        if (!$cacheLock->get()) {
            return null;
        }

        return new self(false, '', $cacheLock);
    }

    /**
     * Имя для GET_LOCK (ограничение MySQL — 64 символа).
     */
    private static function mysqlLockName(string $keyId, int $chatId): string
    {
        return 'ka_' . md5($keyId . ':' . $chatId);
    }
}
