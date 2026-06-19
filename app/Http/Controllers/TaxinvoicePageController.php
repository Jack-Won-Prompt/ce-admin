<?php

namespace App\Http\Controllers;

class TaxinvoicePageController extends Controller
{
    public function index()
    {
        $corpNum = config('popbill.test.corp_num');

        return view('taxinvoice.index', compact('corpNum'));
    }
}
