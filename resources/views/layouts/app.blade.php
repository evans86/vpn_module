<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>VPN</title>

    <!-- Preconnect to Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@200;300;400;600;700;800&display=swap"
          rel="stylesheet">

    <!-- Favicon -->
    <link rel="icon" href="{{ \App\Helpers\AssetHelper::url('img/favicon.ico') }}">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
          integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA=="
          crossorigin="anonymous" referrerpolicy="no-referrer"/>

    <!-- Required CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet"
          href="{{ \App\Helpers\AssetHelper::asset('mota/centre/bootstrap-select/dist/css/bootstrap-select.min.css') }}">
    <link rel="stylesheet" href="{{ \App\Helpers\AssetHelper::asset('mota/centre/jqvmap/css/jqvmap.min.css') }}">
    <link rel="stylesheet" href="{{ \App\Helpers\AssetHelper::asset('mota/centre/chartist/css/chartist.min.css') }}">
    <link rel="stylesheet" href="{{ \App\Helpers\AssetHelper::asset('mota/css/style.css') }}">
    <link rel="stylesheet" href="{{ \App\Helpers\AssetHelper::asset('mota/css/custom.css') }}">
    <link rel="stylesheet" href="{{ \App\Helpers\AssetHelper::asset('mota/css/custom-fixes.css') }}">

    <!-- Additional CSS -->
    <link rel="stylesheet" href="https://cdn.lineicons.com/2.0/LineIcons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">

    @stack('css')
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
    @if(Auth::check())
        @include('layouts.navs.leftbar')
    @endif

    <div class="content-body">
        <div class="container-fluid">
            @yield('content')
        </div>
    </div>

    @include('layouts.navs.footer')
</div>

<!-- Required Scripts -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
<script
    src="{{ \App\Helpers\AssetHelper::asset('mota/centre/bootstrap-select/dist/js/bootstrap-select.min.js') }}"></script>
<script src="{{ \App\Helpers\AssetHelper::asset('mota/centre/chartist/js/chartist.min.js') }}"></script>
<script
    src="{{ \App\Helpers\AssetHelper::asset('mota/centre/chartist-plugin-tooltips/js/chartist-plugin-tooltip.min.js') }}"></script>
<script src="{{ \App\Helpers\AssetHelper::asset('mota/centre/jqvmap/js/jquery.vmap.min.js') }}"></script>
<script src="{{ \App\Helpers\AssetHelper::asset('mota/centre/jqvmap/js/jquery.vmap.usa.js') }}"></script>
<script src="{{ \App\Helpers\AssetHelper::asset('mota/js/plugins-init/jqvmap-init.js') }}"></script>
<script src="{{ \App\Helpers\AssetHelper::asset('mota/js/custom.min.js') }}"></script>
<script src="{{ \App\Helpers\AssetHelper::asset('mota/js/deznav-init.js') }}"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.3/umd/popper.min.js"></script>

<!-- Charts and Graphs -->
<script src="{{ \App\Helpers\AssetHelper::asset('mota/centre/chart.js/Chart.bundle.min.js') }}"></script>
<script src="{{ \App\Helpers\AssetHelper::asset('mota/centre/apexchart/apexchart.js') }}"></script>
<script src="{{ \App\Helpers\AssetHelper::asset('mota/centre/peity/jquery.peity.min.js') }}"></script>

<!-- Initialize AOS -->
<script>
    AOS.init();
</script>

@stack('js')
@stack('scripts')
</body>
</html>
