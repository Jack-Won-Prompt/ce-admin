<?php
// app/Http/Controllers/OcrSettingController.php
// 처방전 OCR(AWS Textract) 연동 상태 확인 (관리자)
// 공급자가 Textract 단일이므로 선택 UI 없이 상태만 보여준다.

namespace App\Http\Controllers;

use App\Models\OcrSetting;
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

}
