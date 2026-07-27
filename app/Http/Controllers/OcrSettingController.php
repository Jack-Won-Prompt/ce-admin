<?php
// app/Http/Controllers/OcrSettingController.php
// 처방전 OCR 공급자 설정 관리 (관리자)

namespace App\Http\Controllers;

use App\Models\OcrSetting;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OcrSettingController extends Controller
{
    public function edit(): View
    {
        return view('ocr-settings.edit', [
            'setting'          => OcrSetting::current(),
            'textractEnabled'  => (bool) config('ocr.textract.enabled'),
            'textractRegion'   => config('ocr.textract.region'),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'provider' => 'required|in:'.implode(',', OcrSetting::PROVIDERS),
        ]);

        $setting = OcrSetting::current();
        $setting->update($data);

        activity()->causedBy(auth()->user())
            ->log('처방전 OCR 공급자 변경: '.$data['provider']);

        return redirect()->route('ocr-settings.edit')
            ->with('status', 'OCR 설정이 저장되었습니다.');
    }
}
