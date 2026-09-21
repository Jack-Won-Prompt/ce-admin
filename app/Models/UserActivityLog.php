<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserActivityLog extends Model
{
    protected $fillable = [
        'user_id', 'type', 'menu_name', 'route_name', 'url', 'ip_address', 'user_agent',
        // 감사 확장(P0-7) — 마스킹 해제·다운로드 등 '무엇을 왜' 했는지
        'action', 'target_type', 'target_id', 'record_count',
        'reason_code', 'reason_text', 'retention_until',
    ];

    protected $casts = [
        'retention_until' => 'date',
    ];

    // 라우트명 → 한국어 메뉴명 매핑
    public const MENU_NAMES = [
        'dashboard'                => '대시보드',
        'dashboard.index'          => '대시보드',
        'patients.index'           => '환자관리',
        'patients.show'            => '환자 상세',
        'prescriptions.upload'     => '처방전 업로드',
        'prescriptions.index'      => '처방전 목록',
        'prescriptions.show'       => '처방전 상세',
        'repurchase.index'         => '재구매 관리',
        'repurchase.day'           => '재구매 관리',
        'orders.index'             => '주문관리',
        'orders.show'              => '주문 상세',
        'nhis.index'               => '청구 관리',
        'settlement.index'         => '정산/회계',
        'dispatch.index'           => '발송/발행 내역',
        'notices.index'            => '공지사항',
        'notices.show'             => '공지사항 상세',
        'notices.create'           => '공지사항 작성',
        'inquiries.index'          => '문의하기',
        'inquiries.show'           => '문의 상세',
        'user-logs.index'          => '사용자 로그',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function menuName(string $routeName): string
    {
        return self::MENU_NAMES[$routeName] ?? $routeName;
    }

    /** url 칸의 길이 — 표와 같은 값을 코드가 쥔다 */
    public const URL_MAX = 300;

    /** user_agent 칸의 길이 */
    public const AGENT_MAX = 300;

    /**
     * 이력에 남길 주소 — **쿼리스트링을 떼고 경로만** 남긴다.
     *
     * 두 가지를 한꺼번에 막는다.
     *
     * ① 길이. SSO 콜백 주소는 `?code=…` 가 붙어 1,800자를 넘는데 url 칸은 300자다.
     *    여태 그 줄은 `Data too long` 으로 저장이 죽었고, 기록하는 자리가 모두 오류를
     *    삼키게 되어 있어 **로그인은 되고 이력만 조용히 사라졌다**(2026-09-21).
     *
     * ② 비밀. 그 주소에는 OAuth 인가 코드와 state 가 통째로 들어 있다. 길이를 늘려
     *    담으면 자격증명 조각이 이력 표에 평문으로 쌓인다. 잘라 넣어도 앞부분은 남는다.
     *    이력이 알고 싶은 것은 「어느 화면에 왔는가」이지 「무슨 값을 들고 왔는가」가
     *    아니므로, 물음표 뒤는 아예 버린다.
     */
    public static function safeUrl(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        $물음표 = strpos($url, '?');

        if ($물음표 !== false) {
            $url = substr($url, 0, $물음표);
        }

        return mb_substr($url, 0, self::URL_MAX);
    }

    /** 브라우저 문자열 — 칸 길이에 맞춰 자른다 */
    public static function safeAgent(?string $agent): ?string
    {
        $agent = trim((string) $agent);

        return $agent === '' ? null : mb_substr($agent, 0, self::AGENT_MAX);
    }
}
