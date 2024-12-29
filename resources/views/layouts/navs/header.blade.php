<!--*******************
    Preloader start
********************-->
<div class="nav-header">
    <a href="{{ route('admin.module.server.index') }}" class="brand-logo">
        <h4 class="logo-text">VPN Admin</h4>
    </a>
</div>

<div class="header">
    <div class="header-content">
        <nav class="navbar navbar-expand">
            <div class="collapse navbar-collapse justify-content-between">
                <div class="header-left">
                    <div class="dashboard_bar">
                        Панель управления
                    </div>
                </div>
                <ul class="navbar-nav header-right">
                    @auth
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle user-dropdown" href="javascript:void(0);"
                               data-toggle="dropdown">
                                <span class="text-black">{{ Auth::user()->name }}</span>
                            </a>
                            <div class="dropdown-menu dropdown-menu-right">
                                <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                                    @csrf
                                </form>
                                <a class="dropdown-item" href="javascript:void(0);"
                                   onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                                    <i class="fas fa-sign-out-alt mr-2 text-danger"></i>
                                    <span>Выход</span>
                                </a>
                            </div>
                        </li>
                    @endauth
                </ul>
            </div>
        </nav>
    </div>
</div>

<style>
    .user-dropdown:after {
        content: "";
        margin-left: 0.5em;
        display: inline-block;
        width: 0;
        height: 0;
        border-top: 0.3em solid;
        border-right: 0.3em solid transparent;
        border-bottom: 0;
        border-left: 0.3em solid transparent;
        vertical-align: middle;
    }

    .dropdown-menu {
        min-width: 12rem;
        padding: 0.5rem 0;
        margin: 0.125rem 0 0;
        font-size: 1rem;
        color: #212529;
        background-color: #fff;
        border: 1px solid rgba(0, 0, 0, .15);
        border-radius: 0.25rem;
    }

    .dropdown-item {
        display: flex;
        align-items: center;
        padding: 0.5rem 1.5rem;
        clear: both;
        font-weight: 400;
        color: #212529;
        text-align: inherit;
        white-space: nowrap;
        background-color: transparent;
        border: 0;
    }

    .dropdown-item:hover {
        color: #16181b;
        text-decoration: none;
        background-color: #f8f9fa;
    }
</style>

@push('js')
    <script>
        $(document).ready(function () {
            $('.dropdown-toggle').dropdown();
        });
    </script>
@endpush
