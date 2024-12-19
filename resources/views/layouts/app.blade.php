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
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@200;300;400;600;700;800&display=swap" rel="stylesheet">

    <!-- Favicon -->
    <link rel="icon" href="{{ \App\Helpers\AssetHelper::url('img/favicon.ico') }}">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="{{ \App\Helpers\AssetHelper::url('vendor/fontawesome/css/all.min.css') }}">

    <!-- Required CSS -->
    <link rel="stylesheet" href="{{ \App\Helpers\AssetHelper::asset('mota/centre/bootstrap-select/dist/css/bootstrap-select.min.css') }}">
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
        @include('layouts.navs.leftbar')

        <div class="content-body">
            <div class="container-fluid">
                @yield('content')
            </div>
        </div>

        @include('layouts.navs.footer')
    </div>

    <!-- Required Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/metismenu/dist/metisMenu.min.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/perfect-scrollbar@1.5.5/dist/perfect-scrollbar.min.js" crossorigin="anonymous"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js" crossorigin="anonymous"></script>

    <script src="{{ \App\Helpers\AssetHelper::asset('mota/centre/global/global.min.js') }}"></script>
    <script src="{{ \App\Helpers\AssetHelper::asset('mota/centre/bootstrap-select/dist/js/bootstrap-select.min.js') }}"></script>
    <script src="{{ \App\Helpers\AssetHelper::asset('mota/js/custom.min.js') }}"></script>
    <script src="{{ \App\Helpers\AssetHelper::asset('mota/js/deznav-init.js') }}"></script>

    <!-- Charts and Graphs -->
    <script src="{{ \App\Helpers\AssetHelper::asset('mota/centre/chart.js/Chart.bundle.min.js') }}"></script>
    <script src="{{ \App\Helpers\AssetHelper::asset('mota/centre/apexchart/apexchart.js') }}"></script>
    <script src="{{ \App\Helpers\AssetHelper::asset('mota/centre/peity/jquery.peity.min.js') }}"></script>
    <script src="{{ \App\Helpers\AssetHelper::asset('mota/centre/chartist/js/chartist.min.js') }}"></script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js" crossorigin="anonymous"></script>

    <!-- Initialize AOS -->
    <script>
        AOS.init();
    </script>

    @stack('js')
    @stack('scripts')
</body>
</html>
