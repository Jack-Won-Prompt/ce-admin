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

            $imageContent = base64_encode(file_get_contents($absolutePath));
            $mimeType     = $this->detectMimeType($absolutePath);

            // Claude 우선, 실패 시 OpenAI 폴백
            $result = $this->callWithFallback($imageContent, $mimeType);

            $parsed = !empty($result['parsed'])
                ? $this->mapOpenAiFields($result['parsed'])
                : $this->parsePrescriptionFields($result['text']);

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

            $imageContent = base64_encode(file_get_contents($absolutePath));
            $mimeType     = $this->detectMimeType($absolutePath);

            $anthropicKey = config('services.anthropic.key');
            $openaiKey    = config('services.openai.api_key');

            $rawText = '';

            if (!empty($anthropicKey)) {
                try {
                    $rawText = $this->callClaudeTextOnly($imageContent, $mimeType);
                } catch (\Throwable $e) {
                    Log::warning('첨부 OCR Claude 실패', ['error' => $e->getMessage()]);
                }
            }

            if (empty($rawText) && !empty($openaiKey)) {
                $result  = $this->callOpenAI($imageContent, $mimeType);
                $rawText = $result['text'] ?? '';
            }

            return ['raw_text' => $rawText, 'confidence' => empty($rawText) ? 0 : 50];

        } catch (\Exception $e) {
            Log::error('첨부 OCR 실패', ['path' => $imagePath, 'error' => $e->getMessage()]);
            return ['raw_text' => '', 'confidence' => 0];
        }
    }

    private function callClaudeTextOnly(string $base64Image, string $mimeType): string
    {
        $apiKey   = config('services.anthropic.key');
        $endpoint = 'https://api.anthropic.com/v1/messages';

        $response = \Illuminate\Support\Facades\Http::withHeaders([
            'x-api-key'         => $apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type'      => 'application/json',
        ])->timeout(60)->post($endpoint, [
            'model'      => 'claude-haiku-4-5-20251001',
            'max_tokens' => 1024,
            'messages'   => [[
                'role'    => 'user',
                'content' => [[
                    'type'       => 'image',
                    'source'     => ['type' => 'base64', 'media_type' => $mimeType, 'data' => $base64Image],
                ], [
                    'type' => 'text',
                    'text' => '이 문서 이미지에서 보이는 모든 텍스트를 그대로 추출해주세요. 구조와 형식을 최대한 유지하세요.',
                ]],
            ]],
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Claude API 오류: ' . $response->status());
        }

        return $response->json('content.0.text', '');
    }

    private function callWithFallback(string $base64Image, string $mimeType): array
    {
        $anthropicKey = config('services.anthropic.key');
        $openaiKey    = config('services.openai.api_key');

        // 1) Claude (Anthropic) 시도
        if (!empty($anthropicKey)) {
            try {
                return $this->callClaude($base64Image, $mimeType);
            } catch (\Throwable $e) {
                Log::warning('Claude OCR 실패, OpenAI 폴백 시도', ['error' => $e->getMessage()]);
            }
        }

        // 2) OpenAI 폴백
        if (!empty($openaiKey)) {
            return $this->callOpenAI($base64Image, $mimeType);
        }

        throw new \RuntimeException('Claude API 키와 OpenAI API 키가 모두 설정되지 않았습니다.');
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
     * OpenAI JSON → DB 저장용 필드명으로 매핑
     */
    private function mapOpenAiFields(array $p): array
    {
        return [
            'prescription_type' => $p['prescription_type'] ?? null,
            'registration_no'   => $p['registration_no']   ?? null,
            'serial_no'         => $p['serial_no']         ?? null,
            'is_reissue'        => $p['is_reissue']        ?? false,
            'patient_name'      => $p['patient_name']      ?? null,
            'resident_no'       => $p['resident_no']       ?? null,
            'phone'             => $p['phone']             ?? null,
            'mobile'            => $p['mobile']            ?? null,
            'address'           => $p['address']           ?? null,
            'department'        => $p['department']        ?? null,
            'disease_name'      => $p['disease_name']      ?? null,
            'disease_code'      => $p['disease_code']      ?? null,
            'condition_type'    => $p['condition_type']    ?? null,
            'daily_count'       => isset($p['daily_count'])  && $p['daily_count']  !== null ? (int)$p['daily_count']  : null,
            'total_days'        => isset($p['total_days'])   && $p['total_days']   !== null ? (int)$p['total_days']   : null,
            'total_count'       => isset($p['total_count'])  && $p['total_count']  !== null ? (int)$p['total_count']  : null,
            'product_name'      => $p['product_name']      ?? null,
            'hospital_name'     => $p['hospital_name']     ?? null,
            'hospital_code'     => $p['hospital_code']     ?? null,
            'doctor_name'       => $p['doctor_name']       ?? null,
            'specialty'         => $p['specialty']         ?? null,
            'license_no'        => $p['license_no']        ?? null,
            'specialist_no'     => $p['specialist_no']     ?? null,
            'issued_date'       => $p['issued_date']       ?? null,
            'usage_period'      => $p['usage_period']      ?? '교부일로부터 처방기간까지',
        ];
    }

    /**
     * Claude Vision API 호출 (claude-opus-4-7 또는 claude-sonnet-4-6)
     */
    private function callClaude(string $base64Image, string $mimeType): array
    {
        $apiKey   = config('services.anthropic.key');
        $endpoint = 'https://api.anthropic.com/v1/messages';
        $model    = 'claude-opus-4-7';

        $systemPrompt = $this->ocrSystemPrompt();

        $payload = [
            'model'      => $model,
            'max_tokens' => 3000,
            'system'     => $systemPrompt,
            'messages'   => [
                [
                    'role'    => 'user',
                    'content' => [
                        [
                            'type'   => 'image',
                            'source' => [
                                'type'       => 'base64',
                                'media_type' => $mimeType,
                                'data'       => $base64Image,
                            ],
                        ],
                        [
                            'type' => 'text',
                            'text' => '이 처방전 이미지의 유형을 먼저 판단하고, 모든 텍스트를 정확히 추출하여 지정된 JSON 형식으로만 반환해주세요. 소모성재료 처방전인 경우 product_name과 다중 상병코드를 반드시 추출해주세요.',
                        ],
                    ],
                ],
            ],
        ];

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'x-api-key: ' . $apiKey,
                'anthropic-version: 2023-06-01',
            ],
            CURLOPT_TIMEOUT        => 60,
        ]);

        $response  = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            throw new \RuntimeException('Claude API 네트워크 오류: ' . $curlError);
        }

        if ($httpCode !== 200) {
            $errorData = json_decode($response, true);
            $errorMsg  = $errorData['error']['message'] ?? 'HTTP ' . $httpCode;
            throw new \RuntimeException('Claude API 오류: ' . $errorMsg);
        }

        $data    = json_decode($response, true);
        $content = $data['content'][0]['text'] ?? '';

        // 마크다운 코드블록 제거
        $content = preg_replace('/^```json\s*/im', '', $content);
        $content = preg_replace('/^```\s*$/im', '', $content);
        $content = trim($content);

        $parsed = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($parsed)) {
            Log::warning('Claude OCR JSON 파싱 실패, 정규식 폴백', ['raw_response' => $content]);
            return ['text' => $content, 'confidence' => 50.0, 'parsed' => []];
        }

        Log::info('Claude OCR 성공', [
            'model'        => $model,
            'type'         => $parsed['prescription_type'] ?? 'unknown',
            'patient_name' => $parsed['patient_name']  ?? 'null',
            'hospital'     => $parsed['hospital_name'] ?? 'null',
            'disease_code' => $parsed['disease_code']  ?? 'null',
            'confidence'   => $parsed['confidence']    ?? 'null',
        ]);

        return [
            'text'       => $parsed['raw_text'] ?? $content,
            'confidence' => is_numeric($parsed['confidence'] ?? null) ? (float)$parsed['confidence'] : 70.0,
            'parsed'     => $parsed,
        ];
    }

    /**
     * OpenAI GPT-4o Vision API 호출
     */
    private function callOpenAI(string $base64Image, string $mimeType): array
    {
        $apiKey   = config('services.openai.api_key');
        $endpoint = 'https://api.openai.com/v1/chat/completions';

        if (empty($apiKey)) {
            throw new \RuntimeException('OpenAI API 키가 설정되지 않았습니다. .env 파일의 OPENAI_API_KEY를 확인해주세요.');
        }

        $systemPrompt = $this->ocrSystemPrompt();

        $payload = [
            'model'      => 'gpt-4o',
            'max_tokens' => 3000,
            'messages'   => [
                ['role' => 'system', 'content' => $systemPrompt],
                [
                    'role'    => 'user',
                    'content' => [
                        [
                            'type'      => 'image_url',
                            'image_url' => [
                                'url'    => "data:{$mimeType};base64,{$base64Image}",
                                'detail' => 'high',
                            ],
                        ],
                        [
                            'type' => 'text',
                            'text' => '이 처방전 이미지의 유형을 먼저 판단하고, 모든 텍스트를 정확히 추출하여 지정된 JSON 형식으로만 반환해주세요. 소모성재료 처방전인 경우 product_name과 다중 상병코드를 반드시 추출해주세요.',
                        ],
                    ],
                ],
            ],
        ];

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_TIMEOUT        => 60,
        ]);

        $response  = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            throw new \RuntimeException('OpenAI API 네트워크 오류: ' . $curlError);
        }

        if ($httpCode !== 200) {
            $errorData = json_decode($response, true);
            $errorMsg  = $errorData['error']['message'] ?? 'HTTP ' . $httpCode;
            throw new \RuntimeException('OpenAI API 오류: ' . $errorMsg);
        }

        $data    = json_decode($response, true);
        $content = $data['choices'][0]['message']['content'] ?? '';

        // 마크다운 코드블록 제거
        $content = preg_replace('/^```json\s*/im', '', $content);
        $content = preg_replace('/^```\s*$/im', '', $content);
        $content = trim($content);

        $parsed = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($parsed)) {
            Log::warning('OpenAI OCR JSON 파싱 실패, 정규식 폴백', [
                'raw_response' => $content,
            ]);
            return [
                'text'       => $content,
                'confidence' => 50.0,
                'parsed'     => [],
            ];
        }

        Log::info('OpenAI OCR 성공', [
            'type'         => $parsed['prescription_type'] ?? 'unknown',
            'patient_name' => $parsed['patient_name']  ?? 'null',
            'hospital'     => $parsed['hospital_name'] ?? 'null',
            'disease_code' => $parsed['disease_code']  ?? 'null',
            'product_name' => $parsed['product_name']  ?? 'null',
            'confidence'   => $parsed['confidence']    ?? 'null',
        ]);

        return [
            'text'       => $parsed['raw_text'] ?? $content,
            'confidence' => is_numeric($parsed['confidence'] ?? null) ? (float)$parsed['confidence'] : 70.0,
            'parsed'     => $parsed,
        ];
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
     * 폴백용 정규식 파싱 (OpenAI JSON 파싱 실패 시)
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

    private function ocrSystemPrompt(): string
    {
        return <<<'PROMPT'
당신은 한국 의료 처방전 및 의료기기 소모성재료 처방전 전문 OCR 분석 AI입니다.
이미지의 모든 텍스트를 정확히 읽고, 아래 JSON 형식으로만 응답하세요.
JSON 외 다른 내용(설명, 주석, 마크다운 코드블록 등)은 절대 포함하지 마세요.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
[STEP 1] 처방전 유형 판단
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
처방전 제목/헤더를 가장 먼저 확인하여 유형을 결정하세요.

● 소모성재료 처방전: 제목에 아래 키워드가 포함된 경우
  - "자가도뇨", "요실금", "욕창예방", "인공호흡기", "기침유발기", "이동식 산소발생기"
  - "소모성재료", "의료기기", "보조기기" 등
  → prescription_type = "소모성재료"
  → product_name: 제목에서 소모성재료 품목명 추출 (예: "자가도뇨", "욕창예방 매트리스")

● 일반 의약품 처방전: 약품명, 용량, 투여방법이 표에 있는 경우
  → prescription_type = "의약품"

● 한방 처방전: 한약재, 첩수, 탕약 등이 있는 경우
  → prescription_type = "한방"

● 기타: 위에 해당하지 않으면
  → prescription_type = "기타"

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
[STEP 2] 소모성재료 처방전 특별 추출 규칙
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
▶ 보장기관 정보
  - "보장기관명(기호)" 또는 "요양기관명" 항목 → hospital_name
  - 괄호 안 숫자 코드 → hospital_code

▶ 상병코드 (여러 개일 수 있음)
  - 상병코드 표에서 모든 코드를 쉼표로 연결 (예: "Q059, K319")

▶ 상병구분 체크박스 (□ 중 ✓ 또는 ■ 표시된 항목)
  - 신성상병, 후천성 취수성 상병, 후천성 질환 상병, 2차성 방광 기능이상 등
  → condition_type 에 체크된 항목명 기입

▶ 처방 수량 표
  - "1일 처방개수" → daily_count (integer)
  - "총 처방기간(일)" 또는 "총 처방기간" → total_days (integer)
  - "총계(개)" 또는 "총계" → total_count (integer)

▶ 담당의사 정보
  - "담당의사성명(면번호)" → doctor_name + license_no
  - "전문과목(전문의 자격번호)" → specialty + specialist_no

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
[STEP 3] 반환할 JSON 구조
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
{
  "prescription_type": "소모성재료",
  "raw_text": "이미지의 전체 텍스트 (모든 줄 포함, 줄바꿈은 \\n)",
  "registration_no": "등록번호 또는 null",
  "serial_no": "연번호 또는 null",
  "is_reissue": false,
  "patient_name": "환자 성명 또는 null",
  "resident_no": "주민등록번호 XXXXXX-XXXXXXX 형식 또는 null",
  "phone": "자택전화(지역번호 포함) 또는 null",
  "mobile": "환자 연락처 전화번호(휴대전화·전화·연락처 등 010/011/016 등으로 시작하는 번호 우선, 없으면 기재된 전화번호) 또는 null",
  "address": "주소 전체 또는 null",
  "department": "진료과목(전문과목) 또는 null",
  "disease_name": "상병명 전체 (여러 개면 쉼표 구분) 또는 null",
  "disease_code": "상병코드 (여러 개면 쉼표 구분, 예: Q059, K319) 또는 null",
  "condition_type": "상병구분 체크된 항목 또는 null",
  "daily_count": 6,
  "total_days": 90,
  "total_count": 540,
  "product_name": "소모성재료명 또는 처방 의약품명 또는 null",
  "hospital_name": "요양기관명 또는 보장기관명 또는 null",
  "hospital_code": "요양기관기호 또는 보장기관기호 또는 null",
  "doctor_name": "담당의사 성명 또는 null",
  "specialty": "전문과목 또는 null",
  "license_no": "면허번호 숫자만 또는 null",
  "specialist_no": "전문의자격번호 숫자만 또는 null",
  "issued_date": "발행일 YYYY-MM-DD 형식 또는 null",
  "usage_period": "사용기간 텍스트 또는 null",
  "confidence": 85.0
}

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
[STEP 4] 신뢰도(confidence) 채점 기준
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
① patient_name   판독 성공 → +20점
② hospital_name  판독 성공 → +20점
③ disease_code   판독 성공 → +20점
④ daily_count + total_days + total_count 모두 판독 → +20점
⑤ issued_date    판독 성공 → +20점
이미지 품질(흐림·기울기·반사 등) 감점 최대 -15점. 최종 confidence: 0~100 소수점 1자리.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
[공통 규칙]
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
- 날짜: 항상 YYYY-MM-DD 형식 (YYYY. MM. DD → YYYY-MM-DD 변환)
- 주민등록번호: XXXXXX-XXXXXXX 형식 (하이픈 포함, 공백 제거)
- 숫자 필드: 문자 제거 후 정수, 판독 불가 시 null
- 값 확인 불가 시 반드시 null (빈 문자열 "" 사용 금지)
- disease_code 여러 개: 이미지에 있는 그대로 모두 기입
- license_no, specialist_no: 숫자만 추출 (제 65644 호 → "65644")
PROMPT;
    }
}
