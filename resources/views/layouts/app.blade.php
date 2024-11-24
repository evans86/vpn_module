<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    {{--    <meta name="csrf-token" content="{{ csrf_token() }}">--}}

    <title>VPN</title>
    <!-- Favicon -->
    <link rel="icon" href="{{ url('img/favicon.ico') }}">
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css?family=Poppins:200,300,400,600,700,800" rel="stylesheet"/>
    <!-- CSS -->
    <link href="{{ asset('mota') }}/css/style.css" rel="stylesheet"/>
    <link href="{{ asset('mota') }}/centre/bootstrap-select/dist/css/bootstrap-select.min.css" rel="stylesheet">
    <link href="{{ asset('mota') }}/centre/jqvmap/css/jqvmap.min.css" rel="stylesheet">
    <link href="{{ asset('mota') }}/centre/chartist/css/chartist.min.css" rel="stylesheet">
    <link href="https://cdn.lineicons.com/2.0/LineIcons.css" rel="stylesheet">


</head>
<body class="{{ $class ?? '' }}">

<div id="preloader">
    <div class="sk-three-bounce">
        <div class="sk-child sk-bounce1"></div>
        <div class="sk-child sk-bounce2"></div>
        <div class="sk-child sk-bounce3"></div>
    </div>
</div>

<div class="wrapper">

    @include('layouts.navs.header')
    {{--    @include('layouts.navs.sidebar')--}}
    @include('layouts.navs.start')
    @include('layouts.navs.leftbar')

    <div class="content-body">
        @yield('content')
    </div>

    @include('layouts.navs.footer')

</div>
<!-- Required vendors -->
<script src="{{ asset('mota') }}/centre/global/global.min.js"></script>
<script src="{{ asset('mota') }}/centre/bootstrap-select/dist/js/bootstrap-select.min.js"></script>
<script src="{{ asset('mota') }}/centre/chart.js/Chart.bundle.min.js"></script>
<script src="{{ asset('mota') }}/js/custom.min.js"></script>
<script src="{{ asset('mota') }}/js/deznav-init.js"></script>
<!-- Apex Chart -->
<script src="{{ asset('mota') }}/centre/apexchart/apexchart.js"></script>
<!-- Chart piety plugin files -->
<script src="{{ asset('mota') }}/centre/peity/jquery.peity.min.js"></script>
<!-- Chartist -->
<script src="{{ asset('mota') }}/centre/chartist/js/chartist.min.js"></script>
<!-- Dashboard 1 -->
<script src="{{ asset('mota') }}/js/dashboard/dashboard-1.js"></script>
<!-- Svganimation scripts -->
<script src="{{ asset('mota') }}/centre/svganimation/vivus.min.js"></script>
<script src="{{ asset('mota') }}/centre/svganimation/svg.animation.js"></script>
</body>
</html>
