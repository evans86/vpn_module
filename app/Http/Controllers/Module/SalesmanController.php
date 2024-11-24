<?php

namespace App\Http\Controllers\Module;

use App\Models\Salesman\Salesman;

class SalesmanController
{
    public function index()
    {
        $salesmans = Salesman::orderBy('id', 'desc')->limit(1000)->Paginate(10);

        return view('module.salesman.index', compact('salesmans'));
    }
}
