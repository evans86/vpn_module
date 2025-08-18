<?php

namespace App\Http\Controllers\Module;

use App\Http\Controllers\Controller;
use App\Models\ServerUser\ServerUser;
use App\Models\Panel\Panel;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ServerUserController extends Controller
{
    /**
     * Display a listing of the server users.
     *
     * @param Request $request
     * @return View
     */
    public function index(Request $request): View
    {
        $query = ServerUser::query()
            ->select('server_user.*', 'key_activate.user_tg_id as telegram_id')
            ->leftJoin('key_activate_user', 'server_user.id', '=', 'key_activate_user.server_user_id')
            ->leftJoin('key_activate', 'key_activate_user.key_activate_id', '=', 'key_activate.id')
            ->with(['panel.server']);

        // Фильтрация по ID
        if ($request->filled('id')) {
            $query->where('server_user.id', $request->input('id'));
        }

        // Фильтрация по panel_id
        if ($request->filled('panel_id')) {
            $query->where('server_user.panel_id', $request->input('panel_id'));
        }

        // Фильтрация по server_id
        if ($request->filled('server_id')) {
            $query->whereHas('panel', function ($q) use ($request) {
                $q->where('server_id', $request->input('server_id'));
            });
        }

        // Фильтрация по имени или IP сервера
        if ($request->filled('server')) {
            $search = $request->input('server');
            $query->whereHas('panel.server', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('ip', 'like', "%{$search}%");
            });
        }

        // Фильтрация по адресу панели
        if ($request->filled('panel')) {
            $search = $request->input('panel');
            $query->whereHas('panel', function ($q) use ($search) {
                $q->where('panel_adress', 'like', "%{$search}%");
            });
        }

        $serverUsers = $query->orderBy('server_user.created_at', 'desc')
            ->paginate(20);

        // Загружаем текущую панель, если указан panel_id
        $panel = $request->filled('panel_id') ? Panel::with('server')->find($request->input('panel_id')) : null;

        return view('module.server-users.index', compact('serverUsers', 'panel'));
    }

    /**
     * Show the specified server user.
     *
     * @param ServerUser $serverUser
     * @return View
     */
    public function show(ServerUser $serverUser): View
    {
        $serverUser->load(['keyActivateUser.keyActivate', 'panel.server']);

        return view('module.server-users.show', compact('serverUser'));
    }
}
