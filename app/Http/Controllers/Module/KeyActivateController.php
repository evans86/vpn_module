<?php

namespace App\Http\Controllers\Module;

use App\Models\KeyActivate\KeyActivate;

class KeyActivateController
{
    public function index()
    {
        $activate_keys = KeyActivate::orderBy('id', 'desc')->limit(1000)->Paginate(10);

        return view('module.key-activate.index', compact('activate_keys'));
    }
}
