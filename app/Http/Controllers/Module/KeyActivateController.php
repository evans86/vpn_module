<?php

namespace App\Http\Controllers\Module;

use App\Models\KeyActivate\KeyActivate;
use App\Logging\DatabaseLogger;
use App\Http\Controllers\Controller;

class KeyActivateController extends Controller
{
    /** @var DatabaseLogger */
    private $logger;

    public function __construct(DatabaseLogger $logger)
    {
        $this->logger = $logger;
    }

    public function index()
    {
        try {
            $activate_keys = KeyActivate::orderBy('id', 'desc')->limit(1000)->paginate(10);

            $this->logger->info('Просмотр списка активированных ключей', [
                'source' => 'key_activate',
                'action' => 'view_list',
                'user_id' => auth()->id(),
                'total_keys' => $activate_keys->total(),
                'page' => $activate_keys->currentPage()
            ]);

            return view('module.key-activate.index', compact('activate_keys'));
        } catch (\Exception $e) {
            $this->logger->error('Ошибка при загрузке списка активированных ключей', [
                'source' => 'key_activate',
                'action' => 'view_list',
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
}
