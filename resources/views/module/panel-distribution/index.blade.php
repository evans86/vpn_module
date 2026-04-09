@extends('layouts.admin')

@section('title', 'Панели и распределение')
@section('page-title', 'Панели и распределение')

@section('content')
    <div class="space-y-6">
        @if(session('success'))
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                <span class="block sm:inline">{{ session('success') }}</span>
            </div>
        @endif
        @if(session('error'))
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                <span class="block sm:inline">{{ session('error') }}</span>
            </div>
        @endif

        @include('module.panel-distribution.partials.unified-distribution', [
            'distributionTiers' => $distributionTiers,
            'comparison' => $comparison,
            'panelDistributionPageCacheTtl' => $panelDistributionPageCacheTtl ?? 600,
        ])

        @include('module.panel-distribution.partials.rotation-block')
    </div>
@endsection
