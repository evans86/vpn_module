<?php

namespace App\Http\Controllers\Api\v1;

use App\Helpers\ApiHelpers;
use App\Http\Controllers\Controller;
use App\Models\Bot\BotModule;
use App\Models\KeyActivate\KeyActivate;
use App\Models\Pack\Pack;
use App\Models\PackSalesman\PackSalesman;
use App\Models\Salesman\Salesman;
use App\Services\Key\KeyActivateService;
use Exception;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class PackSalesmanController extends Controller
{
    private KeyActivateService $keyActivateService;

    /**
     * @param KeyActivateService $keyActivateService
     */
    public function __construct(KeyActivateService $keyActivateService)
    {
        $this->middleware('api');
        $this->keyActivateService = $keyActivateService;
    }
}
