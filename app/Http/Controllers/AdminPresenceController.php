<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AdminPresenceController extends Controller
{
    private const CACHE_KEY = 'admin_presence_sessions';

    private const ONLINE_WINDOW_SECONDS = 90;

    private const CACHE_TTL_SECONDS = 86400;

    public function heartbeat(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return response()->json(['success' => false], 401);
        }

        $sessions = $this->freshSessions();
        $sessionId = $request->session()->getId();
        $presenceKey = ((int) $user->id).':'.sha1($sessionId !== '' ? $sessionId : (string) $user->id);
        $now = now();

        $sessions[$presenceKey] = [
            'user_id' => (int) $user->id,
            'name' => (string) ($user->name ?: $user->username ?: ('User #'.$user->id)),
            'username' => (string) ($user->username ?? ''),
            'ip' => $this->clientIp($request),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 160),
            'last_seen_at' => $now->toIso8601String(),
            'last_seen_human' => $now->format('H:i:s'),
        ];

        Cache::put(self::CACHE_KEY, $sessions, now()->addSeconds(self::CACHE_TTL_SECONDS));

        return response()->json([
            'success' => true,
            'online' => $this->visibleSessions($sessions),
        ]);
    }

    public function online(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'online' => $this->visibleSessions($this->freshSessions()),
        ]);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function freshSessions(): array
    {
        $sessions = Cache::get(self::CACHE_KEY, []);
        $sessions = is_array($sessions) ? $sessions : [];
        $cutoff = now()->subSeconds(self::ONLINE_WINDOW_SECONDS);
        $fresh = [];

        foreach ($sessions as $key => $row) {
            if (! is_array($row)) {
                continue;
            }
            $lastSeen = isset($row['last_seen_at']) ? strtotime((string) $row['last_seen_at']) : false;
            if ($lastSeen !== false && $lastSeen >= $cutoff->timestamp) {
                $fresh[(string) $key] = $row;
            }
        }

        if (count($fresh) !== count($sessions)) {
            Cache::put(self::CACHE_KEY, $fresh, now()->addSeconds(self::CACHE_TTL_SECONDS));
        }

        return $fresh;
    }

    /**
     * @param  array<string, array<string, mixed>>  $sessions
     * @return array<int, array<string, mixed>>
     */
    private function visibleSessions(array $sessions): array
    {
        $items = array_values($sessions);
        usort($items, static function (array $a, array $b): int {
            return strcmp((string) ($b['last_seen_at'] ?? ''), (string) ($a['last_seen_at'] ?? ''));
        });

        return array_map(static function (array $row): array {
            return [
                'name' => (string) ($row['name'] ?? ''),
                'username' => (string) ($row['username'] ?? ''),
                'ip' => (string) ($row['ip'] ?? ''),
                'last_seen_human' => (string) ($row['last_seen_human'] ?? ''),
            ];
        }, $items);
    }

    private function clientIp(Request $request): string
    {
        foreach (['CF-Connecting-IP', 'X-Real-IP'] as $header) {
            $value = trim((string) $request->headers->get($header, ''));
            if ($value !== '' && filter_var($value, FILTER_VALIDATE_IP)) {
                return $value;
            }
        }

        $xff = (string) $request->headers->get('X-Forwarded-For', '');
        foreach (explode(',', $xff) as $candidate) {
            $candidate = trim($candidate);
            if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_IP)) {
                return $candidate;
            }
        }

        return (string) $request->ip();
    }
}
