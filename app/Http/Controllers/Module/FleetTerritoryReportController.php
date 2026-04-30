<?php

namespace App\Http\Controllers\Module;

use App\Http\Controllers\Controller;
use App\Models\Server\FleetTerritoryReport;
use App\Services\Server\FleetTerritoryReportParserService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FleetTerritoryReportController extends Controller
{
    private FleetTerritoryReportParserService $parser;

    public function __construct(FleetTerritoryReportParserService $parser)
    {
        $this->parser = $parser;
    }

    public function store(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'submitter_note' => 'nullable|string|max:255',
            'raw_report' => 'nullable|string|max:4000000',
            'report_file' => 'nullable|file|max:8192',
        ]);

        $validator->after(function ($validator) use ($request) {
            $text = trim((string) $request->input('raw_report', ''));
            if ($text === '' && ! $request->hasFile('report_file')) {
                $validator->errors()->add('raw_report', 'Вставьте текст отчёта или приложите файл .txt.');
            }
        });

        $validator->validate();

        $raw = trim((string) $request->input('raw_report', ''));
        if ($request->hasFile('report_file')) {
            $uploadedRaw = file_get_contents($request->file('report_file')->getRealPath());
            if ($raw !== '') {
                $raw = trim($uploadedRaw) . "\n\n--- текст из формы ниже файла ---\n\n" . $raw;
            } else {
                $raw = $uploadedRaw;
            }
        }

        $parsed = $this->parser->parse($raw);

        $record = FleetTerritoryReport::query()->create(array_merge([
            'user_id' => auth()->id(),
            'submitter_note' => $request->input('submitter_note'),
            'raw_report' => $raw,
        ], $parsed));

        $extra = ($record->country_name || $record->country_code || $record->city)
            ? ' — территория: ' . $record->territoryLabel()
            : '';

        return redirect()
            ->to(route('admin.module.server-fleet.report') . '#external-probes')
            ->with('success', 'Отчёт сохранён (№' . $record->id . $extra . '), таблица обновится на этой же странице.');
    }

    public function show(FleetTerritoryReport $fleetTerritoryReport): View
    {
        return view('module.server.fleet-territory-report-show', [
            'report' => $fleetTerritoryReport,
            'title' => 'Проба №' . $fleetTerritoryReport->id,
            'pageTitle' => 'Отчёт внешней пробы',
        ]);
    }
}
