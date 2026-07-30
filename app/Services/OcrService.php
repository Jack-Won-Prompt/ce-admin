<?php
// app/Services/OcrService.php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class OcrService
{
    /**
     * 처방전 이미지에서 텍스트 추출 및 구조화
     */
    public function extractFromImage(string $imagePath): array
    {
        try {
            $absolutePath = Storage::disk('public')->path($imagePath);

            if (!file_exists($absolutePath)) {
                throw new \RuntimeException("이미지 파일을 찾을 수 없습니다: {$imagePath}");
            }

            $mimeType = $this->detectMimeType($absolutePath);

            // OCR 공급자는 AWS Textract 단일. 구조화는 정규식 파서가 담당한다.
            $result = $this->runProvider($absolutePath, $mimeType);

            $parsed = $this->parsePrescriptionFields($result['text']);
            $parsed['raw_text'] = $result['text'];

            return [
                'data'       => $parsed,
                'confidence' => $this->calcFieldConfidence($parsed),
                'raw_text'   => $result['text'],
            ];

        } catch (\Exception $e) {
            Log::error('OCR 처리 실패', [
                'path'  => $imagePath,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * 처방전 이외 첨부 문서(주민등록증, 위임장 등)에서 텍스트만 추출
     */
    public function extractTextOnly(string $imagePath): array
    {
        try {
            $absolutePath = Storage::disk('public')->path($imagePath);
            if (!file_exists($absolutePath)) {
                throw new \RuntimeException("파일을 찾을 수 없습니다: {$imagePath}");
            }

            $mimeType = $this->detectMimeType($absolutePath);

            if (!$this->textractUsable($mimeType)) {
                Log::info('첨부 OCR 건너뜀 — Textract 미설정 또는 미지원 형식', ['mime' => $mimeType]);
                return ['raw_text' => '', 'confidence' => 0];
            }

            $rawText = $this->callTextract($absolutePath)['text'] ?? '';

            return ['raw_text' => $rawText, 'confidence' => empty($rawText) ? 0 : 50];

        } catch (\Exception $e) {
            Log::error('첨부 OCR 실패', ['path' => $imagePath, 'error' => $e->getMessage()]);
            return ['raw_text' => '', 'confidence' => 0];
        }
    }

    /**
     * OCR 공급자 실행. 공급자는 AWS Textract 단일이다.
     * Textract 는 raw text 만 주므로 구조화는 parsePrescriptionFields 정규식이 담당한다.
     * (한글 필드는 Textract 가 인식하지 못하므로 검수 화면에서 사람이 보정한다)
     */
    private function runProvider(string $absolutePath, string $mimeType): array
    {
        if (!$this->textractUsable($mimeType)) {
            throw new \RuntimeException(
                'OCR 을 사용할 수 없습니다. AWS Textract 자격증명과 지원 형식(PNG·JPEG)을 확인해 주세요.'
            );
        }

        return $this->callTextract($absolutePath);
    }

    /** Textract 사용 가능 여부: 자격증명 설정 + SDK 설치 + 동기 지원 이미지 형식(PNG/JPEG) */
    private function textractUsable(string $mimeType): bool
    {
        return config('ocr.textract.enabled')
            && class_exists(\Aws\Textract\TextractClient::class)
            && in_array($mimeType, ['image/png', 'image/jpeg'], true);
    }

    /**
     * AWS Textract detectDocumentText 호출 → LINE 블록을 이어붙여 raw text 반환.
     * (구조화 JSON 은 제공되지 않으므로 parsed 는 비움)
     */
    private function callTextract(string $absolutePath): array
    {
        $cfg  = config('ocr.textract');
        $args = ['region' => $cfg['region'], 'version' => $cfg['version'] ?? 'latest'];
        // 명시 키가 있으면 사용, 없으면 기본 자격증명 체인(EC2 IAM Role 등)
        if (!empty($cfg['key']) && !empty($cfg['secret'])) {
            $args['credentials'] = ['key' => $cfg['key'], 'secret' => $cfg['secret']];
        }

        $client = new \Aws\Textract\TextractClient($args);
        $res    = $client->detectDocumentText([
            'Document' => ['Bytes' => file_get_contents($absolutePath)],
        ]);

        $lines = [];
        foreach ($res['Blocks'] ?? [] as $b) {
            if (($b['BlockType'] ?? '') === 'LINE' && isset($b['Text'])) {
                $lines[] = $b['Text'];
            }
        }
        $text = implode("\n", $lines);

        Log::info('Textract OCR 완료', ['lines' => count($lines), 'chars' => mb_strlen($text)]);

        return ['text' => $text, 'confidence' => 0.0, 'parsed' => []];
    }

    /**
     * 처방전 주요 항목 인식률로 신뢰도 계산
     * 각 항목이 null/빈문자열이면 미인식, 값이 있으면 인식으로 판단
     */
    private function calcFieldConfidence(array $data): float
    {
        // 처방전 핵심 항목 정의 (UI 표시 기준)
        $fields = [
            // 수진자 (4항목)
            'patient_name',
            'resident_no',
            'mobile',
            'address',
            // 의료기관·의사 (4항목)
            'hospital_name',
            'doctor_name',
            'specialty',
            'issued_date',
            // 처방·병명 (3항목)
            'disease_name',
            'disease_code',
            'product_name',
            // 투약 (4항목)
            'daily_count',
            'total_days',
            'total_count',
            'usage_period',
        ];

        $total    = count($fields);
        $filled   = 0;

        foreach ($fields as $key) {
            $val = $data[$key] ?? null;
            if ($val !== null && $val !== '' && $val !== false) {
                $filled++;
            }
        }

        return round(($filled / $total) * 100, 1);
    }

    /**
     * 이미지 MIME 타입 감지
     */
    private function detectMimeType(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png'         => 'image/png',
            'gif'         => 'image/gif',
            'webp'        => 'image/webp',
            default       => 'image/jpeg',
        };
    }

    /**
     * Textract raw text 에서 처방전 항목을 뽑아내는 정규식 파서
     * 소모성재료 처방전 + 일반 처방전 모두 지원
     */
    private function parsePrescriptionFields(string $text): array
    {
        $result = [];

        // ── 처방전 유형 감지 ──────────────────────────────
        $consumableKeywords = ['자가도뇨', '욕창예방', '인공호흡기', '기침유발기', '요실금', '소모성재료', '이동식 산소'];
        $result['prescription_type'] = 'unknown';
        foreach ($consumableKeywords as $kw) {
            if (mb_strpos($text, $kw) !== false) {
                $result['prescription_type'] = '소모성재료';
                // 제목에서 품목명 추출
                if (preg_match('/(' . preg_quote($kw, '/') . '[가-힣a-zA-Z\s]*)/u', $text, $m)) {
                    $result['product_name'] = trim($m[1]);
                }
                break;
            }
        }

        // ── 기본 필드 ────────────────────────────────────
        if (preg_match('/등록번호\s*[:\s]*([0-9]{5,10})/u', $text, $m))
            $result['registration_no'] = trim($m[1]);
        if (preg_match('/연번호\s*[:\s]*([0-9]{10,15})/u', $text, $m))
            $result['serial_no'] = trim($m[1]);

        $result['is_reissue'] = mb_strpos($text, '재발급') !== false
            && (mb_strpos($text, '✓') !== false || mb_strpos($text, '■') !== false);

        // ── 환자 정보 ─────────────────────────────────────
        if (preg_match('/성\s*명\s+([가-힣]{2,5})/u', $text, $m))
            $result['patient_name'] = trim($m[1]);
        if (preg_match('/([0-9]{6}[-—–]\s*[0-9]{7})/u', $text, $m))
            $result['resident_no'] = preg_replace('/\s+/', '', $m[1]);
        if (preg_match('/주\s*소\s+(.+?)(?=전화|연락|휴대|$)/u', $text, $m))
            $result['address'] = trim($m[1]);
        // 휴대전화 — 010/011/016/017/018/019 로 시작하는 번호 우선
        if (preg_match('/(?:휴대전화|휴대폰|핸드폰|연락처|전화)\s*[:\s]*((01[016789])[0-9\-—–\s]{8,13})/u', $text, $m))
            $result['mobile'] = preg_replace('/[\s—–]/', '-', trim($m[1]));
        // 위에서 못 잡은 경우: 010/011... 번호 패턴 직접 탐색
        if (empty($result['mobile']) && preg_match('/(01[016789])[-—–\s]?([0-9]{3,4})[-—–\s]?([0-9]{4})/u', $text, $m))
            $result['mobile'] = "{$m[1]}-{$m[2]}-{$m[3]}";
        // 자택전화 (02/031/... 지역번호 시작)
        if (preg_match('/자택전화\s*[:\s]*([0-9]{2,3}[-—–][0-9]{3,4}[-—–][0-9]{4})/u', $text, $m))
            $result['phone'] = trim($m[1]);

        // ── 진료/상병 ─────────────────────────────────────
        if (preg_match('/(?:진료과목|전문과목)\s+([가-힣a-zA-Z]+과)/u', $text, $m))
            $result['department'] = trim($m[1]);

        // 상병명 (여러 개 가능)
        if (preg_match('/상\s*병\s*명\s+(.+?)(?=상병코드|처방|$)/us', $text, $m))
            $result['disease_name'] = preg_replace('/\s+/', ' ', trim($m[1]));

        // 상병코드 (여러 개: Q059, K319 등)
        $codes = [];
        if (preg_match_all('/\b([A-Z][0-9]{2,3}(?:\.[0-9x]+)?)\b/u', $text, $matches)) {
            $codes = array_unique($matches[1]);
        }
        if (!empty($codes)) {
            $result['disease_code'] = implode(', ', $codes);
        }

        // 상병구분 (체크된 항목)
        $conditionMap = ['신성상병', '후천성 취수성 상병', '후천성 질환 상병', '2차성 방광 기능이상'];
        foreach ($conditionMap as $cond) {
            if (mb_strpos($text, $cond) !== false) {
                $result['condition_type'] = ($result['condition_type'] ?? '') . $cond . ' ';
                break; // 첫 번째 체크된 항목
            }
        }
        if (isset($result['condition_type'])) {
            $result['condition_type'] = trim($result['condition_type']);
        }

        // ── 처방 수량 ─────────────────────────────────────
        if (preg_match('/1일\s*처방개수\s*([0-9]+)/u', $text, $m))
            $result['daily_count'] = (int) $m[1];
        if (preg_match('/총\s*처방기간\s*(?:\(일\))?\s*([0-9]+)/u', $text, $m))
            $result['total_days'] = (int) $m[1];
        if (preg_match('/총\s*계\s*(?:\(개\))?\s*([0-9]+)/u', $text, $m))
            $result['total_count'] = (int) $m[1];

        // ── 기관 정보 ─────────────────────────────────────
        // 보장기관명 or 요양기관명
        if (preg_match('/(?:보장기관명|요양기관명)\s*(?:\(기호\))?\s*[:\s]*([가-힣a-zA-Z\s]+(?:병원|의원|의료원|한의원))\s*\(?\s*([0-9]{8,10})?/u', $text, $m)) {
            $result['hospital_name'] = trim($m[1]);
            if (!empty($m[2])) $result['hospital_code'] = trim($m[2]);
        }
        if (empty($result['hospital_code'])) {
            if (preg_match('/(?:기관기호|기관코드|기호)\s*[:\s]*([0-9]{8,10})/u', $text, $m))
                $result['hospital_code'] = trim($m[1]);
        }

        // ── 의사 정보 ─────────────────────────────────────
        // "담당의사성명(면번호): 장재진 (제 65644 호)"
        if (preg_match('/담당의사성명\s*(?:\(면번호\))?\s*[:\s]*([가-힣]{2,5})\s*(?:\(제\s*([0-9]+)\s*호\))?/u', $text, $m)) {
            $result['doctor_name'] = trim($m[1]);
            if (!empty($m[2])) $result['license_no'] = trim($m[2]);
        }
        // "전문과목(전문의 자격번호): 비뇨의학과 (제 1685 호)"
        if (preg_match('/전문과목\s*(?:\(전문의\s*자격번호\))?\s*[:\s]*([가-힣]+과)\s*(?:\(제\s*([0-9]+)\s*호\))?/u', $text, $m)) {
            $result['specialty'] = trim($m[1]);
            if (!empty($m[2])) $result['specialist_no'] = trim($m[2]);
        }

        // ── 날짜 ─────────────────────────────────────────
        // "2026. 02. 19" 형식 → YYYY-MM-DD
        if (preg_match('/([0-9]{4})\.\s*([0-9]{1,2})\.\s*([0-9]{1,2})/u', $text, $m)) {
            $result['issued_date'] = sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
        }

        $result['usage_period'] = '교부일로부터 처방기간까지';

        return $result;
    }
}
