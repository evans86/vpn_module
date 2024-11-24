@extends('layouts.app', ['page' => __('Сервера'), 'pageSlug' => 'servers'])

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Список серверов</h4>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-responsive-md">
                                <thead>
                                <tr>
                                    <th style="width:80px;"><strong>#</strong></th>
                                    <th><strong>IP</strong></th>
                                    <th><strong>LOGIN</strong></th>
                                    <th><strong>PASSWORD</strong></th>
                                    <th><strong>HOST</strong></th>
                                    <th><strong>STATUS</strong></th>
                                    <th></th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($servers as $server)
                                    <tr>
                                        <td><strong>{{ $server->id }}</strong></td>
                                        <td>{{ $server->ip }}</td>
                                        <td>{{ $server->login }}</td>
                                        <td>{{ $server->password }}</td>
                                        <td>{{ $server->host }}</td>
                                        <td><span class="badge light badge-success">{{ $server->server_status }}</span></td>
                                        <td>
                                            <div class="dropdown">
                                                <button type="button" class="btn btn-success light sharp"
                                                        data-toggle="dropdown">
                                                    <svg width="20px" height="20px" viewBox="0 0 24 24" version="1.1">
                                                        <g stroke="none" stroke-width="1" fill="none"
                                                           fill-rule="evenodd">
                                                            <rect x="0" y="0" width="24" height="24"/>
                                                            <circle fill="#000000" cx="5" cy="12" r="2"/>
                                                            <circle fill="#000000" cx="12" cy="12" r="2"/>
                                                            <circle fill="#000000" cx="19" cy="12" r="2"/>
                                                        </g>
                                                    </svg>
                                                </button>
                                                <div class="dropdown-menu">
                                                    <a class="dropdown-item" href="#">Statistics</a>
                                                    <a class="dropdown-item" href="#">Delete</a>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="d-grid gap-2 d-md-block mb-2">
                            <a href="{{ route('module.server.create') }}" class="btn btn-success">Создать сервер</a>
                        </div>
{{--                        <div class="d-flex">--}}
{{--                            {!! $servers->links() !!}--}}
{{--                        </div>--}}
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection
