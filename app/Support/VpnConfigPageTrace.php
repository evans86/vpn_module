<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

/**
 * Временные метки по цепочке /config — в storage/logs/config-trace.log (канал config_trace).
 * Включается: VPN_CONFIG_TRACE=true, опционально VPN_CONFIG_TRACE_TOKENS=uuid1,uuid2
 */
final class VpnConfigPageTrace
{
    /** @var array<string, mixed>|null */
    private static ?array $ctx = null;

    private static bool $shutdownRegistered = false;

    public static function isActive(): bool
    {
        return self::$ctx !== null;
    }

    public static function shouldTrace(string $keyActivateId): bool
    {
        $cfg = config('vpn.config_trace', []);
        if (!($cfg['enabled'] ?? false)) {
            return false;
        }
        $tokens = $cfg['tokens'] ?? [];
        if ($tokens === []) {
            return true;
        }

        return in_array($keyActivateId, $tokens, true);
    }

    public static function begin(string $keyActivateId, string $endpoint, array $extra = []): void
    {
        if (!self::shouldTrace($keyActivateId)) {
            return;
        }
        self::$ctx = [
            'key' => $keyActivateId,
            'endpoint' => $endpoint,
            't0' => microtime(true),
            'method' => request()->method(),
            'path' => request()->path(),
            'ua' => mb_substr((string) (request()->header('User-Agent') ?? ''), 0, 200),
        ];
        self::registerShutdownOnce();
        self::write('BEGIN', $extra);
    }

    /**
     * @param array<string, mixed> $extra
     */
    public static function checkpoint(string $stage, array $extra = []): void
    {
        if (self::$ctx === null) {
            return;
        }
        self::write($stage, $extra);
    }

    /**
     * Ветка plain text подписки — те же записи в config-trace.log, stage с префиксом subscription_ (grep: subscription_SUB_).
     *
     * @param array<string, mixed> $extra segment_ms — длительность шага (мс)
     */
    public static function subscription(string $keyActivateId, string $label, array $extra = []): void
    {
        if (!self::shouldTrace($keyActivateId) || self::$ctx === null) {
            return;
        }
        self::write('subscription_' . $label, array_merge([
            'subscription_label' => $label,
        ], $extra));
    }

    /**
     * @param array<string, mixed> $extra
     */
    public static function end(array $extra = []): void
    {
        if (self::$ctx === null) {
            return;
        }
        self::write('END', $extra);
        self::$ctx = null;
    }

    /**
     * Не завершили цепочку (таймаут PHP, fatal, kill) — последняя запись покажет, на каком этапе оборвалось.
     */
    public static function onShutdown(): void
    {
        if (self::$ctx === null) {
            return;
        }
        $err = error_get_last();
        $extra = [
            'shutdown' => true,
            'memory_peak_mb' => round(memory_get_peak_usage(true) / 1048576, 2),
        ];
        if (is_array($err) && isset($err['message'])) {
            $extra['last_error'] = $err['message'];
            $extra['last_error_file'] = $err['file'] ?? null;
            $extra['last_error_line'] = $err['line'] ?? null;
        }
        self::write('SHUTDOWN_INCOMPLETE', $extra);
        self::$ctx = null;
    }

    private static function registerShutdownOnce(): void
    {
        if (self::$shutdownRegistered) {
            return;
        }
        self::$shutdownRegistered = true;
        register_shutdown_function(static function (): void {
            self::onShutdown();
        });
    }

    /**
     * @param array<string, mixed> $extra
     */
    private static function write(string $stage, array $extra): void
    {
        if (self::$ctx === null) {
            return;
        }
        $t0 = (float) self::$ctx['t0'];
        $now = microtime(true);
        $elapsedMs = round(($now - $t0) * 1000, 2);
        $wall = \Carbon\Carbon::createFromTimestamp($now)->format('Y-m-d H:i:s.u');

        Log::channel('config_trace')->info('vpn_config_timing', array_merge([
            'key' => self::$ctx['key'],
            'endpoint' => self::$ctx['endpoint'],
            'stage' => $stage,
            'elapsed_ms' => $elapsedMs,
            'wall_time' => $wall,
            'method' => self::$ctx['method'],
            'path' => self::$ctx['path'],
            'memory_mb' => round(memory_get_usage(true) / 1048576, 2),
        ], $extra));
    }
}
