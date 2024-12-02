<?php

namespace App\Http\Controllers\Module;

use App\Services\Telegram\ModuleBot\FatherBotController;
use Exception;
use Illuminate\Http\Request;
use App\Logging\DatabaseLogger;
use App\Http\Controllers\Controller;

class BotController extends Controller
{
    /**
     * @var DatabaseLogger
     */
    private DatabaseLogger $logger;

    public function __construct(DatabaseLogger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @throws Exception
     */
    public function index()
    {
        try {
            $this->logger->info('Доступ к странице управления ботом', [
                'source' => 'bot',
                'action' => 'view',
                'user_id' => auth()->id()
            ]);
            return view('module.bot.index');
        } catch (Exception $e) {
            $this->logger->error('Ошибка при доступе к странице управления ботом', [
                'source' => 'bot',
                'action' => 'view',
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * @throws Exception
     */
    public function update(Request $request)
    {
        try {
            $botFatherService = new FatherBotController();
            $botFatherService->init();

            $this->logger->info('Сервис бота успешно инициализирован', [
                'source' => 'bot',
                'action' => 'update',
                'user_id' => auth()->id()
            ]);

            return view('module.bot.index');
        } catch (Exception $e) {
            $this->logger->error('Ошибка при инициализации сервиса бота', [
                'source' => 'bot',
                'action' => 'update',
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
}
