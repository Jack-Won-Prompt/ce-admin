<?php
// app/Http/Controllers/Api/PrescriptionApiController.php

namespace App\Http\Controllers\Api;

use App\Events\PrescriptionUploaded;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessPrescriptionOcr;
use App\Models\Prescription;
use App\Models\PrescriptionAttachment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PrescriptionApiController extends Controller
{
    /** 모바일 업로드 화면에서 고를 수 있는 서류 유형 — common_codes(doc_type) 코드값과 동일하게 맞춘다 */
    private const DOC_TYPES = ['registration_form', 'prescription', 'test_result', 'id_card', 'delegation'];

    // ── POST /api/prescriptions/upload ───────────────────
    /**
     * 모바일 앱에서 처방자료 업로드.
     * "처방전" 유형만 새 처방전 레코드를 만든다(웹 store()와 동일하게 OCR 없이 검수
     * 필요 상태로 시작) — 그 외(등록신청서·결과지·신분증·위임장)는 이 환자의 기존
     * 처방전에 첨부로 붙는다.
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
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'doc_type'   => ['required', 'string', Rule::in(self::DOC_TYPES)],
            'memo'       => ['nullable', 'string', 'max:500'],
        ], [
            'prescription_image.required' => '처방전 이미지를 첨부해주세요.',
            'prescription_image.mimes'    => 'JPG, PNG, PDF, HEIC 형식만 지원합니다.',
            'prescription_image.max'      => '파일 크기는 10MB 이하여야 합니다.',
            'patient_id.required'         => '환자를 먼저 선택해주세요.',
            'patient_id.exists'           => '존재하지 않는 환자입니다.',
            'doc_type.required'           => '서류 유형을 선택해주세요.',
            'doc_type.in'                 => '올바른 서류 유형이 아닙니다.',
            'memo.max'                    => '메모는 500자 이하로 입력해주세요.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        $docType   = $request->input('doc_type');
        $patientId = (int) $request->input('patient_id');
        $file      = $request->file('prescription_image');

        $memo = $request->filled('memo') ? $request->input('memo') : null;

        try {
            if ($docType === 'prescription') {
                return $this->uploadAsPrescription($file, $patientId, $memo);
            }

            return $this->uploadAsAttachment($file, $patientId, $docType, $memo);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '업로드 처리 중 오류가 발생했습니다.',
            ], 500);
        }
    }

    private function uploadAsPrescription($file, int $patientId, ?string $memo): JsonResponse
    {
        $path = $this->storeUploadedFile($file, 'prescriptions/' . now()->format('Y/m'));

        /* 이 환자 몫으로 이미 만들어진 자리(등록신청서·신분증 등 첨부만 먼저 올라오고
           아직 실제 처방전 이미지가 없는 레코드)가 있으면 그걸 채운다 — 무조건 새로
           만들면, "신분증 먼저 → 처방전 나중" 순서일 때 같은 환자에 레코드가 둘로
           쪼개져 나중에 공단 팩스를 보낼 때 먼저 올린 첨부가 빠진 채로 나간다. */
        $prescription = DB::transaction(function () use ($patientId) {
            return Prescription::where('patient_id', $patientId)
                ->whereNull('image_path')
                ->latest()
                ->lockForUpdate()
                ->first();
        });

        $attrs = [
            'image_path'          => $path,
            'image_original_name' => $file->getClientOriginalName(),
            'image_mime_type'     => $file->getMimeType(),
            'image_size'          => $file->getSize(),
            'upload_source'       => 'mobile',
            'status'              => 'review_needed',
            'admin_note'          => $memo,
        ];

        // OCR 은 쓰지 않는다(웹과 동일). 곧장 검수 필요로 두고 담당자가 웹 검수
        // 화면에서 손으로 적는다.
        if ($prescription) {
            $prescription->update($attrs);
        } else {
            $prescription = Prescription::create($attrs + [
                'rx_number'  => Prescription::generateRxNumber(),
                'patient_id' => $patientId,
                'created_by' => auth()->id(),
            ]);
        }

        // 상담번호 자동 채번 — 이미 첨부만 있던 자리를 재사용한 경우 이미 있을 수 있다
        if (!$prescription->counsel_no) {
            $prescription->update([
                'counsel_no'   => Prescription::generateCounselNo(),
                'counsel_date' => now()->format('Y-m-d'),
            ]);
        }

        /* 올라온 그 자리에서 주문 관리에도 선다(웹 업로드와 같다).
           제품도 금액도 아직 없는 빈 줄이지만, 담당자가 「손댈 차례가 된 것」을 찾는
           자리가 그 목록이다. */
        \App\Support\OrderSync::ensure($prescription->refresh());

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
    }

    /**
     * 처방전이 아닌 서류(등록신청서·결과지·신분증·위임장) — 이 환자의 가장 최근
     * 처방전에 첨부로 붙인다. 처방전이 하나도 없으면 빈 처방전 자리를 하나 만들어
     * 붙인다(웹의 '신규 등록' 빈 초안과 같은 개념 — Prescription::scopeBlankDraft 참고).
     * 메모는 그 빈 자리를 새로 만들 때만 담는다 — 이미 있는 처방전에 붙는 경우엔
     * 웹의 개별 첨부 업로드(storeAttachment)도 메모를 받지 않는 것과 같다.
     */
    private function uploadAsAttachment($file, int $patientId, string $docType, ?string $memo): JsonResponse
    {
        // find-or-create 사이 경합(같은 환자의 첫 서류 두 개를 동시에 올리는 경우)을
        // 줄이기 위해 조회부터 생성까지 하나의 트랜잭션 + 잠금으로 묶는다.
        $prescription = DB::transaction(function () use ($patientId, $memo) {
            $existing = Prescription::where('patient_id', $patientId)
                ->latest()
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $existing;
            }

            return Prescription::create([
                'rx_number'      => Prescription::generateRxNumber(),
                'patient_id'     => $patientId,
                'created_by'     => auth()->id(),
                'status'         => 'review_needed',
                'upload_source'  => 'mobile',
                'is_blank_draft' => true,
                'admin_note'     => $memo,
            ]);
        });

        $path = $this->storeUploadedFile($file, 'prescriptions/attachments/' . now()->format('Y/m'));

        $maxOrder = $prescription->attachments()->max('display_order') ?? -1;

        PrescriptionAttachment::create([
            'prescription_id'    => $prescription->id,
            'file_path'          => $path,
            'file_original_name' => $file->getClientOriginalName(),
            'file_mime_type'     => $file->getMimeType(),
            'file_size'          => $file->getSize(),
            'doc_type'           => $docType,
            'doc_label'          => PrescriptionAttachment::labelFor($docType),
            'ocr_raw_text'       => null,
            'ocr_confidence'     => 0,
            'display_order'      => $maxOrder + 1,
            'uploaded_by'        => auth()->id(),
        ]);

        // 새로 만든 처방전 자리일 때만 알린다 — 이미 있던 처방전이면 관리자가 이미
        // 알고 작업 중인 건이라 첨부 하나마다 또 알릴 필요는 없다.
        if ($prescription->wasRecentlyCreated) {
            try {
                broadcast(new PrescriptionUploaded($prescription, auth()->user()->name));
            } catch (\Throwable) {}
        }

        return response()->json([
            'success'         => true,
            'message'         => PrescriptionAttachment::labelFor($docType) . ' 파일이 등록되었습니다.',
            'prescription_id' => $prescription->rx_number,
        ], 201);
    }

    /** 처방전/첨부 업로드 공통 — storage/app/public/{$subDir}/에 저장하고 상대 경로를 반환 */
    private function storeUploadedFile($file, string $subDir): string
    {
        $fileName = now()->format('Ymd_His') . '_' . uniqid() . '.' . $file->getClientOriginalExtension();

        return $file->storeAs($subDir, $fileName, 'public');
    }

    // ── GET /api/prescriptions ────────────────────────────
    /** 내 처방전 목록 (모바일 앱 — 로그인 사용자 본인 업로드만) */
    public function index(Request $request): JsonResponse
    {
        $query = Prescription::where('created_by', auth()->id())
            ->with('patient')
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // 환자 이름 검색 — 연결된 환자명 또는 OCR로 읽힌 이름(옛 데이터) 둘 다 본다
        if ($request->filled('name')) {
            $name = $request->input('name');
            $query->where(function ($q) use ($name) {
                $q->where('patient_name_ocr', 'like', "%{$name}%")
                  ->orWhereHas('patient', fn ($p) => $p->where('name', 'like', "%{$name}%"));
            });
        }

        // 업로드 날짜 범위 — 웹 처방전 목록(PrescriptionController::index)과 동일하게
        // date_from ~ date_to 로 본다. 모바일은 기본 기간을 강제하지 않는다(둘 다
        // 없으면 전체) — 웹은 목록이 전체 환자 대상이라 기본 최근 7일을 깔지만,
        // 모바일은 이미 "내가 올린 것"만 보여 굳이 좁힐 필요가 없다.
        if ($request->filled('date_from') || $request->filled('date_to')) {
            $dateFrom = $request->input('date_from') ?: '1970-01-01';
            $dateTo   = $request->input('date_to')   ?: now()->format('Y-m-d');
            $query->whereBetween(DB::raw('DATE(created_at)'), [$dateFrom, $dateTo]);
        }

        $prescriptions = $query->paginate(15);

        return response()->json([
            'success' => true,
            'data'    => $prescriptions->map(fn($p) => [
                'rx_number'      => $p->rx_number,
                'status'         => $p->status,
                'status_label'   => $p->status_label,
                'patient_name'   => $p->patient?->name ?? $p->patient_name_ocr,
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
