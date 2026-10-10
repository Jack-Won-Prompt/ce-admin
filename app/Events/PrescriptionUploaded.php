<?php

namespace App\Events;

use App\Models\Prescription;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PrescriptionUploaded implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly Prescription $prescription,
        public readonly string       $uploaderName
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('admin')];
    }

    public function broadcastAs(): string
    {
        return 'prescription.uploaded';
    }

    public function broadcastWith(): array
    {
        $p = $this->prescription;
        return [
            'rx_number'     => $p->rx_number,
            /* 고른 환자 이름으로 받친다 (2026-10-10). 앱ㆍH5 업로드는 환자를 거래처에서
               골라 붙이므로 patient_name_ocr 이 비어, 웹 담당자 화면에 뜨는 실시간 알림이
               늘 「미인식」으로 보였다. 상세 화면과 같은 잣대로 맞춘다. */
            'patient_name'  => $p->patient_name_ocr ?: ($p->patient?->name ?? '미인식'),
            'hospital_name' => $p->hospital_name    ?? '',
            'status'        => $p->status,
            'uploader_name' => $this->uploaderName,
            'uploaded_at'   => $p->created_at->format('H:i'),
        ];
    }
}
