<?php
// app/Http/Controllers/Api/PrescriptionApiController.php

namespace App\Http\Controllers\Api;

use App\Events\PrescriptionUploaded;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessPrescriptionOcr;
use App\Models\Prescription;
use App\Services\OcrService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class PrescriptionApiController extends Controller
{
    public function __construct(private readonly OcrService $ocrService) {}

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
                'status'              => 'ocr_processing',
                'created_by'          => auth()->id(),
                'admin_note'          => $request->filled('memo') ? $request->input('memo') : null,
            ]);

            // OCR 처리 — 동기 처리 (간단한 경우) 또는 큐 사용
            try {
                $ocrResult = $this->ocrService->extractFromImage($path);
                $ocrData   = $ocrResult['data'];

                $prescription->update([
                    'status'           => $ocrResult['confidence'] >= 85 ? 'ocr_done' : 'review_needed',
                    'ocr_confidence'   => $ocrResult['confidence'],
                    'ocr_raw_data'     => ['raw_text' => $ocrResult['raw_text']],
                    'registration_no'  => $ocrData['registration_no'] ?? null,
                    'serial_no'        => $ocrData['serial_no'] ?? null,
                    'is_reissue'       => $ocrData['is_reissue'] ?? false,
                    'patient_name_ocr' => $ocrData['patient_name'] ?? null,
                    'resident_no_ocr'  => $ocrData['resident_no'] ?? null,
                    'mobile_ocr'       => $ocrData['mobile'] ?? $ocrData['phone'] ?? null,
                    'address_ocr'      => $ocrData['address'] ?? null,
                    'hospital_name'    => $ocrData['hospital_name'] ?? null,
                    'hospital_code'    => $ocrData['hospital_code'] ?? null,
                    'doctor_name'      => $ocrData['doctor_name'] ?? null,
                    'specialty'        => $ocrData['specialty'] ?? null,
                    'license_no'       => $ocrData['license_no'] ?? null,
                    'specialist_no'    => $ocrData['specialist_no'] ?? null,
                    'department'       => $ocrData['department'] ?? null,
                    'disease_name'     => $ocrData['disease_name'] ?? null,
                    'disease_code'     => $ocrData['disease_code'] ?? null,
                    'daily_count'      => $ocrData['daily_count'] ?? null,
                    'total_days'       => $ocrData['total_days'] ?? null,
                    'total_count'      => $ocrData['total_count'] ?? null,
                    'usage_period'     => $ocrData['usage_period'] ?? null,
                    'issued_date'      => $ocrData['issued_date'] ?? null,
                ]);

                $prescription->refresh();
            } catch (\Exception $e) {
                // OCR 실패해도 업로드는 성공 처리, 수동 검수로 전환
                $prescription->update(['status' => 'review_needed']);
            }

            // 상담번호 자동 채번
            $prescription->update([
                'counseling_data' => array_merge(
                    $prescription->counseling_data ?? [],
                    ['counselling_no' => \App\Models\Prescription::generateCounselNo(), 'counsel_date' => now()->format('Y-m-d')]
                ),
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
                'ocr_confidence' => $p->ocr_confidence,
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
            'ocr_confidence'  => $p->ocr_confidence,
            'ocr_result'      => [
                'registration_no'    => $p->registration_no,
                'serial_no'          => $p->serial_no,
                'is_reissue'         => $p->is_reissue,
                'patient_name'       => $p->patient_name_ocr,
                'resident_no'        => $p->resident_no_ocr
                    ? substr($p->resident_no_ocr, 0, 7) . '******'
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
