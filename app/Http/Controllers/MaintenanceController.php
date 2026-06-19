<?php
// app/Http/Controllers/MaintenanceController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class MaintenanceController extends Controller
{
    // ── URL → 블레이드 파일 매핑 ────────────────────────────────
    private function resolveViewPath(string $urlPath): ?string
    {
        $base = resource_path('views');

        // XAMPP 서브폴더 등 base path 제거 후 순수 경로만 추출
        $parsed   = parse_url($urlPath, PHP_URL_PATH) ?? '/';
        $basePath = request()->getBasePath(); // 예: '/ce-admin'
        if ($basePath && str_starts_with($parsed, $basePath)) {
            $parsed = substr($parsed, strlen($basePath));
        }
        $path = '/' . ltrim($parsed, '/');
        if ($path === '') $path = '/';

        $map = [
            '/'                     => 'dashboard/index',
            '/dashboard'            => 'dashboard/index',
            '/prescriptions'        => 'prescriptions/list',
            '/prescriptions/upload' => 'prescriptions/upload',
            '/orders'               => 'orders/index',
            '/patients'             => 'patients/index',
            '/settlement'           => 'settlement/index',
            '/invoice'              => 'invoice/index',
            '/nhis'                 => 'nhis/index',
        ];

        if (isset($map[$path])) {
            $file = $base . '/' . $map[$path] . '.blade.php';
            return file_exists($file) ? $file : null;
        }
        if (preg_match('#^/prescriptions/\d+$#', $path)) {
            $file = $base . '/prescriptions/order.blade.php';
            return file_exists($file) ? $file : null;
        }
        if (preg_match('#^/orders/.+$#', $path)) {
            $file = $base . '/orders/show.blade.php';
            return file_exists($file) ? $file : null;
        }
        if (preg_match('#^/patients/\d+$#', $path)) {
            $file = $base . '/patients/show.blade.php';
            return file_exists($file) ? $file : null;
        }

        return null;
    }

    // ── POST /maintenance/stream  (SSE 스트리밍) ────────────────
    public function stream(Request $request): Response
    {
        $prompt  = trim($request->input('prompt', ''));
        $urlPath = $request->input('url', '/');
        $apiKey  = config('services.anthropic.key');

        // 헤더 미리 설정 후 직접 출력
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('X-Accel-Buffering: no');
        header('Connection: keep-alive');

        while (ob_get_level() > 0) ob_end_flush();
        ob_implicit_flush(true);

        $output = fopen('php://output', 'wb');

        $send = function (array $data) use ($output) {
            fwrite($output, 'data: ' . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n");
            fflush($output);
        };

        // ── 기본 유효성 검사 ───────────────────────────────────
        if (empty($prompt)) {
            $send(['type' => 'error', 'message' => '프롬프트를 입력해주세요.']);
            fclose($output); exit;
        }
        if (empty($apiKey)) {
            $send(['type' => 'error', 'message' => '.env에 ANTHROPIC_API_KEY가 설정되지 않았습니다.']);
            fclose($output); exit;
        }

        // ── 파일 해석 ──────────────────────────────────────────
        $viewFile     = $this->resolveViewPath($urlPath);
        $fileContent  = '';
        $fileRelative = '(파일 매핑 없음: ' . $urlPath . ')';

        if ($viewFile) {
            $fileContent  = file_get_contents($viewFile);
            $fileRelative = str_replace(
                [base_path() . DIRECTORY_SEPARATOR, base_path() . '/'],
                '',
                str_replace('\\', '/', $viewFile)
            );
        }

        $send(['type' => 'status', 'message' => "📂 파일: {$fileRelative}"]);
        $send(['type' => 'status', 'message' => '🤖 Claude에게 요청 중...']);

        // ── 시스템 프롬프트 ────────────────────────────────────
        $systemPrompt = <<<'SYSPROMPT'
You are an expert Laravel Blade template editor.
The user will send you a blade file content and a modification request (in Korean).

CRITICAL OUTPUT FORMAT RULES — you MUST follow these exactly:
1. You MAY write a short explanation in Korean BEFORE the file content.
2. Then output the COMPLETE modified file content wrapped EXACTLY like this:
   <modified_file>
   ...full file content here...
   </modified_file>
3. The <modified_file> opening tag must be on its own line.
4. The </modified_file> closing tag must be on its own line.
5. Do NOT wrap the file content in markdown code fences (no ```) inside the tags.
6. Do NOT omit any part of the file — always return the full file.
7. If no file is provided, create a new one from scratch following the existing Laravel Blade conventions.
SYSPROMPT;

        $userMessage = "파일 경로: {$fileRelative}\n\n"
            . ($fileContent
                ? "파일 내용:\n{$fileContent}\n\n"
                : "파일 내용: (없음 — 새로 작성해주세요)\n\n")
            . "수정 요청: {$prompt}";

        // ── Claude API 호출 (스트리밍) ─────────────────────────
        $body = json_encode([
            'model'      => 'claude-3-5-sonnet-20241022',
            'max_tokens' => 8192,
            'stream'     => true,
            'system'     => $systemPrompt,
            'messages'   => [
                ['role' => 'user', 'content' => $userMessage],
            ],
        ]);

        // JSON 인코딩 실패 시 (잘못된 UTF-8 등) 조기 종료
        if ($body === false) {
            $send(['type' => 'error', 'message' => 'JSON 인코딩 실패: ' . json_last_error_msg()]);
            fclose($output); exit;
        }

        $curlHeaders = [
            'Content-Type: application/json',
            'x-api-key: ' . $apiKey,
            'anthropic-version: 2023-06-01',
        ];

        $fullText = '';
        $sseBuffer = '';   // 불완전한 SSE 행 버퍼
        $rawBuffer = '';   // 전체 원본 수신 버퍼 (에러 파싱용)

        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => $curlHeaders,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_WRITEFUNCTION  => function ($ch, $chunk) use ($send, &$fullText, &$sseBuffer, &$rawBuffer) {
                $rawBuffer .= $chunk;
                $sseBuffer .= $chunk;
                $lines      = explode("\n", $sseBuffer);
                $sseBuffer  = array_pop($lines); // 마지막 불완전 행 보류

                foreach ($lines as $line) {
                    $line = rtrim($line, "\r");
                    if (!str_starts_with($line, 'data: ')) continue;
                    $json = substr($line, 6);
                    if ($json === '' || $json === '[DONE]') continue;

                    $event = json_decode($json, true);
                    if (!$event) continue;

                    $type = $event['type'] ?? '';
                    if ($type === 'content_block_delta') {
                        $text = $event['delta']['text'] ?? '';
                        if ($text !== '') {
                            $fullText .= $text;
                            $send(['type' => 'token', 'text' => $text]);
                        }
                    } elseif ($type === 'error') {
                        $msg = $event['error']['message'] ?? json_encode($event);
                        $send(['type' => 'error', 'message' => "API 스트림 오류: {$msg}"]);
                    }
                }
                return strlen($chunk);
            },
        ]);

        curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // 에러 응답 파싱 (400 등은 SSE가 아닌 일반 JSON)
        if ($curlError) {
            $send(['type' => 'error', 'message' => "cURL 오류: {$curlError}"]);
            Log::error('[Maintenance] cURL error', ['error' => $curlError]);
            fclose($output); exit;
        }
        if ($httpCode >= 400) {
            $errData = json_decode($rawBuffer, true);
            $errMsg  = $errData['error']['message']
                ?? ($errData['error']['type'] ?? null)
                ?? $rawBuffer;
            Log::error('[Maintenance] Claude API 오류', [
                'http_code' => $httpCode,
                'body'      => $rawBuffer,
            ]);
            $send(['type' => 'error', 'message' => "API {$httpCode} 오류: {$errMsg}"]);
            fclose($output); exit;
        }

        Log::info('[Maintenance] Claude 응답 완료', [
            'http_code'    => $httpCode,
            'response_len' => strlen($fullText),
            'preview'      => mb_substr($fullText, 0, 200),
        ]);

        // ── 파일 추출 및 적용 ─────────────────────────────────
        $send(['type' => 'status', 'message' => '✏️ 파일 변경 사항 적용 중...']);

        if (preg_match('/<modified_file>\r?\n?(.*?)\r?\n?<\/modified_file>/s', $fullText, $m)) {
            $newContent = $m[1];

            if ($viewFile) {
                try {
                    $backupPath = $viewFile . '.bak.' . date('YmdHis');
                    copy($viewFile, $backupPath);
                    file_put_contents($viewFile, $newContent);
                    $send([
                        'type'    => 'done',
                        'message' => "✅ 파일이 성공적으로 수정되었습니다.\n📁 백업: " . basename($backupPath),
                        'applied' => true,
                    ]);
                } catch (\Throwable $e) {
                    $send(['type' => 'error', 'message' => '파일 쓰기 오류: ' . $e->getMessage()]);
                }
            } else {
                $send([
                    'type'    => 'done',
                    'message' => '⚠️ 파일 경로 미확인 — 파일을 직접 적용하지 않았습니다.',
                    'applied' => false,
                    'content' => $newContent,
                ]);
            }
        } else {
            // 태그를 못 찾은 경우 — 첫 300자 미리보기 전송
            $preview = mb_substr($fullText, 0, 300);
            $send([
                'type'    => 'done',
                'message' => "⚠️ <modified_file> 태그를 찾지 못했습니다.\n응답 미리보기: {$preview}",
                'applied' => false,
            ]);
        }

        fclose($output);
        exit;
    }
}
