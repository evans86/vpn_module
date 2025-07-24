<?php

namespace App\Http\Controllers\Module;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class PersonalController
{
//    public function dashboard()
//    {
//        $salesman = Auth::guard('salesman')->user();
//        return view('module.personal.dashboard', compact('salesman'));
//    }
//
//    public function orders()
//    {
//        $salesman = Auth::guard('salesman')->user();
//        return view('module.personal.orders', compact('salesman'));
//    }
//
//    public function stats()
//    {
//        $salesman = Auth::guard('salesman')->user();
//        return view('module.personal.stats', compact('salesman'));
//    }

    public function dashboard()
    {
        $salesman = Auth::guard('salesman')->user();
        return view('module.personal.soon', compact('salesman'));
    }

    public function orders()
    {
        return view('module.personal.soon');
    }

    public function stats()
    {
        return view('module.personal.soon');
    }
}
