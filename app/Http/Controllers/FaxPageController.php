<?php

namespace App\Http\Controllers;

class FaxPageController extends Controller
{
    public function index()
    {
        $corpNum     = config('popbill.test.corp_num');
        $senderNum   = config('popbill.test.sender_num');
        $receiverFax = config('popbill.test.receiver_fax');

        return view('fax.index', compact('corpNum', 'senderNum', 'receiverFax'));
    }
}
