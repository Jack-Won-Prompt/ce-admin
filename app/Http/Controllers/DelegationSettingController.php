<?php
// app/Http/Controllers/DelegationSettingController.php
// 요양비 위임장 설정 관리 (관리자)

namespace App\Http\Controllers;

use App\Models\DelegationSetting;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DelegationSettingController extends Controller
{
    public function edit(): View
    {
        return view('delegation-settings.edit', [
            'setting' => DelegationSetting::current(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'provider_name'    => 'nullable|string|max:150',
            'provider_biz_no'  => 'nullable|string|max:40',
            'provider_ceo'     => 'nullable|string|max:50',
            'provider_phone'   => 'nullable|string|max:40',
            'account_receiver' => 'nullable|string|max:100',
            'account_bank'     => 'nullable|string|max:50',
            'account_holder'   => 'nullable|string|max:100',
            'account_number'   => 'nullable|string|max:50',
            'period_years'     => 'required|integer|min:1|max:5',
            'sig_x'            => 'required|numeric|min:0|max:210',
            'sig_y'            => 'required|numeric|min:0|max:297',
            'sig_w'            => 'required|numeric|min:5|max:80',
        ]);

        $setting = DelegationSetting::current();
        $setting->update($data);

        activity()->causedBy(auth()->user())
            ->log('요양비 위임장 설정 변경');

        return redirect()->route('delegation-settings.edit')
            ->with('status', '위임장 설정이 저장되었습니다.');
    }
}
