<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class PublicNetworkCheckController extends Controller
{
    public function index()
    {
        $must     = config('networkcheck.resources_must', []);
        $blocked  = config('networkcheck.resources_often_blocked', []);
        $sizes    = config('networkcheck.download_sizes', []);
        $youtube  = config('networkcheck.youtube', []);
        $ru       = config('networkcheck.ru_services', []);
        $mess     = config('networkcheck.messengers', []);
        $http80   = config('networkcheck.http_probe', []);
        $doh      = config('networkcheck.doh_domains', []);
        $regions  = config('networkcheck.regional_probes', []);

        return view('netcheck.public.index', compact(
            'must','blocked','sizes','youtube','ru','mess','http80','doh','regions'
        ));
    }

    public function ping(Request $request)
    {
        return response()->json([
            'ok'          => true,
            'server_ts'   => microtime(true),
            'client_ip'   => $request->ip(),
            'server_addr' => $request->server('SERVER_ADDR') ?? null,
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, private');
    }

    public function payload(string $size)
    {
        [$bytes, $label] = $this->parseSizeToBytes($size);
        $chunk = 64 * 1024;

        $headers = [
            'Content-Type'                => 'application/octet-stream',
            'Content-Disposition'         => 'inline; filename="payload_'.$label.'.bin"',
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
            'packetLoss'  => 'sometimes|array',
            'doh'         => 'sometimes|array',
            'regional'    => 'sometimes|array',
            'youtube'     => 'sometimes|array',
            'ru_services' => 'sometimes|array',
            'messengers'  => 'sometimes|array',
            'http80'      => 'sometimes|array',
            'voip'        => 'sometimes|array',
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

            if (ob_get_length()) { ob_end_clean(); }

            // Важно: запретим внешние ресурсы и PHP в шаблоне
            $pdf = Pdf::setOptions([
                'isRemoteEnabled' => false,
                'enable_php'      => false,
            ])->loadView('netcheck.public.pdf', [
                'data'        => $data,
                'generatedAt' => now()->format('d.m.Y H:i:s'),
            ])->setPaper('a4');

            $content  = $pdf->output();
            $filename = 'network-report-'.\Illuminate\Support\Str::uuid()->toString().'.pdf';

            return response($content, 200, [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
                'Cache-Control'       => 'no-store, no-cache, must-revalidate, private',
            ]);
        } catch (\Throwable $e) {
            Log::error('Public PDF generation failed', ['e' => $e]);
            return response()->json(['ok' => false, 'message' => 'PDF generation failed'], 500);
        }
    }

    // PHP 7.4 совместимый
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
