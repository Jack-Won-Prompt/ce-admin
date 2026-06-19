<?php
// app/Services/NhisEFaxService.php

namespace App\Services;

use App\Models\NhisFaxLog;
use App\Models\Order;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NhisEFaxService
{
    protected string $driver;
    protected array  $config;

    public function __construct()
    {
        $this->driver = config('nhis.efax.driver', 'simulation');
        $this->config = config('nhis.efax', []);
    }

    // ─────────────────────────────────────────────────────────────
    // 공개 API
    // ─────────────────────────────────────────────────────────────

    /**
     * 단건 청구 팩스 발송
     * 로그를 생성하고 드라이버별 발송 처리 후 orders + nhis_fax_logs 업데이트
     */
    public function send(Order $order): NhisFaxLog
    {
        $faxNumber = $this->config['nhis_fax_number'] ?? '02-000-0000';

        // 청구 로그 생성
        $log = NhisFaxLog::create([
            'order_id'       => $order->id,
            'sent_by'        => Auth::id(),
            'fax_number'     => $faxNumber,
            'sender_number'  => $this->config['sender_number'] ?? '',
            'document_title' => $this->buildDocumentTitle($order),
            'claim_amount'   => $order->nhis_amount + $order->patient_copay,
            'nhis_amount'    => $order->nhis_amount,
            'patient_copay'  => $order->patient_copay,
            'status'         => 'queued',
            'retry_count'    => 0,
        ]);

        try {
            $result = $this->dispatchByDriver($order, $log);

            $log->update([
                'status'       => 'sent',
                'reference_no' => $result['reference_no'] ?? null,
                'sent_at'      => now(),
                'raw_payload'  => json_encode($result, JSON_UNESCAPED_UNICODE),
            ]);

            // 주문 상태 동기화
            $order->update([
                'nhis_claim_status' => 'submitted',
                'nhis_submitted_at' => now(),
                'latest_fax_log_id' => $log->id,
            ]);

        } catch (\Throwable $e) {
            $log->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
                'retry_count'   => $log->retry_count + 1,
            ]);

            Log::error('[NhisEFax] 발송 실패', [
                'order_id'  => $order->id,
                'log_id'    => $log->id,
                'error'     => $e->getMessage(),
            ]);

            throw $e;
        }

        return $log->fresh();
    }

    /**
     * 일괄 발송 — 주문 컬렉션을 받아 각각 send() 호출
     * 실패해도 계속 진행하고 결과를 반환
     */
    public function sendBulk(iterable $orders): array
    {
        $results = ['success' => [], 'failed' => []];

        foreach ($orders as $order) {
            try {
                $log = $this->send($order);
                $results['success'][] = ['order' => $order, 'log' => $log];
            } catch (\Throwable $e) {
                $results['failed'][] = ['order' => $order, 'error' => $e->getMessage()];
            }
        }

        return $results;
    }

    /**
     * NHIS 처리 결과 수동 등록 (공단 회신 처리)
     */
    public function recordNhisResult(
        NhisFaxLog $log,
        string $result,         // approved | rejected | partial
        ?float $approvedAmount,
        ?string $message
    ): void {
        $log->update([
            'nhis_result'    => $result,
            'approved_amount'=> $approvedAmount,
            'nhis_message'   => $message,
            'nhis_result_at' => now(),
        ]);

        // 주문 nhis_claim_status 동기화
        $claimStatus = match($result) {
            'approved', 'partial' => 'approved',
            'rejected'            => 'rejected',
            default               => 'submitted',
        };

        $log->order->update([
            'nhis_claim_status'  => $claimStatus,
            'nhis_approved_at'   => in_array($result, ['approved', 'partial']) ? now() : null,
            'nhis_reimbursement' => $approvedAmount,
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // 드라이버 디스패치
    // ─────────────────────────────────────────────────────────────

    protected function dispatchByDriver(Order $order, NhisFaxLog $log): array
    {
        return match($this->driver) {
            'hifaxkorea' => $this->sendViaHiFaxKorea($order, $log),
            'efax'       => $this->sendViaEFax($order, $log),
            default      => $this->sendSimulation($order, $log),    // 'simulation'
        };
    }

    // ─────────────────────────────────────────────────────────────
    // 드라이버 구현
    // ─────────────────────────────────────────────────────────────

    /**
     * [시뮬레이션] 개발/테스트용 — 실제 팩스 미발송, 랜덤 참조번호 반환
     */
    protected function sendSimulation(Order $order, NhisFaxLog $log): array
    {
        // 실제 연동 전 테스트: 의도적으로 짧은 딜레이 시뮬레이션
        usleep(200_000); // 0.2초

        $refNo = 'SIM-' . now()->format('YmdHis') . '-' . str_pad($order->id, 4, '0', STR_PAD_LEFT);

        Log::info('[NhisEFax][Simulation] 청구 팩스 발송 시뮬레이션', [
            'order_number' => $order->order_number,
            'fax_to'       => $log->fax_number,
            'reference_no' => $refNo,
            'nhis_amount'  => $order->nhis_amount,
        ]);

        return [
            'driver'       => 'simulation',
            'reference_no' => $refNo,
            'message'      => '시뮬레이션 발송 완료',
            'timestamp'    => now()->toIso8601String(),
        ];
    }

    /**
     * [HiFaxKorea] 실제 팩스 발송 API 연동
     * https://www.hifaxkorea.com — API 키 설정 필요
     */
    protected function sendViaHiFaxKorea(Order $order, NhisFaxLog $log): array
    {
        $cfg = $this->config['hifaxkorea'];

        // 청구서 텍스트 본문 생성
        $body = $this->buildClaimDocument($order);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $cfg['api_key'],
            'Content-Type'  => 'application/json',
        ])->post($cfg['api_url'] . '/v1/fax/send', [
            'to'       => preg_replace('/[^0-9]/', '', $log->fax_number),
            'from'     => preg_replace('/[^0-9]/', '', $log->sender_number),
            'subject'  => $log->document_title,
            'body'     => $body,
            'type'     => 'text',
            'callback' => url('/nhis/fax-callback'),
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException(
                'HiFaxKorea API 오류: ' . $response->body()
            );
        }

        $data = $response->json();

        return [
            'driver'       => 'hifaxkorea',
            'reference_no' => $data['faxId'] ?? $data['id'] ?? null,
            'raw'          => $data,
            'timestamp'    => now()->toIso8601String(),
        ];
    }

    /**
     * [eFax] 일반 eFax API 연동
     */
    protected function sendViaEFax(Order $order, NhisFaxLog $log): array
    {
        $cfg  = $this->config['efax'];
        $body = $this->buildClaimDocument($order);

        $response = Http::withBasicAuth($cfg['account_id'], $cfg['api_key'])
            ->post($cfg['api_url'] . '/fax/send', [
                'to'      => $log->fax_number,
                'from'    => $log->sender_number,
                'subject' => $log->document_title,
                'content' => base64_encode($body),
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException('eFax API 오류: ' . $response->body());
        }

        $data = $response->json();

        return [
            'driver'       => 'efax',
            'reference_no' => $data['id'] ?? null,
            'raw'          => $data,
            'timestamp'    => now()->toIso8601String(),
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // 청구서 문서 생성 유틸리티
    // ─────────────────────────────────────────────────────────────

    protected function buildDocumentTitle(Order $order): string
    {
        $institution = config('nhis.institution.name', 'CE 의료기기');
        return "[NHIS 청구] {$institution} / {$order->order_number} / {$order->patient?->name}";
    }

    /**
     * NHIS e-Fax 청구 본문 생성 (텍스트 기반)
     * 실제 기관 코드·양식에 맞게 EDI 형식으로 변경 필요
     */
    public function buildClaimDocument(Order $order): string
    {
        $inst    = config('nhis.institution');
        $patient = $order->patient;
        $rx      = $order->prescription;
        $now     = now()->format('Y-m-d H:i');

        $lines = [
            '═══════════════════════════════════════════════',
            '         건강보험 요양급여비용 청구서',
            '═══════════════════════════════════════════════',
            '',
            '■ 청구 기관 정보',
            "  기관명    : {$inst['name']}",
            "  기관코드  : {$inst['code']}",
            "  사업자번호: {$inst['biz_no']}",
            '',
            '■ 환자 정보',
            "  환자명    : {$patient?->name}",
            "  주민번호  : {$patient?->masked_resident_no}",
            "  건강보험번호: {$patient?->health_insurance_no}",
            '',
            '■ 처방 정보',
            "  처방전번호: {$rx?->rx_number}",
            "  처방일    : {$rx?->issued_date?->format('Y-m-d')}",
            "  병원명    : {$rx?->hospital_name}",
            "  담당의사  : {$rx?->doctor_name}",
            "  상병명    : {$rx?->disease_name}",
            '',
            '■ 급여 품목',
            "  제품명    : {$order->product_name}",
            "  제품코드  : {$order->product_code}",
            "  수량      : {$order->quantity}",
            "  보험가(단가): " . number_format($order->unit_price) . '원',
            '',
            '■ 청구 금액',
            "  건보 부담금  : " . number_format($order->nhis_amount) . '원',
            "  환자 본인부담: " . number_format($order->patient_copay) . '원',
            "  합계         : " . number_format($order->nhis_amount + $order->patient_copay) . '원',
            '',
            '■ 주문 정보',
            "  주문번호  : {$order->order_number}",
            "  배송주소  : {$order->shipping_address}",
            "  배송완료일: {$order->delivered_at?->format('Y-m-d')}",
            '',
            '───────────────────────────────────────────────',
            "  청구일시  : {$now}",
            '═══════════════════════════════════════════════',
        ];

        return implode("\n", $lines);
    }
}
