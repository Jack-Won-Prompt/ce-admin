<?php

namespace App\Http\Controllers;

use App\Models\Prescription;
use App\Models\PrescriptionAttachment;
use App\Models\PrescriptionDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class PrescriptionDocumentController extends Controller
{
    /** 서류 유형 (탭 · 등록 폼 · 검증에서 공용) */
    public const TYPES = [
        'consent'      => '위임동의서',
        'delegation'   => '요양비위임장',
        'fax'          => '팩스통합본',
        'cash_receipt' => '현금영수증',
        'tax_invoice'  => '세금계산서',
    ];

    public function index(Request $request): View
    {
        $query = PrescriptionDocument::with(['prescription.patient', 'creator'])
            ->latest();

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('q')) {
            $kw = $request->q;
            $query->where(function ($q) use ($kw) {
                $q->where('original_filename', 'like', "%{$kw}%")
                  ->orWhereHas('prescription.patient', fn($p) => $p->where('name', 'like', "%{$kw}%"))
                  ->orWhereHas('prescription', fn($p) => $p->where('rx_number', 'like', "%{$kw}%"));
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $gridData = $query->get()->map(function ($doc) {
            return [
                'id'        => $doc->id,
                'type'      => $doc->typeLabel(),
                'source'    => $doc->sourceLabel(),
                'patient'   => $doc->prescription?->patient?->name ?? '',
                'rx_number' => $doc->prescription?->rx_number ?? '',
                'filename'  => $doc->original_filename ?? '',
                'creator'   => $doc->creator?->name ?? '',
                'created'   => $doc->created_at?->format('Y-m-d H:i') ?? '',
                'download'  => route('documents.download', $doc),
                // 행 클릭 시 해당 처방전의 전체 서류를 불러오기 위한 식별자 (그리드 비표시)
                'prescription_id' => $doc->prescription_id,
            ];
        });

        $total = $gridData->count();

        $typeCounts = PrescriptionDocument::selectRaw('type, count(*) as cnt')
            ->groupBy('type')
            ->pluck('cnt', 'type');

        return view('documents.index', [
            'gridData'   => $gridData,
            'total'      => $total,
            'typeCounts' => $typeCounts,
            'types'      => self::TYPES,
        ]);
    }

    /**
     * 서류 등록 탭: 특정 처방전에 속한 '모든 서류'를 반환.
     * 생성 서류(PrescriptionDocument)와 첨부 서류(PrescriptionAttachment)를 함께 내려준다.
     */
    public function byPrescription(Prescription $prescription): JsonResponse
    {
        $prescription->loadMissing('patient');

        $documents = PrescriptionDocument::where('prescription_id', $prescription->id)
            ->with('creator')
            ->latest()
            ->get()
            ->map(fn (PrescriptionDocument $d) => [
                'id'        => $d->id,
                'type'      => $d->type,
                'typeLabel' => $d->typeLabel(),
                'source'    => $d->sourceLabel(),
                'filename'  => $d->original_filename ?? '',
                'creator'   => $d->creator?->name ?? '',
                'created'   => $d->created_at?->format('Y-m-d H:i') ?? '',
                'download'  => route('documents.download', $d),
            ])->values();

        $attachments = $prescription->attachments()->latest()->get()
            ->map(fn (PrescriptionAttachment $a) => [
                'id'        => $a->id,
                'typeLabel' => $a->doc_type_label,
                'filename'  => $a->file_original_name ?? '',
                'created'   => $a->created_at?->format('Y-m-d H:i') ?? '',
                'url'       => $a->file_url,
                'isPdf'     => $a->is_pdf,
            ])->values();

        return response()->json([
            'success' => true,
            'prescription' => [
                'id'        => $prescription->id,
                'rx_number' => $prescription->rx_number,
                'patient'   => $prescription->patient?->name ?? $prescription->patient_name_ocr ?? '-',
                'hospital'  => $prescription->hospital_name ?? '-',
                'status'    => $prescription->status_label,
                'url'       => route('prescriptions.show', $prescription),
            ],
            'documents'   => $documents,
            'attachments' => $attachments,
        ]);
    }

    /**
     * 서류 등록 탭: 파일을 업로드해 생성 서류(PrescriptionDocument)로 등록.
     * 등록 즉시 서류 관리 목록과 유형별 건수에 반영된다.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'prescription_id' => 'required|exists:prescriptions,id',
            'type'            => 'required|string|in:' . implode(',', array_keys(self::TYPES)),
            'file'            => 'required|file|mimes:pdf,jpg,jpeg,png,heic|max:51200',
        ]);

        $prescription = Prescription::findOrFail($data['prescription_id']);

        $file = $request->file('file');
        $path = $file->storeAs(
            'documents/manual/' . now()->format('Y/m'),
            now()->format('Ymd_His') . '_' . uniqid() . '.' . $file->getClientOriginalExtension()
        );

        $doc = PrescriptionDocument::create([
            'prescription_id'   => $prescription->id,
            'patient_id'        => $prescription->patient_id,
            'created_by'        => Auth::id(),
            'type'              => $data['type'],
            'file_path'         => $path,
            'original_filename' => $file->getClientOriginalName(),
        ]);

        activity()->causedBy(Auth::user())
            ->log("서류 직접 등록: {$doc->typeLabel()} / {$prescription->rx_number}");

        return response()->json([
            'success'  => true,
            'message'  => $doc->typeLabel() . ' 서류를 등록했습니다.',
            'document' => [
                'id'        => $doc->id,
                'type'      => $doc->type,
                'typeLabel' => $doc->typeLabel(),
                'source'    => $doc->sourceLabel(),
                'filename'  => $doc->original_filename,
                'creator'   => Auth::user()?->name ?? '',
                'created'   => $doc->created_at->format('Y-m-d H:i'),
                'download'  => route('documents.download', $doc),
                'patient'   => $prescription->patient?->name ?? '',
                'rx_number' => $prescription->rx_number,
                'prescription_id' => $prescription->id,
            ],
        ]);
    }

    public function download(PrescriptionDocument $document)
    {
        foreach (['local', 'public'] as $disk) {
            if (Storage::disk($disk)->exists($document->file_path)) {
                return Storage::disk($disk)->download($document->file_path, $document->original_filename);
            }
        }
        abort(404, '파일을 찾을 수 없습니다.');
    }

    public function preview(PrescriptionDocument $document)
    {
        foreach (['local', 'public'] as $disk) {
            if (Storage::disk($disk)->exists($document->file_path)) {
                $content = Storage::disk($disk)->get($document->file_path);
                /* 대개 PDF 지만, 장표를 PNG 로 그려 넣던 시절의 줄이 남아 있다.
                   무엇이든 application/pdf 로 내보내면 그림이 깨진 채로 뜬다. */
                $mime = [
                    'pdf' => 'application/pdf', 'png' => 'image/png',
                    'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
                ][strtolower(pathinfo((string) $document->file_path, PATHINFO_EXTENSION))] ?? 'application/pdf';

                return response($content, 200, [
                    'Content-Type'        => $mime,
                    'Content-Disposition' => 'inline; filename*=UTF-8\'\'' . rawurlencode($document->original_filename),
                ]);
            }
        }
        abort(404, '파일을 찾을 수 없습니다.');
    }
}
