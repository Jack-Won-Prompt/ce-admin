<?php
// app/Http/Controllers/Api/PrescriptionApiController.php

namespace App\Http\Controllers\Api;

use App\Events\PrescriptionUploaded;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessPrescriptionOcr;
use App\Models\Prescription;
use App\Models\PrescriptionAttachment;
use App\Support\UploadDocTypes;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PrescriptionApiController extends Controller
{
    /** 모바일 업로드 화면에서 고를 수 있는 서류 유형 — common_codes(doc_type) 코드값과 동일하게 맞춘다 */
    // ── GET /api/prescriptions/doc-types ───────────────────
    /**
     * 업로드에서 고를 수 있는 서류 유형. 웹 업로드 화면과 같은 목록이다(UploadDocTypes).
     * 앱이 목록을 코드에 박아 두지 않고 여기서 받아 간다 — 환경 설정에서 유형을
     * 바꾸면 앱도 따라간다.
     */
    public function docTypes(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => UploadDocTypes::list(),
        ]);
    }

    // ── POST /api/prescriptions/upload ───────────────────
    /**
     * 모바일 앱에서 처방자료 업로드 (2026-09-15 지시로 바꿈).
     *
     * 번호(rx_number)가 없으면 늘 새 처방전 번호로 만든다 — 업로드 화면의 한 번 누름이
     * 한 건이다. 번호가 있으면 그 건에 붙인다(같은 업로드의 둘째 장부터, 상세 화면의
     * 서류 추가). 기존 건을 찾아 붙이던 예전 방식은 걷었다 — createWith() 참고.
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
            /* 어느 건에 붙일지. 비우면 새 처방전 번호로 만든다(2026-09-15 지시).
               업로드 화면은 누를 때마다 새 건이고, 그 건의 둘째 장부터는 첫 장이
               받은 번호를 싣는다. 상세 화면에서 서류를 더할 때도 이 번호를 싣는다. */
            'rx_number'  => ['nullable', 'string', 'max:30'],
            // 새 건을 연다는 표시 — 1.3.2 부터 첫 장에 싣는다. 있든 없든 번호가 없으면 새 건이다
            'new_batch'  => ['nullable', 'boolean'],
            'patient_id' => ['required_without:rx_number', 'nullable', 'integer', 'exists:patients,id'],
            // 웹 업로드 화면과 같은 목록만 받는다 — 위임장은 거기서 빠진다(UploadDocTypes)
            'doc_type'   => ['required', 'string', Rule::in(UploadDocTypes::codes())],
            'memo'       => ['nullable', 'string', 'max:500'],
        ], [
            'prescription_image.required'  => '처방전 이미지를 첨부해 주십시오.',
            'prescription_image.mimes'     => 'JPG, PNG, PDF, HEIC 형식만 지원합니다.',
            'prescription_image.max'       => '파일 크기는 10MB 이하여야 합니다.',
            'patient_id.required_without'  => '환자를 먼저 선택해 주십시오.',
            'patient_id.exists'            => '존재하지 않는 환자입니다.',
            'doc_type.required'            => '서류 유형을 선택해 주십시오.',
            'doc_type.in'                  => '올바른 서류 유형이 아닙니다.',
            'memo.max'                     => '메모는 500자 이하로 입력해 주십시오.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        $docType = $request->input('doc_type');
        $file    = $request->file('prescription_image');
        $memo    = $request->filled('memo') ? $request->input('memo') : null;

        try {
            // ── 번호를 정해 온 것 — 그 건에 붙인다(같은 업로드의 둘째 장부터, 상세 화면의 추가)
            if ($request->filled('rx_number')) {
                $target = Prescription::where('rx_number', $request->input('rx_number'))->first();

                if (! $target) {
                    return response()->json([
                        'success' => false,
                        'message' => '처방전을 찾을 수 없습니다.',
                    ], 404);
                }

                /* 처방전 그림은 건을 만든 사람만 갈아 끼운다. 그 밖의 서류는 검수를
                   마치기 전이면 **앱을 쓰는 사람 누구나** 보탤 수 있다 (2026-09-23 지시).

                   어제 한 사람이 처방전을 올리고 오늘 다른 사람이 등록신청서를 보태는
                   일이 실제로 잦다. 여태는 「본인이 업로드한 처방전만」이라 그 길이
                   막혀 있었다. 지우는 것은 열지 않는다 — 내가 올린 서류만 지운다
                   (destroyAttachment). 남의 자료가 말없이 사라지면 검수자가 무엇을
                   보고 승인했는지 알 수 없게 된다. */
                if ($docType === 'prescription') {
                    if (! $target->editableByUploader(auth()->id())) {
                        return $this->refuseEdit($target, $this->보탤수있나($target)
                            ? '처방전은 해당 처방전을 등록한 담당자만 변경할 수 있습니다. 그 외 서류는 추가할 수 있습니다.'
                            : null);
                    }

                    return $this->fillPrescriptionImage($target, $file);
                }

                if (! $this->보탤수있나($target)) {
                    return $this->refuseEdit($target);
                }

                return $this->attachTo($target, $file, $docType);
            }

            /* 번호가 없으면 늘 새 처방전 번호다 — 처방전 서류가 없어도 그렇다.
               기존 처방전에 붙이는 길은 두지 않는다(2026-09-15 지시: 「업로드 시 기존의
               처방전 번호에 업로드 하는 일은 절대 불가」).

               옛 판(1.3.1 이하)은 번호를 싣지 않고 한 장씩 보내므로 한 번에 올린 서류가
               장마다 새 번호로 갈린다. 예전에는 같은 사람이 10분 안에 만든 건에 모았는데,
               그것도 기존 건에 붙이는 일이라 걷었다. 옛 판은 최소 판(1.3.2)으로 막는다. */
            return $this->createWith($file, (int) $request->input('patient_id'), $docType, $memo);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '업로드 처리 중 오류가 발생했습니다.',
            ], 500);
        }
    }

    /**
     * 새 처방전 번호로 건을 만들고 첫 서류를 담는다 (2026-09-15 지시).
     *
     * 예전에는 같은 환자의 기존 건을 찾아 붙였다 — 처방전은 「그림이 빈 가장 최근
     * 건」을, 다른 서류는 「가장 최근 건」을 채웠다. 그래서 결과지만 올리면 몇 달 전
     * 건이나 이미 검수를 마친 건, 남이 만든 건에 붙었고, 올린 사람의 목록에는 보이지
     * 않았다. 그림이 빈 예전 건이 있으면 처방전과 뒤따른 서류가 두 건으로 갈리기도
     * 했다. 업로드 화면에서 올린 것은 늘 새 건이다. 기존 건에 더하는 것은 상세
     * 화면에서 번호를 정해 올린다.
     */
    private function createWith($file, int $patientId, string $docType, ?string $memo): JsonResponse
    {
        $isRx = $docType === 'prescription';

        $prescription = Prescription::create([
            'rx_number'      => Prescription::generateRxNumber(),
            'patient_id'     => $patientId,
            'created_by'     => auth()->id(),
            'status'         => 'review_needed',
            'upload_source'  => 'mobile',
            // 처방전 없이 서류만 먼저 온 건 — 서류가 붙으면 PrescriptionAttachment 가 푼다
            'is_blank_draft' => ! $isRx,
            'admin_note'     => $memo,
        ]);

        if ($isRx) {
            $this->storePrescriptionImage($prescription, $file);
        } else {
            $this->storeAttachment($prescription, $file, $docType);
        }

        // 웹 관리자에게 실시간 알림 — 새 건이 섰다
        try {
            broadcast(new PrescriptionUploaded($prescription, auth()->user()->name));
        } catch (\Throwable) {}

        return response()->json([
            'success'         => true,
            'message'         => $isRx
                ? '처방전이 성공적으로 업로드되었습니다.'
                : PrescriptionAttachment::labelFor($docType) . ' 파일이 등록되었습니다.',
            'prescription_id' => $prescription->rx_number,
            'ocr_result'      => $isRx ? $this->formatOcrResult($prescription) : null,
        ], 201);
    }

    /**
     * 정한 건에 처방전 그림을 넣는다. 이미 있으면 받지 않는다 — 처방전은 한 건에
     * 한 장이고, 바꾸려면 먼저 지운다(상세 화면의 「처방전 그림 지우기」).
     */
    private function fillPrescriptionImage(Prescription $prescription, $file): JsonResponse
    {
        if ($prescription->image_path) {
            return response()->json([
                'success' => false,
                'message' => '이 처방전에는 처방전 이미지가 이미 등록되어 있습니다. 먼저 삭제한 뒤 업로드해 주십시오.',
            ], 422);
        }

        $열린요청 = $this->openRequestIds($prescription);
        $this->storePrescriptionImage($prescription, $file);
        $this->tellReuploadArrived($prescription, $열린요청);

        return response()->json([
            'success'         => true,
            'message'         => '처방전을 업로드했습니다.',
            'prescription_id' => $prescription->rx_number,
            'ocr_result'      => $this->formatOcrResult($prescription),
        ], 201);
    }

    /** 정한 건에 서류 한 장을 붙인다. */
    private function attachTo(Prescription $prescription, $file, string $docType): JsonResponse
    {
        $열린요청 = $this->openRequestIds($prescription);
        $this->storeAttachment($prescription, $file, $docType);
        $this->tellReuploadArrived($prescription, $열린요청);
        $this->tellOwner($prescription, $docType);

        return response()->json([
            'success'         => true,
            'message'         => PrescriptionAttachment::labelFor($docType) . ' 파일이 등록되었습니다.',
            'prescription_id' => $prescription->rx_number,
        ], 201);
    }

    /**
     * 올리기 전에 열려 있던 재업로드 요청의 id.
     *
     * 자료가 붙으면 그 요청은 모델 자리에서 저절로 닫힌다(PrescriptionAttachment::booted,
     * Prescription::booted). 무엇이 닫혔는지는 올린 뒤에야 알 수 있으므로, 올리기 전에
     * 열려 있던 것을 적어 둔다.
     *
     * @return list<int>
     */
    private function openRequestIds(Prescription $prescription): array
    {
        return $prescription->reuploadRequests()->whereNull('resolved_at')->pluck('id')->all();
    }

    /**
     * 되물었던 자료가 도착했으면 되물은 사람에게 알린다 (2026-09-17 지시).
     *
     * 웹에서 자료를 되묻고 나면 검수자는 목록을 다시 열어 봐야 올라왔는지 알았다.
     * 알리지 못해도 업로드는 이미 끝났다 — 안에서 삼킨다.
     *
     * @param list<int> $열린요청
     */
    private function tellReuploadArrived(Prescription $prescription, array $열린요청): void
    {
        if (! $열린요청) {
            return;
        }

        try {
            $닫힌 = \App\Models\PrescriptionReuploadRequest::whereIn('id', $열린요청)
                ->whereNotNull('resolved_at')->get();

            if ($닫힌->isNotEmpty()) {
                app(\App\Services\ReuploadArrivedNotice::class)
                    ->arrived($prescription->refresh(), $닫힌);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[재업로드] 알림 준비 실패', [
                'rx' => $prescription->rx_number, 'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 처방전 그림을 저장하고 건에 적는다. 상태와 관리자 메모는 건드리지 않는다 —
     * 예전에는 빈 자리를 채우며 메모를 새 값(없으면 빈 값)으로 덮어 지웠다.
     */
    private function storePrescriptionImage(Prescription $prescription, $file): void
    {
        $path = $this->storeUploadedFile($file, 'prescriptions/' . now()->format('Y/m'));

        // 그림이 바뀌면 그것을 물었던 요청이 닫힌다(Prescription::booted)
        $prescription->update([
            'image_path'          => $path,
            'image_original_name' => $file->getClientOriginalName(),
            'image_mime_type'     => $file->getMimeType(),
            'image_size'          => $file->getSize(),
        ]);

        // 상담번호 자동 채번 — 처방전이 들어올 때 한 번
        if (! $prescription->counsel_no) {
            $prescription->update([
                'counsel_no'   => Prescription::generateCounselNo(),
                'counsel_date' => now()->format('Y-m-d'),
            ]);
        }
    }

    /** 첨부 한 장을 저장한다. 같은 이름을 물었던 요청은 붙는 자리에서 닫힌다. */
    private function storeAttachment(Prescription $prescription, $file, string $docType): void
    {
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
                /* 목록도 앱이 여는 주소로 준다 — 웹 주소(image_url)는 세션을 요구하고,
                   주소가 그대로면 지우고 다시 올린 그림이 바뀌지 않는다(2026-09-17) */
                'image_url'      => $this->imageUrl($p),
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
    /**
     * 처방전 상세.
     *
     * 올린 자료를 함께 내려보낸다 — 처방전 그림과 첨부 목록. 예전에는 OCR 항목만
     * 보내서, 앱에서는 무엇을 올렸는지 볼 수 없었다. 잘못 올린 것을 고치려면
     * 먼저 무엇이 올라가 있는지 보여야 한다.
     */
    public function show(string $rxNumber): JsonResponse
    {
        $p = Prescription::with([
                'attachments',
                // 아직 닫히지 않은 것만 — 다시 올리면 저절로 닫힌다(PrescriptionAttachment::booted)
                'reuploadRequests' => fn ($q) => $q->whereNull('resolved_at')->latest('requested_at'),
            ])
            ->where('rx_number', $rxNumber)->firstOrFail();

        return response()->json([
            'success' => true,
            'data'    => $this->formatOcrResult($p) + [
                // 앱이 Bearer 토큰으로 열 수 있는 주소를 준다(웹 주소는 세션을 요구한다)
                'image_url'   => $this->imageUrl($p),
                'image_name'  => $p->image_original_name,
                // 올린 사람이 지우고 다시 올릴 수 있는 상태인가(처방전 그림 기준)
                'editable'    => $p->editableByUploader(auth()->id()),
                /* 남이 올린 건에도 서류를 보탤 수 있다 (2026-09-23 지시).
                   앱은 이 값으로 「서류 추가」를 세우고, 지우기(🗑)는 서류마다
                   can_delete 로 가린다. */
                'can_add'     => $this->보탤수있나($p),
                'is_mine'     => $p->created_by === auth()->id(),
                'owner_name'  => $p->creator?->name,
                /* 검수 재요청 단추를 세울지 (2026-09-15 지시) — 되물은 자취가 있고
                   아직 요청하지 않은 건. 규칙은 requestReview() 와 같다.
                   남의 건에서도 선다 (2026-09-23) — 서류를 보탠 사람이 그대로 청한다. */
                'can_request_review' => $this->보탤수있나($p)
                    && ($p->status === 'review_hold' || $p->reuploadRequests()->exists())
                    && ! in_array($p->status, ['review_requested', 'review_resent'], true),
                'attachments' => $p->attachments->map(fn (PrescriptionAttachment $a) => [
                    'id'         => $a->id,
                    'doc_type'   => $a->doc_type,
                    'doc_label'  => $a->doc_type_label,
                    'file_name'  => $a->file_original_name,
                    'url'        => url("/api/prescriptions/{$p->rx_number}/attachments/{$a->id}/file"),
                    'is_pdf'     => $a->is_pdf,
                    // 누가 올렸는지, 내가 지울 수 있는지 (2026-09-23 지시)
                    'uploader'   => $a->uploader?->name,
                    'can_delete' => $this->보탤수있나($p) && $this->내가올린서류인가($a, $p),
                ])->values(),
                /* 검수자가 다시 올려 달라고 한 것 — 앱이 서류마다 표시하고 사유ㆍ비고를
                   보인다(2026-09-15 지시). 다시 올리면 저절로 닫혀 여기서 빠진다. */
                'reupload_requests' => $p->reuploadRequests->map(fn (\App\Models\PrescriptionReuploadRequest $r) => [
                    'id'            => $r->id,
                    'attachment_id' => $r->attachment_id,
                    'doc_label'     => $r->doc_label,
                    'reason'        => \App\Models\PrescriptionReuploadRequest::사유[$r->reason] ?? $r->reason,
                    'memo'          => $r->memo,
                    'requested_by'  => $r->requested_by_name,
                    'requested_at'  => $r->requested_at?->format('Y-m-d H:i'),
                ])->values(),
            ],
        ]);
    }

    // ── GET /api/prescriptions/lookup ─────────────────────
    /**
     * 이름과 생년월일이 **둘 다** 맞는 건을 찾는다 (2026-09-23 지시).
     *
     * 목록(index)은 내가 올린 것만 보인다. 어제 다른 사람이 올린 건에 오늘 서류를
     * 보태려면 그 건을 찾을 길이 있어야 한다. 그렇다고 목록을 통째로 열면 앱을 쓰는
     * 사람 누구나 환자를 훑게 되므로, 이름만으로는 내주지 않는다 — 생년월일까지 맞은
     * 건만 내준다. 아는 사람을 확인하러 오는 길이지, 환자를 둘러보는 길이 아니다.
     *
     * 검수를 마친 건은 아예 내주지 않는다. 보탤 수 없는 건을 보여 줄 까닭이 없다.
     * 누가 무엇을 찾아봤는지는 이력에 남긴다.
     */
    public function lookup(Request $request): JsonResponse
    {
        $request->validate([
            'name'  => ['required', 'string', 'max:50'],
            'birth' => ['required', 'date_format:Y-m-d'],
        ], [
            'name.required'     => '환자 이름을 입력해 주십시오.',
            'birth.required'    => '생년월일을 입력해 주십시오.',
            'birth.date_format' => '생년월일은 YYYY-MM-DD 로 입력해 주십시오.',
        ]);

        $이름 = trim($request->input('name'));
        $생일 = $request->input('birth');

        $건들 = Prescription::with(['patient', 'creator'])
            ->whereIn('status', Prescription::UPLOADER_EDITABLE_STATUSES)
            ->whereHas('patient', fn ($q) => $q->where('name', $이름)->whereDate('birth_date', $생일))
            ->latest()
            ->take(20)
            ->get();

        try {
            activity()->causedBy(auth()->user())
                ->withProperties(['name' => $이름, 'birth' => $생일, 'hits' => $건들->count()])
                ->log('처방전 조회 (앱)');
        } catch (\Throwable) {}

        return response()->json([
            'success' => true,
            'data'    => $건들->map(fn (Prescription $p) => [
                'rx_number'    => $p->rx_number,
                'status'       => $p->status,
                'status_label' => $p->status_label,
                'patient_name' => $p->patient?->name ?? $p->patient_name_ocr,
                'birth_date'   => $p->patient?->birth_date?->format('Y-m-d'),
                'hospital'     => $p->hospital_name,
                'disease_name' => $p->disease_name,
                'file_count'   => $p->attachments()->count() + ($p->image_path ? 1 : 0),
                'owner_name'   => $p->creator?->name,
                'is_mine'      => $p->created_by === auth()->id(),
                'created_at'   => $p->created_at->format('Y-m-d H:i'),
            ])->values(),
        ]);
    }

    /**
     * 이 건에 서류를 보탤 수 있는가 (2026-09-23 지시).
     *
     * 건을 누가 만들었는지는 보지 않는다 — 검수를 마치기 전이면 앱을 쓰는 사람
     * 누구나 보탠다. 지우는 것은 이것과 별개다(내가올린서류인가).
     */
    private function 보탤수있나(Prescription $p): bool
    {
        return in_array($p->status, Prescription::UPLOADER_EDITABLE_STATUSES, true);
    }

    /**
     * 이 서류를 내가 올렸는가.
     *
     * 올린 사람을 적어 두기 전(2026-08 이전)에 붙은 자료는 uploaded_by 가 비어 있다.
     * 그런 것은 건을 만든 사람의 것으로 본다 — 그때는 건 주인만 올릴 수 있었다.
     */
    private function 내가올린서류인가(PrescriptionAttachment $a, Prescription $p): bool
    {
        $올린이 = $a->uploaded_by ? (int) $a->uploaded_by : (int) $p->created_by;

        return $올린이 === (int) auth()->id();
    }

    /**
     * 남의 건에 서류를 보탰으면 건 주인에게 알린다 (2026-09-23 지시).
     *
     * 내 건에 남이 무엇을 보탰는지 모르면, 검수를 청할 때 무엇이 올라와 있는지
     * 다시 열어 봐야 한다. 알리지 못해도 업로드는 이미 끝났다 — 안에서 삼킨다.
     */
    private function tellOwner(Prescription $prescription, string $docType): void
    {
        try {
            app(\App\Services\DocumentAddedNotice::class)
                ->알린다($prescription->refresh(), $docType, auth()->user());
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[서류 보탬] 알림 실패', [
                'rx' => $prescription->rx_number, 'error' => $e->getMessage(),
            ]);
        }
    }

    // ── POST /api/prescriptions/{rx_number}/request-review ──
    /**
     * 앱에서 검수를 다시 청한다 (2026-09-15 지시).
     *
     * 되물은 서류를 다시 올린 담당자가 「다 올렸다」고 알리는 자리다. 웹의 검수 요청
     * (PrescriptionController::requestReview)과 같은 규칙으로 상태를 세운다 — 되물은
     * 자취(검수 보류였거나 다시 올리기 요청이 걸렸던 적)가 있으면 검수 재요청, 없으면
     * 검수 요청. 웹과 달리 올린 사람이 고칠 수 있는 제 건이어야 한다.
     */
    public function requestReview(Request $request, string $rxNumber): JsonResponse
    {
        $p = Prescription::where('rx_number', $rxNumber)->firstOrFail();

        /* 서류를 보탠 사람이 그대로 검수를 청할 수 있어야 한다 (2026-09-23 지시).
           보태기만 되고 청하는 것은 건 주인만 할 수 있으면, 보탠 사람은 「다 올렸다」고
           따로 말을 전해야 한다. 검수를 마치기 전이면 누구나 청한다. */
        if (! $this->보탤수있나($p)) {
            return $this->refuseEdit($p);
        }

        $request->validate(['memo' => ['nullable', 'string', 'max:500']], [
            'memo.max' => '메모는 500자 이하로 입력해 주십시오.',
        ]);

        $되물은적있나 = $p->status === 'review_hold' || $p->reuploadRequests()->exists();

        $요청 = ['status' => $되물은적있나 ? 'review_resent' : 'review_requested'];

        // 요청하며 남긴 말은 요청 메모 칸에 — 비우면 적어 둔 것을 지킨다(웹과 같게)
        if (\Illuminate\Support\Facades\Schema::hasColumn('prescriptions', 'review_request_memo')) {
            $요청['review_request_memo'] = $request->input('memo') ?: $p->review_request_memo;
        } elseif ($request->filled('memo')) {
            $요청['review_memo'] = $request->input('memo');
        }

        $p->update($요청);

        activity()->causedBy(auth()->user())->performedOn($p)
            ->log($되물은적있나 ? '검수 재요청 (앱)' : '검수 요청 (앱)');

        // 승인할 수 있는 사람들에게 알린다 — 알리지 못해도 요청은 이미 됐다
        try {
            app(\App\Services\ReviewNotice::class)->askReview($p->refresh());
        } catch (\Throwable) {}

        $p->refresh();

        return response()->json([
            'success'      => true,
            'message'      => $되물은적있나 ? '검수를 다시 요청했습니다.' : '검수를 요청했습니다.',
            'status'       => $p->status,
            'status_label' => $p->status_label,
        ]);
    }

    // ── DELETE /api/prescriptions/{rx_number}/image ───────
    /**
     * 처방전 그림을 지운다.
     *
     * 레코드는 남기고 그림만 비운다. 다시 올리는 것은 앱 상세 화면의 「서류 추가」가
     * 이 번호를 싣고 올린다(fillPrescriptionImage) — 건이 둘로 갈리지 않고, 먼저 올려
     * 둔 첨부도 그대로 매달려 있다. 업로드 화면에서 올리면 새 건이 된다.
     */
    public function destroyImage(string $rxNumber): JsonResponse
    {
        $p = Prescription::where('rx_number', $rxNumber)->firstOrFail();

        if (! $p->editableByUploader(auth()->id())) {
            return $this->refuseEdit($p);
        }

        if (! $p->image_path) {
            return response()->json([
                'success' => false,
                'message' => '이미 삭제된 자료입니다.',
            ], 404);
        }

        Storage::disk('public')->delete($p->image_path);

        $p->update([
            'image_path'          => null,
            'image_original_name' => null,
            'image_mime_type'     => null,
            'image_size'          => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => '처방전을 삭제했습니다. 다시 업로드해 주십시오.',
        ]);
    }

    // ── DELETE /api/prescriptions/{rx_number}/attachments/{id} ──
    public function destroyAttachment(string $rxNumber, int $id): JsonResponse
    {
        $p = Prescription::where('rx_number', $rxNumber)->firstOrFail();

        if (! $this->보탤수있나($p)) {
            return $this->refuseEdit($p);
        }

        $attachment = $p->attachments()->whereKey($id)->first();

        if (! $attachment) {
            return response()->json([
                'success' => false,
                'message' => '이미 삭제된 자료입니다.',
            ], 404);
        }

        /* 지우는 것은 올린 사람만 (2026-09-23 지시). 남이 올린 자료가 말없이
           사라지면, 검수자가 무엇을 보고 승인했는지 알 수 없게 된다. */
        if (! $this->내가올린서류인가($attachment, $p)) {
            return response()->json([
                'success' => false,
                'message' => '본인이 등록한 서류만 삭제할 수 있습니다.',
            ], 403);
        }

        Storage::disk('public')->delete($attachment->file_path);
        $attachment->delete();

        return response()->json([
            'success' => true,
            'message' => '삭제했습니다. 다시 업로드해 주십시오.',
        ]);
    }

    // ── GET /api/prescriptions/{rx_number}/image ──────────
    /**
     * 처방전 그림을 앱으로 내려보낸다.
     *
     * 웹에도 같은 일을 하는 경로가 있지만(SecureFileController) 그쪽은 세션
     * 로그인을 요구해 앱의 Bearer 토큰으로는 열리지 않는다. 그 경로의 인증을
     * 바꾸면 웹에서 그림이 안 보이게 될 수 있어, 앱 몫을 따로 둔다.
     */
    public function image(string $rxNumber): StreamedResponse
    {
        $p = Prescription::where('rx_number', $rxNumber)->firstOrFail();

        abort_unless($p->image_path, 404);

        return $this->streamFile($p->image_path, $p->image_original_name);
    }

    // ── GET /api/prescriptions/{rx_number}/attachments/{id}/file ──
    public function attachmentFile(string $rxNumber, int $id): StreamedResponse
    {
        $p = Prescription::where('rx_number', $rxNumber)->firstOrFail();

        $attachment = $p->attachments()->whereKey($id)->first();
        abort_unless($attachment && $attachment->file_path, 404);

        return $this->streamFile($attachment->file_path, $attachment->file_original_name);
    }

    /**
     * 파일을 내보낸다 — 바뀌면 바뀐 것을 준다 (2026-09-17 지시).
     *
     * 주소는 처방번호로 만들어져 지우고 다시 올려도 그대로다. 10분짜리 캐시를 붙여
     * 두어(max-age=600) 앱은 그동안 묻지도 않고 옛 그림을 그렸다 — 다시 올린 사람은
     * 바뀌지 않았다고 본다. 쓰기 전에 물어보게 하고, 그대로면 304 로 답한다.
     */
    private function streamFile(string $path, ?string $originalName = null): StreamedResponse
    {
        $disk = Storage::disk('public');
        abort_unless($disk->exists($path), 404);

        $name = $originalName ?: basename($path);

        $때  = (int) $disk->lastModified($path);
        $tag = '"' . substr(md5($path . '|' . $때 . '|' . $disk->size($path)), 0, 16) . '"';

        $머리 = [
            'Content-Disposition'    => 'inline; filename="' . addslashes($name) . '"',
            'Cache-Control'          => 'private, no-cache, must-revalidate',
            'ETag'                   => $tag,
            'Last-Modified'          => gmdate('D, d M Y H:i:s', $때) . ' GMT',
            'X-Content-Type-Options' => 'nosniff',
        ];

        if (($가진것 = request()->headers->get('If-None-Match')) && trim($가진것) === $tag) {
            return response()->stream(fn () => null, 304, $머리);
        }

        return $disk->response($path, $name, $머리);
    }

    /**
     * 앱에 주는 그림 주소 — 파일이 바뀌면 주소도 바뀐다 (2026-09-17 지시).
     *
     * 앱이 주소로 그림을 담아 두면(캐시) 머리글을 고쳐도 다시 묻지 않는다. 주소 끝에
     * 파일을 가리키는 짧은 표를 붙여, 지우고 다시 올린 그림은 다른 주소가 되게 한다.
     */
    private function imageUrl(Prescription $p): ?string
    {
        if (! $p->image_path) {
            return null;
        }

        $표 = substr(md5($p->image_path . '|' . $p->updated_at?->timestamp), 0, 10);

        return url("/api/prescriptions/{$p->rx_number}/image") . '?v=' . $표;
    }

    /**
     * 고칠 수 없는 까닭을 알린다 — 이미 검수를 지났는지, 남의 것인지.
     *
     * 상태부터 본다. 남의 건에도 서류를 보탤 수 있게 된 뒤로(2026-09-23), 검수를
     * 마친 건을 남이 건드렸을 때 「본인이 올린 것만」이라고 답하면 까닭이 어긋난다 —
     * 제 건이어도 그 상태에서는 못 고친다.
     */
    private function refuseEdit(Prescription $p, ?string $까닭 = null): JsonResponse
    {
        $고칠수있는상태 = in_array($p->status, Prescription::UPLOADER_EDITABLE_STATUSES, true);

        return response()->json([
            'success' => false,
            'message' => $까닭 ?: ($고칠수있는상태
                ? '본인이 등록한 처방전만 수정할 수 있습니다.'
                : "「{$p->status_label}」 상태에서는 수정할 수 없습니다. 담당자에게 문의하십시오."),
        ], 403);
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
