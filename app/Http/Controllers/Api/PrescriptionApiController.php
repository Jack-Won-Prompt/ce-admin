<?php
// app/Http/Controllers/Api/PrescriptionApiController.php

namespace App\Http\Controllers\Api;

use App\Events\PrescriptionUploaded;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessPrescriptionOcr;
use App\Models\Prescription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class PrescriptionApiController extends Controller
{
    // ── POST /api/prescriptions/upload ───────────────────
    /**
     * 모바일 앱에서 처방전 이미지 업로드
     * Authorization: Bearer <token>
     */
    public function upload(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'prescription_image' => [
                'required', 'file',
                'mimes:jpg,jpeg,png,pdf,heic',
                'max:10240', // 10MB
            ],
            'memo' => ['nullable', 'string', 'max:500'],
        ], [
            'prescription_image.required' => '처방전 이미지를 첨부해주세요.',
            'prescription_image.mimes'    => 'JPG, PNG, PDF, HEIC 형식만 지원합니다.',
            'prescription_image.max'      => '파일 크기는 10MB 이하여야 합니다.',
            'memo.max'                    => '메모는 500자 이하로 입력해주세요.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $file = $request->file('prescription_image');

            // 파일 저장 (storage/app/public/prescriptions/YYYY/MM/)
            $subDir   = 'prescriptions/' . now()->format('Y/m');
            $fileName = now()->format('Ymd_His') . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $path     = $file->storeAs($subDir, $fileName, 'public');

            // 처방전 레코드 생성
            $prescription = Prescription::create([
                'rx_number'           => Prescription::generateRxNumber(),
                'image_path'          => $path,
                'image_original_name' => $file->getClientOriginalName(),
                'image_mime_type'     => $file->getMimeType(),
                'image_size'          => $file->getSize(),
                'upload_source'       => 'mobile',
                // OCR 은 쓰지 않는다 — 올리면 곧장 검수 필요로 두고 담당자가 손으로 적는다
                'status'              => 'review_needed',
                'created_by'          => auth()->id(),
                'admin_note'          => $request->filled('memo') ? $request->input('memo') : null,
            ]);

            /* OCR 은 걷었다 — 신뢰도로 「OCR 완료 / 검수 필요」를 가르던 흐름을 없애고,
               올린 것은 무엇이든 검수 필요로 둔다. 값은 담당자가 웹 검수 화면에서 적는다. */

            // 상담번호 자동 채번
            $prescription->update([
                'counsel_no'   => \App\Models\Prescription::generateCounselNo(),
                'counsel_date' => now()->format('Y-m-d'),
            ]);

            // 웹 관리자에게 실시간 알림
            try {
                broadcast(new PrescriptionUploaded(
                    $prescription,
                    auth()->user()->name
                ));
            } catch (\Throwable) {}

            return response()->json([
                'success'         => true,
                'message'         => '처방전이 성공적으로 업로드되었습니다.',
                'prescription_id' => $prescription->rx_number,
                'ocr_result'      => $this->formatOcrResult($prescription),
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '업로드 처리 중 오류가 발생했습니다.',
            ], 500);
        }
    }

    // ── GET /api/prescriptions ────────────────────────────
    /** 내 처방전 목록 (모바일 앱 — 로그인 사용자 본인 업로드만) */
    public function index(Request $request): JsonResponse
    {
        $query = Prescription::where('created_by', auth()->id())
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $prescriptions = $query->paginate(15);

        return response()->json([
            'success' => true,
            'data'    => $prescriptions->map(fn($p) => [
                'rx_number'      => $p->rx_number,
                'status'         => $p->status,
                'status_label'   => $p->status_label,
                'patient_name'   => $p->patient_name_ocr,
                'hospital'       => $p->hospital_name,
                'disease_name'   => $p->disease_name,
                'issued_date'    => $p->issued_date?->format('Y-m-d'),
                'image_url'      => $p->image_url,
                'created_at'     => $p->created_at->format('Y-m-d H:i'),
            ]),
            'meta' => [
                'current_page' => $prescriptions->currentPage(),
                'last_page'    => $prescriptions->lastPage(),
                'total'        => $prescriptions->total(),
                'per_page'     => $prescriptions->perPage(),
            ],
        ]);
    }

    // ── GET /api/prescriptions/{rx_number} ───────────────
    /** 처방전 상세 조회 */
    public function show(string $rxNumber): JsonResponse
    {
        $prescription = Prescription::where('rx_number', $rxNumber)->firstOrFail();

        return response()->json([
            'success' => true,
            'data'    => $this->formatOcrResult($prescription),
        ]);
    }

    // ── 내부: OCR 결과 포맷 ───────────────────────────────
    private function formatOcrResult(Prescription $p): array
    {
        return [
            'prescription_id' => $p->rx_number,
            'status'          => $p->status,
            'status_label'    => $p->status_label,
            'ocr_result'      => [
                'registration_no'    => $p->registration_no,
                'serial_no'          => $p->serial_no,
                'is_reissue'         => $p->is_reissue,
                'patient_name'       => $p->patient_name_ocr,
                'resident_no'        => $p->masked_resident_no_ocr
                    ? substr($p->masked_resident_no_ocr, 0, 7) . '******'
                    : null,
                'phone'              => $p->patient?->phone,
                'mobile'             => $p->patient?->mobile,
                'department'         => $p->department,
                'disease_name'       => $p->disease_name,
                'disease_code'       => $p->disease_code,
                'daily_count'        => $p->daily_count,
                'total_days'         => $p->total_days,
                'total_count'        => $p->total_count,
                'usage_period'       => $p->usage_period,
                'hospital_name'      => $p->hospital_name,
                'hospital_code'      => $p->hospital_code,
                'doctor_name'        => $p->doctor_name,
                'specialty'          => $p->specialty,
                'license_no'         => $p->license_no,
                'specialist_no'      => $p->specialist_no,
                'issued_date'        => $p->issued_date?->format('Y-m-d'),
            ],
        ];
    }
}
