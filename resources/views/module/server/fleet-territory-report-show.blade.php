@extends('layouts.admin')

@section('title', $title)
@section('page-title', $pageTitle)

@section('content')
    <div class="space-y-4 text-sm">
        @if(session('success'))
            <div class="rounded-md bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3">
                {{ session('success') }}
            </div>
        @endif

        <div class="flex flex-wrap gap-3 items-center justify-between">
            <a href="{{ route('admin.module.server-fleet.report') }}#external-probes"
               class="text-indigo-600 hover:text-indigo-800 text-sm">← К проверке серверов</a>
            <span class="text-xs text-slate-500">№ {{ $report->id }} · {{ optional($report->created_at)->format('Y-m-d H:i') }}</span>
        </div>

        <x-admin.card title="Территория и сеть (из отчёта)">
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2 text-sm">
                <div class="sm:col-span-2">
                    <dt class="text-xs text-slate-500">Метка</dt>
                    <dd class="font-medium">{{ $report->submitter_note ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-500">Режим</dt>
                    <dd>{{ $report->mode_label ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-500">IP для GeoIP</dt>
                    <dd class="font-mono">{{ $report->public_ip ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-500">Сервис GeoIP</dt>
                    <dd>{{ $report->geo_service ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-500">Страна</dt>
                    <dd>
                        @if($report->country_name || $report->country_code)
                            {{ $report->country_name }}
                            @if($report->country_code)
                                <span class="text-slate-500">({{ $report->country_code }})</span>
                            @endif
                        @else
                            —
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-500">Регион / город</dt>
                    <dd>{{ $report->region ?? '—' }} @if($report->city) / {{ $report->city }} @endif</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-500">ISP</dt>
                    <dd>{{ $report->isp ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-slate-500">ASN</dt>
                    <dd class="font-mono text-xs">{{ $report->asn ?? '—' }}</dd>
                </div>
                @if($report->geo_parse_error)
                    <div class="sm:col-span-2 rounded bg-amber-50 border border-amber-200 px-3 py-2 text-amber-900">
                        <span class="text-xs font-medium text-amber-800">Парсинг GeoIP</span>
                        <p class="mt-1 text-sm">{{ $report->geo_parse_error }}</p>
                    </div>
                @endif
            </dl>
        </x-admin.card>

        <x-admin.card title="Полный текст отчёта">
            <textarea readonly rows="28" class="w-full font-mono text-xs text-slate-800 border border-slate-300 rounded-md p-2 bg-slate-50">{{ $report->raw_report }}</textarea>
        </x-admin.card>
    </div>
@endsection
