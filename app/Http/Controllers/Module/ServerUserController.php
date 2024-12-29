<?php

namespace App\Http\Controllers\Module;

use App\Http\Controllers\Controller;
use App\Models\ServerUser\ServerUser;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ServerUserController extends Controller
{
    /**
     * Display a listing of the server users.
     */
    public function index(): View
    {
        $serverUsers = ServerUser::with(['keyActivateUser.keyActivate', 'server.panel'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('module.server-users.index', compact('serverUsers'));
    }

    /**
     * Show the specified server user.
     */
    public function show(ServerUser $serverUser): View
    {
        $serverUser->load(['keyActivateUser.keyActivate', 'server.panel']);

        return view('module.server-users.show', compact('serverUser'));
    }
}
