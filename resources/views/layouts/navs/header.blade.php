<!--*******************
    Preloader start
********************-->
<div class="nav-header">
    <a href="{{ route('module.server.index') }}" class="logo-abbr">
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
                        <li class="nav-item dropdown header-profile">
                            <a class="nav-link" href="#" role="button" data-toggle="dropdown">
                                <i class="mdi mdi-account"></i>
                                <span class="ml-2">{{ Auth::user()->name }}</span>
                            </a>
                            <div class="dropdown-menu dropdown-menu-right">
                                <form id="logout-form" action="{{ secure_url('logout') }}" method="POST" style="display: none;">
                                    @csrf
                                </form>
                                <button type="submit" class="dropdown-item ai-icon" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                                    <svg id="icon-logout" xmlns="http://www.w3.org/2000/svg" width="18" height="18"
                                         viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                         stroke-linecap="round" stroke-linejoin="round"
                                         class="feather feather-log-out">
                                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                                        <polyline points="16 17 21 12 16 7"></polyline>
                                        <line x1="21" y1="12" x2="9" y2="12"></line>
                                    </svg>
                                    <span class="ml-2">Выход</span>
                                </button>
                            </div>
                        </li>
                    @endauth
                </ul>
            </div>
        </nav>
    </div>
</div>
