<?php

namespace App\Http\Controllers;

class CashbillPageController extends Controller
{
    public function index()
    {
        $corpNum = config('popbill.test.corp_num');

        return view('cashbill.index', compact('corpNum'));
    }
}
