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
            'patient_name'  => $p->patient_name_ocr ?? '미인식',
            'hospital_name' => $p->hospital_name    ?? '',
            'status'        => $p->status,
            'uploader_name' => $this->uploaderName,
            'uploaded_at'   => $p->created_at->format('H:i'),
        ];
    }
}
