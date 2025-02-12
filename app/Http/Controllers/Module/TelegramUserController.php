<?php

namespace App\Http\Controllers\Module;

use App\Http\Controllers\Controller;
use App\Models\TelegramUser\TelegramUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TelegramUserController extends Controller
{
    /**
     * Отображает список пользователей.
     */
    public function index(Request $request)
    {
        // Фильтрация и поиск
        $query = TelegramUser::query();

        if ($request->has('id')) {
            $query->where('id', $request->input('id'));
        }

        if ($request->has('telegram_id')) {
            $query->where('telegram_id', $request->input('telegram_id'));
        }

        $query->orderBy('created_at', 'desc');

        $users = $query->paginate(10);

        return view('module.telegram-users.index', [
            'users' => $users,
        ]);
    }

    /**
     * Переключает статус пользователя.
     */
    public function toggleStatus($id): JsonResponse
    {
        $user = TelegramUser::findOrFail($id);
        $user->status = !$user->status;
        $user->save();

        return response()->json([
            'success' => true,
            'status' => $user->status,
            'message' => 'Статус успешно изменен.',
        ]);
    }
}
