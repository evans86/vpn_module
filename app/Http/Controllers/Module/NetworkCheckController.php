<?php

namespace App\Http\Controllers\Module;

use App\Http\Controllers\Controller;
use App\Models\Salesman\Salesman;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class NetworkCheckController extends Controller
{
    public function index()
    {
//        $salesman = Salesman::where('telegram_id', 6715142449)->first();
        $salesman = Auth::guard('salesman')->user();

        $must = config('networkcheck.resources_must', []);
        $blocked = config('networkcheck.resources_often_blocked', []);
        $sizes = config('networkcheck.download_sizes', []);

        return view('module.personal.network.index', compact('salesman', 'must', 'blocked', 'sizes'));
    }

    // Быстрый endpoint для измерения задержки
    public function ping(Request $request)
    {
        return response()->json([
            'ok'         => true,
            'server_ts'  => microtime(true),
            'client_ip'  => $request->ip(),
            'server_addr'=> $request->server('SERVER_ADDR') ?? null,
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, private');
    }

    /**
     * Стрим рандомного/повторяющегося payload для теста скачивания.
     * URL-пример: /personal/network-check/payload/5mb
     */
    public function payload(string $size)
    {
        [$bytes, $label] = $this->parseSizeToBytes($size);
        $chunk = 64 * 1024; // 64KB
        $contentLength = $bytes;

        $headers = [
            'Content-Type'               => 'application/octet-stream',
            'Content-Disposition'        => 'inline; filename="payload_'.$label.'.bin"',
            'Cache-Control'              => 'no-store, no-cache, must-revalidate, private',
            'X-Content-Length-Bytes'     => (string)$contentLength,
            'Access-Control-Allow-Origin'=> '*', // важно для JS fetch
        ];

        $streamCallback = function () use ($bytes, $chunk) {
            $sent = 0;
            $buf = str_repeat('A', $chunk);
            while ($sent < $bytes && connection_aborted() === 0) {
                $toSend = min($chunk, $bytes - $sent);
                echo substr($buf, 0, $toSend);
                $sent += $toSend;
                if (function_exists('flush')) { flush(); }
            }
        };

        $response = new StreamedResponse($streamCallback, 200, $headers);
        $response->headers->set('Content-Length', (string)$contentLength);

        return $response;
    }

    public function report(Request $request)
    {
        $data = $request->validate([
            'summary'    => 'required|array',
            'latency'    => 'required|array',
            'download'   => 'required|array',
            'resources'  => 'required|array',
            'env'        => 'required|array',
            'startedAt'  => 'required|string',
            'finishedAt' => 'required|string',
            'packetLoss' => 'sometimes|array',
            'doh'        => 'sometimes|array',
            'regional'   => 'sometimes|array',
        ]);

//        $salesman = Salesman::where('telegram_id', 6715142449)->first();
        $salesman = Auth::guard('salesman')->user();

        try {
            $tzBrowser = $data['env']['tz'] ?? config('app.timezone', 'Europe/Moscow');
            try {
                $startLocal  = Carbon::parse($data['startedAt'])->tz($tzBrowser);
                $finishLocal = Carbon::parse($data['finishedAt'])->tz($tzBrowser);
            } catch (Throwable $e) {
                $startLocal  = Carbon::parse($data['startedAt'])->tz(config('app.timezone', 'Europe/Moscow'));
                $finishLocal = Carbon::parse($data['finishedAt'])->tz(config('app.timezone', 'Europe/Moscow'));
            }
            $data['period_display'] = [
                'tz'     => $tzBrowser,
                'start'  => $startLocal->format('d.m.Y H:i:s'),
                'finish' => $finishLocal->format('d.m.Y H:i:s'),
            ];

            $pdf = Pdf::loadView('module.personal.network.pdf', [
                'salesman'    => $salesman,
                'data'        => $data,
                'generatedAt' => now()->format('Y-m-d H:i:s'),
            ])->setPaper('a4');

            // ВАЖНО: отдаем контролируемо, чтобы исключить «мусор» в потоке
            $content  = $pdf->output();
            $filename = 'network-report-'. Str::uuid()->toString().'.pdf';

            return response($content, 200, [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
                'Cache-Control'       => 'no-store, no-cache, must-revalidate, private',
            ]);
        } catch (Throwable $e) {
            Log::error('PDF generation failed', ['e' => $e]);
            return response()->json([
                'ok' => false,
                'message' => 'PDF generation failed',
            ], 500);
        }
    }

    private function parseSizeToBytes(string $size): array
    {
        $size = strtolower($size);

        if (preg_match('/^(\d+)(kb|mb|b)$/', $size, $m)) {
            $n    = (int) $m[1];
            $unit = $m[2];

            // PHP 7.4‑friendly
            switch ($unit) {
                case 'mb':
                    $bytes = $n * 1024 * 1024;
                    break;
                case 'kb':
                    $bytes = $n * 1024;
                    break;
                case 'b':
                default:
                    $bytes = $n;
                    break;
            }

            return [$bytes, $n . $unit];
        }

        // дефолт — 1mb
        return [1024 * 1024, '1mb'];
    }

}

