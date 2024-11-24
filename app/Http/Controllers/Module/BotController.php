<?php

namespace App\Http\Controllers\Module;

use App\Models\KeyActivate\KeyActivate;
use App\Services\Telegram\ModuleBot\FatherBotController;
use Illuminate\Http\Request;

class BotController
{
    public function index()
    {
        return view('module.bot.index');
    }

    public function update(Request $request)
    {
        $botFatherService = new FatherBotController();
        $botFatherService->init();

        return view('module.bot.index');
    }
}
