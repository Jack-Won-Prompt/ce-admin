<?php

namespace App\Http\Controllers;

use App\Models\PrescriptionDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class PrescriptionDocumentController extends Controller
{
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

        $perPage = in_array((int) $request->input('per_page', 20), [10, 20, 50, 100])
            ? (int) $request->input('per_page', 20)
            : 20;

        $documents = $query->paginate($perPage)->withQueryString();

        $typeCounts = PrescriptionDocument::selectRaw('type, count(*) as cnt')
            ->groupBy('type')
            ->pluck('cnt', 'type');

        return view('documents.index', compact('documents', 'typeCounts'));
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
                return response($content, 200, [
                    'Content-Type'        => 'application/pdf',
                    'Content-Disposition' => 'inline; filename*=UTF-8\'\'' . rawurlencode($document->original_filename),
                ]);
            }
        }
        abort(404, '파일을 찾을 수 없습니다.');
    }
}
