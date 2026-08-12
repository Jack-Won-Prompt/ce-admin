<?php
// app/Http/Controllers/SecureFileController.php
//
// 처방전 이미지와 첨부 서류를 로그인·권한 확인을 거쳐 내보낸다.
// 예전에는 /storage/prescriptions/... 로 바로 열려, 주소만 알면 로그인 없이도
// 환자의 처방전과 신분증이 보였다. 그 길은 public/.htaccess 에서 막았다.

namespace App\Http\Controllers;

use App\Models\Prescription;
use App\Models\PrescriptionAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SecureFileController extends Controller
{
    /** 처방전 원본 이미지 */
    public function prescriptionImage(Request $request, Prescription $prescription): StreamedResponse
    {
        abort_unless($prescription->image_path, 404);

        return $this->stream($prescription->image_path, $prescription->image_original_name);
    }

    /** 처방전에 딸린 첨부 서류 (신분증·위임장 등) */
    public function attachment(Request $request, PrescriptionAttachment $attachment): StreamedResponse
    {
        abort_unless($attachment->file_path, 404);

        return $this->stream($attachment->file_path, $attachment->file_original_name);
    }

    /**
     * 업로드 직후, 아직 처방전에 붙지 않은 임시 파일의 미리보기.
     *
     * 파일 이름이 tmp_{올린사람ID}_... 형태라 그 값으로 소유자를 가린다.
     * 남의 임시 파일 이름을 알아내도 열 수 없다.
     */
    public function tempImage(Request $request, string $name): StreamedResponse
    {
        abort_unless(preg_match('/^tmp_(\d+)_[0-9_a-z]+\.[a-z0-9]+$/i', $name, $m), 404);
        abort_unless((int) $m[1] === (int) $request->user()->id, 403);

        return $this->stream('prescriptions/temp/' . $name);
    }

    /**
     * 파일을 스트리밍한다.
     *
     * 다운로드가 아니라 화면 안에서 보이도록 inline 으로 내보낸다 —
     * 검수 화면이 이미지를 <img> 로 띄우기 때문이다.
     *
     * 캐시는 private 로 둔다. 공용 프록시에 남으면 로그인 검사를 우회해
     * 다른 사람에게 그대로 전달될 수 있다.
     */
    /**
     * 보호자 신분증.
     *
     * 신분증은 그 자체가 고유식별정보를 담아 공개 디스크에 두지 않는다. 기본 디스크
     * (storage/app)에 있어 주소로는 아예 닿지 않고, 이 경로로만 로그인·권한을 거쳐 나간다.
     */
    public function consentGuardianId(Request $request, \App\Models\PrescriptionConsent $consent): StreamedResponse
    {
        abort_unless($consent->guardian_id_path, 404);

        $disk = Storage::disk(config('filesystems.default'));
        abort_unless($disk->exists($consent->guardian_id_path), 404);

        activity()->causedBy(auth()->user())->performedOn($consent)
            ->log('보호자 신분증 열람');

        return $disk->response($consent->guardian_id_path, basename($consent->guardian_id_path), [
            'Content-Disposition'    => 'inline; filename="guardian-id"',
            'Cache-Control'          => 'private, max-age=300, must-revalidate',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function stream(string $path, ?string $originalName = null): StreamedResponse
    {
        $disk = Storage::disk('public');
        abort_unless($disk->exists($path), 404);

        $name = $originalName ?: basename($path);

        return $disk->response($path, $name, [
            'Content-Disposition' => 'inline; filename="' . addslashes($name) . '"',
            'Cache-Control'       => 'private, max-age=600, must-revalidate',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
