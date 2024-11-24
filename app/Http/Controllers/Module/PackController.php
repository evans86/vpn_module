<?php

namespace App\Http\Controllers\Module;

use App\Models\Pack\Pack;

class PackController
{
    public function index()
    {
        $packs = Pack::orderBy('id', 'desc')->limit(1000)->Paginate(10);

        return view('module.pack.index', compact('packs'));
    }
}
