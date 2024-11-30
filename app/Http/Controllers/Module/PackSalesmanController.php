<?php

namespace App\Http\Controllers\Module;

use App\Models\PackSalesman\PackSalesman;
use App\Logging\DatabaseLogger;
use App\Http\Controllers\Controller;

class PackSalesmanController extends Controller
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
            $pack_salesmans = PackSalesman::orderBy('id', 'desc')->limit(1000)->paginate(10);

            $this->logger->info('Просмотр списка связей пакет-продавец', [
                'source' => 'pack_salesman',
                'action' => 'view_list',
                'user_id' => auth()->id(),
                'total_relations' => $pack_salesmans->total(),
                'page' => $pack_salesmans->currentPage()
            ]);

            return view('module.pack-salesman.index', compact('pack_salesmans'));
        } catch (\Exception $e) {
            $this->logger->error('Ошибка при загрузке списка связей пакет-продавец', [
                'source' => 'pack_salesman',
                'action' => 'view_list',
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
}
