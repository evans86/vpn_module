@extends('layouts.app', ['page' => __('Ключи активации'), 'pageSlug' => 'activate_keys'])

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Ключи активации</h4>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-responsive-md">
                                <thead>
                                <tr>
                                    <th style="width:80px;"><strong>#</strong></th>
                                    <th><strong>TRAFFIC LIMIT</strong></th>
                                    <th><strong>PACK SALESMAN</strong></th>
                                    <th><strong>FINISH</strong></th>
                                    <th><strong>USER TG ID</strong></th>
                                    <th><strong>KEY DELETED</strong></th>
                                    <th><strong>STATUS</strong></th>
                                    <th></th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($activate_keys as $activate_key)
                                    <tr>
                                        <td><strong>{{ $activate_key->id }}</strong></td>
                                        <td>{{ $activate_key->traffic_limit }}</td>
                                        <td>{{ $activate_key->pack_salesman_id }}</td>
                                        <td>{{ $activate_key->finish_at }}</td>
                                        <td>{{ $activate_key->user_tg_id }}</td>
                                        <td>{{ $activate_key->deleted_at }}</td>
                                        <td><span class="badge light badge-success">{{ $activate_key->status }}</span></td>
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
{{--                        <div class="d-grid gap-2 d-md-block mb-2">--}}
{{--                            <a href="{{ route('module.panel.create') }}" class="btn btn-success">Создать панель</a>--}}
{{--                        </div>--}}
                        {{--                        <div class="d-flex">--}}
                        {{--                            {!! $panels->links() !!}--}}
                        {{--                        </div>--}}
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
