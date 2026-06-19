<?php

namespace App\Events;

use App\Models\PrescriptionConsent;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConsentSubmitted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public PrescriptionConsent $consent)
    {
        $this->consent->load('prescription.patient');
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('admin')];
    }

    public function broadcastAs(): string
    {
        return 'consent.submitted';
    }

    public function broadcastWith(): array
    {
        $prescription = $this->consent->prescription;

        return [
            'prescription_id' => $prescription->id,
            'rx_number'       => $prescription->rx_number,
            'patient_name'    => $prescription->patient?->name
                                 ?? $prescription->patient_name_ocr
                                 ?? '환자',
            'status'          => $this->consent->status,
            'responded_at'    => $this->consent->responded_at?->format('Y-m-d H:i'),
            'has_signature'   => ! empty($this->consent->signature_data),
        ];
    }
}
