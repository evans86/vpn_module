@extends('layouts.app', ['page' => __('Bot Father'), 'pageSlug' => 'packs'])

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-xl-12 col-lg-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Обновление токена бота</h4>
                    </div>
                    <div class="card-body">
                        <div class="basic-form">
                            <form method="post" action="{{ route('module.bot.update') }}" accept-charset="UTF-8">
                                @csrf
                                <div class="input-group mb-3">
                                    <div class="input-group-prepend">
                                        <button class="btn btn-primary" type="submit">Обновить</button>
                                    </div>
                                    <input type="text" name="bot_token" class="form-control">
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
