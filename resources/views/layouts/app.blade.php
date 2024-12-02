<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>VPN</title>
    <!-- Favicon -->
    <link rel="icon" href="{{ url('img/favicon.ico') }}">

    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css?family=Poppins:200,300,400,600,700,800" rel="stylesheet"/>

    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet"/>

    <!-- Required CSS -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet"/>
    <link href="https://cdn.jsdelivr.net/npm/perfect-scrollbar@1.5.5/css/perfect-scrollbar.css" rel="stylesheet"/>
    <link href="https://cdn.jsdelivr.net/npm/metismenu/dist/metisMenu.min.css" rel="stylesheet"/>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" rel="stylesheet"/>

    <!-- Template CSS -->
    <link href="{{ asset('mota/css/style.css') }}" rel="stylesheet"/>
    <link href="{{ asset('mota/css/custom.css') }}" rel="stylesheet"/>
    <link href="{{ asset('mota/css/custom-fixes.css') }}" rel="stylesheet"/>

    <!-- Additional Plugins -->
    <link href="{{ asset('mota/centre/bootstrap-select/dist/css/bootstrap-select.min.css') }}" rel="stylesheet"/>
    <link href="{{ asset('mota/centre/jqvmap/css/jqvmap.min.css') }}" rel="stylesheet"/>
    <link href="{{ asset('mota/centre/chartist/css/chartist.min.css') }}" rel="stylesheet"/>
    <link href="https://cdn.lineicons.com/2.0/LineIcons.css" rel="stylesheet"/>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" rel="stylesheet"/>

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

<!-- Required vendors -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/metismenu/dist/metisMenu.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/perfect-scrollbar@1.5.5/dist/perfect-scrollbar.min.js"></script>
<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>

<!-- Template and Custom JavaScript -->
<script src="{{ asset('mota/js/custom.min.js') }}"></script>
<script src="{{ asset('mota/js/deznav-init.js') }}"></script>

<!-- Additional Plugins -->
<script src="{{ asset('mota/centre/bootstrap-select/dist/js/bootstrap-select.min.js') }}"></script>
<script src="{{ asset('mota/centre/chart.js/Chart.bundle.min.js') }}"></script>
<script src="{{ asset('mota/centre/apexchart/apexchart.js') }}"></script>
<script src="{{ asset('mota/centre/peity/jquery.peity.min.js') }}"></script>
<script src="{{ asset('mota/centre/chartist/js/chartist.min.js') }}"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

<!-- Initialize AOS -->
<script>
    AOS.init();
</script>

@stack('js')
@stack('scripts')
</body>
</html>
