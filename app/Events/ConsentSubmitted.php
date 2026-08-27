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

    /**
     * @param array<string,string> $applied 서명과 함께 주문 등록에 옮겨 적은 칸 (입력칸 id => 값)
     */
    public function __construct(public PrescriptionConsent $consent, public array $applied = [])
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
            /* 환자가 동의서에 적어 준 것 가운데 환자ㆍ처방전으로 옮겨 적은 칸.
               그 처방전을 열어 두고 있는 화면이 새로고침 없이 그대로 받아 앉힌다. */
            'applied'         => $this->applied,
        ];
    }
}
