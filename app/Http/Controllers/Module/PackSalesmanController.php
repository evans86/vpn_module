<?php

namespace App\Http\Controllers\Module;

use App\Models\PackSalesman\PackSalesman;

class PackSalesmanController
{
    public function index()
    {
        $pack_salesmans = PackSalesman::orderBy('id', 'desc')->limit(1000)->Paginate(10);

        return view('module.pack-salesman.index', compact('pack_salesmans'));
    }
}
