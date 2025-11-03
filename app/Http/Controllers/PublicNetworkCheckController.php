<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class PublicNetworkCheckController extends Controller
{
    public function index()
    {
        $brand = config('app.brand', 'VPN Service');

        $targets = [
            'local_services' => [
                ['label' => 'Яндекс', 'url' => 'https://yandex.ru/favicon.ico'],
                ['label' => 'Госуслуги', 'url' => 'https://www.gosuslugi.ru/favicon.ico'],
                ['label' => 'Сбербанк', 'url' => 'https://www.sberbank.ru/favicon.ico'],
                ['label' => 'ВКонтакте', 'url' => 'https://vk.com/favicon.ico'],
            ],
            'global_services' => [
                ['label' => 'YouTube', 'url' => 'https://www.youtube.com/favicon.ico'],
                ['label' => 'Telegram', 'url' => 'https://telegram.org/favicon.ico'],
                ['label' => 'WhatsApp', 'url' => 'https://web.whatsapp.com/favicon.ico'],
                ['label' => 'Instagram', 'url' => 'https://www.instagram.com/favicon.ico'],
                ['label' => 'Twitter/X', 'url' => 'https://twitter.com/favicon.ico'],
                ['label' => 'Facebook', 'url' => 'https://www.facebook.com/favicon.ico'],
                ['label' => 'Google', 'url' => 'https://google.com/favicon.ico'],
                ['label' => 'Netflix', 'url' => 'https://netflix.com/favicon.ico'],
            ],
            'network_health' => [
                ['label' => 'Проверка DNS', 'url' => 'https://dns.google/resolve?name=google.com&type=A'],
                ['label' => 'Основной шлюз', 'url' => 'https://api.ipify.org?format=json'],
                ['label' => 'Резервный шлюз', 'url' => 'https://www.gstatic.com/generate_204'],
            ]
        ];

        return view('netcheck.public.simple', compact('brand', 'targets'));
    }

    public function ping(Request $request)
    {
        $resp = response()->json([
            'ok'          => true,
            'server_ts'   => microtime(true),
            'client_ip'   => $request->ip(),
            'server_addr' => $request->server('SERVER_ADDR') ?? null,
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, private')
            ->header('Access-Control-Allow-Origin', '*');

        return $resp;
    }

    public function telemetry(Request $request)
    {
        try {
            $data = $request->validate([
                'event' => ['required','string', Rule::in([
                    'run:start','checkpoint','run:done','run:error','window:error','window:unhandledrejection'
                ])],
                'runId'  => 'nullable|string|max:100',
                'pct'    => 'nullable|integer|in:10,20,30,40,50,60,70,80,90,100',
                'label'  => 'nullable|string|max:120',
                'status' => 'nullable|string|in:success,error',
                'error'  => 'nullable|string|max:2000',
                'message'=> 'nullable|string|max:2000',
                'reason' => 'nullable|string|max:2000',
                'ua'     => 'nullable|string|max:2000',
                'tz'     => 'nullable|string|max:100',
                'ts'     => 'nullable|string|max:64',
            ]);

            $log = [
                'ip'        => $request->ip(),
                'ua'        => $data['ua'] ?? $request->userAgent(),
                'ts'        => now()->toIso8601String(),
                'runId'     => $data['runId'] ?? null,
                'event'     => $data['event'],
                'pct'       => $data['pct'] ?? null,
                'label'     => $data['label'] ?? null,
                'status'    => $data['status'] ?? null,
                'error'     => $data['error'] ?? $data['message'] ?? $data['reason'] ?? null,
                'tz'        => $data['tz'] ?? null,
                'client_ts' => $data['ts'] ?? null,
            ];

            Log::info('netcheck telemetry', $log);

            return response()->json(['ok' => true])
                ->header('Cache-Control', 'no-store, no-cache, must-revalidate, private');
        } catch (Throwable $e) {
            Log::error('netcheck telemetry invalid', [
                'ip' => $request->ip(),
                'message' => $e->getMessage(),
            ]);
            return response()->json(['ok' => false, 'message' => 'invalid payload'], 400);
        }
    }

    public function payload(string $size)
    {
        [$bytes, $label] = $this->parseSizeToBytes($size);
        $chunk = 64 * 1024;

        $headers = [
            'Content-Type'                => 'application/octet-stream',
            'Content-Disposition'         => 'inline; filename="speedtest_'.$label.'.bin"',
            'Cache-Control'               => 'no-store, no-cache, must-revalidate, private',
            'X-Content-Length-Bytes'      => (string) $bytes,
            'Access-Control-Allow-Origin' => '*',
        ];

        $stream = function () use ($bytes, $chunk) {
            $sent = 0;
            $buf  = str_repeat('A', $chunk);
            while ($sent < $bytes && connection_aborted() === 0) {
                $n = min($chunk, $bytes - $sent);
                echo substr($buf, 0, $n);
                $sent += $n;
                if (function_exists('flush')) { flush(); }
            }
        };

        $resp = new StreamedResponse($stream, 200, $headers);
        $resp->headers->set('Content-Length', (string) $bytes);
        return $resp;
    }

    public function report(Request $request)
    {
        $data = $request->validate([
            'summary'     => 'required|array',
            'latency'     => 'required|array',
            'download'    => 'required|array',
            'resources'   => 'required|array',
            'env'         => 'required|array',
            'startedAt'   => 'required|string',
            'finishedAt'  => 'required|string',
        ]);

        try {
            $tz = $data['env']['tz'] ?? config('app.timezone', 'UTC');

            try {
                $start  = Carbon::parse($data['startedAt'])->tz($tz);
                $finish = Carbon::parse($data['finishedAt'])->tz($tz);
            } catch (Throwable $e) {
                $start  = Carbon::parse($data['startedAt'])->tz(config('app.timezone', 'UTC'));
                $finish = Carbon::parse($data['finishedAt'])->tz(config('app.timezone', 'UTC'));
            }

            $data['period_display'] = [
                'tz'     => $tz,
                'start'  => $start->format('d.m.Y H:i:s'),
                'finish' => $finish->format('d.m.Y H:i:s'),
            ];

            Log::info('PDF generation data', [
                'resources' => $data['resources'] ?? [],
                'summary' => $data['summary'] ?? []
            ]);

            if (ob_get_length()) { @ob_end_clean(); }

            $pdf = Pdf::setOptions([
                'isRemoteEnabled' => false,
                'enable_php'      => false,
            ])->loadView('netcheck.public.simple-pdf', [
                'data'        => $data,
                'generatedAt' => now()->format('d.m.Y H:i:s'),
                'brand'       => config('app.brand', 'VPN Service'),
            ])->setPaper('a4');

            $content  = $pdf->output();
            $filename = 'network-report-'.Str::uuid()->toString().'.pdf';

            return response($content, 200, [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
                'Cache-Control'       => 'no-store, no-cache, must-revalidate, private',
            ]);
        } catch (Throwable $e) {
            Log::error('Public PDF generation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data' => $data ?? []
            ]);
            return response()->json(['ok' => false, 'message' => 'PDF generation failed: ' . $e->getMessage()], 500);
        }
    }

    private function parseSizeToBytes(string $size): array
    {
        $size = strtolower($size);
        if (preg_match('/^(\d+)(kb|mb|b)$/', $size, $m)) {
            $n = (int)$m[1]; $u = $m[2];
            if ($u === 'mb') $bytes = $n * 1024 * 1024;
            elseif ($u === 'kb') $bytes = $n * 1024;
            else $bytes = $n;
            return [$bytes, $n.$u];
        }
        return [1024 * 1024, '1mb'];
    }
}
