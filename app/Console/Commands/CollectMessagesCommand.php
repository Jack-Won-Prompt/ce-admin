<?php

namespace App\Console\Commands;

use App\Models\MessageTemplate;
use Illuminate\Console\Command;

/**
 * 화면에 뜨는 말ㆍ나가는 말을 긁어 메시지 유형 표에 등록한다 (2026-09-23 지시).
 *
 * 「SMS 로 전달되는 모든 알림과 토스트 알림을 화면명ㆍ단계ㆍ변수까지 등록해 고칠 수
 * 있게」 하라는 지시다. 토스트만 441곳이라 손으로 적을 수 없다 — 코드에서 읽는다.
 *
 * **이미 본문이 있는 행은 건드리지 않는다.** 틀 문구는 코드가 아니라 운영 DB 에만
 * 있고(담당자가 화면에서 고친 것이다), 코드에 남은 기본 문구는 그보다 옛 글이다.
 * 덮어쓰면 고쳐 둔 말이 되돌아간다.
 *
 * **본문이 빈 채로는 저장하지 않는다.** 2026-09-23 에 정규식이 깨져 null 을 그대로
 * 저장하는 바람에 열한 건의 본문이 한꺼번에 사라진 적이 있다.
 */
class CollectMessagesCommand extends Command
{
    protected $signature = 'messages:collect {--dry : 무엇이 등록될지 보기만 한다}
                                             {--refresh : 화면명ㆍ단계ㆍ변수를 다시 읽어 덮는다(본문은 그대로)}';
    protected $description = '화면의 토스트ㆍ팝업 문구를 메시지 유형 표에 등록한다';

    /** 파일 경로에서 화면 이름을 읽는다 */
    private const 화면이름 = [
        'prescriptions/order'   => '주문 등록',
        'prescriptions/list'    => '처방전 목록',
        'prescriptions/upload'  => '처방자료 업로드',
        'patients/index'        => '거래처 관리',
        'patients/show'         => '거래처 상세',
        'finance/index'         => 'Finance',
        'settlement/index'      => '정산ㆍ회계',
        'cashbill/index'        => '현금/카드영수증',
        'taxinvoice/index'      => '전자세금계산서',
        'order-returns'         => '교환ㆍ반품ㆍ취소',
        'repurchase/index'      => '재구매 관리',
        'messages/index'        => '메시지 관리',
        /* 좁은 것을 먼저 둔다 — str_contains 로 견주므로 먼저 걸리는 것이 이긴다.
           서명 판은 거래처가 로그인 없이 여는 화면이라 따로 가린다. */
        'delegation-signs/sign' => '위임장 서명(고객)',
        'pay/show'              => '결제(고객)',
        'nhis/assist'           => '공단 지원',
        'delegation-signs'      => '위임 서명',
        'webhooks/index'        => '웹훅 관리',
        /* 모바일 웹 — 앱과 같은 화면이라 이름 앞에 「모바일」을 붙여 가른다 */
        'mobile/prescriptions'  => '모바일 · 처방전 목록',
        'mobile/prescription'   => '모바일 · 처방전 상세',
        'mobile/upload'         => '모바일 · 처방자료 업로드',
        'mobile/chat-room'      => '모바일 · 채팅방',
        'mobile/chat'           => '모바일 · 채팅',
        'mobile/settings'       => '모바일 · 설정',
        'mobile/orders'         => '모바일 · 주문 목록',
        'mobile/notifications'  => '모바일 · 알림 이력',
        'mobile/notices'        => '모바일 · 공지사항',
        'mobile/notice'         => '모바일 · 공지 상세',
        'mobile/inquiry-create' => '모바일 · 문의 등록',
        'mobile/inquiries'      => '모바일 · 문의 목록',
        'mobile/inquiry'        => '모바일 · 문의 상세',
        'mobile/login'          => '모바일 · 로그인',
        'mobile/otp'            => '모바일 · SMS 인증',
        'layouts/mobile'        => '모바일 · 공통',
        'orders/show'           => '주문 상세',
        'orders/index'          => '주문 관리',
        'consent/sign'          => '전자서명(고객)',
        'consent/id_card'       => '신분증 제출(고객)',
        'fax/index'             => '팩스 발송',
        'admin/users'           => '사용자 관리',
        'permission-groups'     => '권한 그룹',
        'service-requests'      => '서비스 요청',
        'sample-orders'         => '샘플 주문',
        'shop-orders'           => '쇼핑몰 주문',
        'institutional-notices' => '기관 공지사항',
        'common-codes'          => '공통 코드',
        'inquiries'             => '문의 관리',
        'notices/index'         => '공지사항',
        'documents/index'       => '서류 보관함',
        'deposits/index'        => '입금 관리',
        'error-logs'            => '오류 기록',
        'masters/_billing'      => '청구처 관리',
        'masters/index'         => '기준정보 관리',
        'nhis/index'            => '공단 관리',
        'prescription-consents' => '처방 동의',
        'privacy-consents'      => '개인정보 동의',
        'privacy/layout'        => '개인정보 동의(고객)',
        'partials/counsel'      => '상담 창',
        'patients/_editor'      => '거래처 관리 — 고치는 창',
        'patients/_address'     => '거래처 관리 — 주소 창',
        'prescriptions/_viewer' => '처방자료 보기',
        'layouts/app'           => '공통(모든 화면)',
    ];

    public function handle(): int
    {
        $볼것만 = (bool) $this->option('dry');
        $모은것 = [];

        foreach ($this->블레이드파일들() as $경로) {
            $글 = @file_get_contents($경로);
            if ($글 === false) { continue; }

            $화면 = $this->화면($경로);

            foreach ($this->문구뽑기($글) as [$채널, $본문, $단계]) {
                /* 같은 말이 여러 화면에 있으면 한 줄로 모은다 — 고치는 자리가
                   여럿이면 어느 것이 실제로 쓰이는지 알 수 없다. */
                $열쇠 = $채널 . '|' . $본문;

                if (isset($모은것[$열쇠])) {
                    $모은것[$열쇠]['screens'][$화면] = true;
                    continue;
                }

                $모은것[$열쇠] = [
                    'channel'   => $채널,
                    'body'      => $본문,
                    'screens'   => [$화면 => true],
                    /* 단계는 우리말 동작으로, 코드 이름은 설명 칸으로 (2026-09-23 지시) */
                    'step'      => $this->단계말($단계),
                    'desc'      => $단계 !== '' ? '화면 코드: ' . $단계 : '',
                    'variables' => $this->변수들($본문),
                ];
            }
        }

        $새것 = 0; $그대로 = 0; $건너뜀 = 0;

        foreach ($모은것 as $것) {
            /* 본문이 비면 등록하지 않는다 — 빈 줄은 고칠 것도 없고, 실수로 빈 값이
               저장되는 길을 아예 두지 않는다. */
            if (trim($것['body']) === '') { $건너뜀++; continue; }

            $코드 = $this->코드($것['channel'], $것['body']);
            $있나 = MessageTemplate::where('channel', $것['channel'])->where('code', $코드)->first();

            if ($있나) {
                /* **본문은 건드리지 않는다.** 화면명ㆍ단계ㆍ변수가 비어 있을 때만 채운다 —
                   담당자가 고쳐 둔 말을 코드의 옛 글로 되돌리지 않기 위해서다. */
                /* --refresh 는 화면명ㆍ단계ㆍ변수만 다시 읽는다. 화면 이름을
                   고쳐 적은 뒤 이미 등록된 줄에도 그 이름이 서게 하는 자리다.
                   **본문과 원문은 여기서도 건드리지 않는다.** */
                $덮기 = (bool) $this->option('refresh');

                $채울것 = array_filter([
                    /* 원문이 비어 있으면 채운다 — 이 값이 없으면 화면이 고친 글을 못 찾는다 */
                    'original'    => $있나->original ?: $것['body'],
                    'screen'      => $덮기 ? implode(' · ', array_keys($것['screens']))
                                           : ($있나->screen ?: implode(' · ', array_keys($것['screens']))),
                    'step'        => $덮기 ? $것['step']      : ($있나->step      ?: $것['step']),
                    'description' => $덮기 ? $것['desc']      : ($있나->description ?: $것['desc']),
                    'variables'   => $덮기 ? $것['variables'] : ($있나->variables ?: $것['variables']),
                ], fn ($v) => $v !== '' && $v !== null);

                /* --refresh 로 동작을 읽어 내지 못한 자리는 **비운다**.
                   옛 코드 이름이 단계 칸에 남아 있으면 안 고친 것과 같다. */
                if ($덮기 && $것['step'] === '') { $있나->forceFill(['step' => null])->save(); }

                if ($채울것) { $있나->update($채울것); }
                $그대로++;
                continue;
            }

            if ($볼것만) {
                $this->line('  새로: [' . $것['channel'] . '] ' . str_replace(PHP_EOL, ' / ', $것['body']));
            }

            if (! $볼것만) {
                MessageTemplate::create([
                    'channel'    => $것['channel'],
                    'code'       => $코드,
                    'label'      => mb_substr(trim(preg_replace('/\s+/u', ' ', $것['body'])), 0, 40),
                    'screen'      => implode(' · ', array_keys($것['screens'])),
                    'step'        => $것['step'],
                    'description' => $것['desc'],
                    'body'       => $것['body'],
                    /* 코드에 적힌 글 — 화면이 이 값을 열쇠로 고친 글을 찾는다.
                       body 는 담당자가 고치면 달라지지만 original 은 그대로다. */
                    'original'   => $것['body'],
                    'variables'  => $것['variables'],
                    'is_active'  => true,
                    'sort_order' => 0,
                ]);
            }
            $새것++;
        }

        $this->info(sprintf('%s새로 등록 %d건 · 이미 있던 것 %d건 · 본문이 비어 건너뜀 %d건',
            $볼것만 ? '[보기만] ' : '', $새것, $그대로, $건너뜀));

        return self::SUCCESS;
    }

    /** 화면 글이 들어 있는 블레이드를 훑는다 — 부분(partials)도 함께 본다 */
    private function 블레이드파일들(): array
    {
        $목록 = [];
        $뿌리 = resource_path('views');

        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($뿌리));
        foreach ($it as $f) {
            if ($f->isFile() && str_ends_with($f->getFilename(), '.blade.php')) {
                $목록[] = $f->getPathname();
            }
        }

        return $목록;
    }

    private function 화면(string $경로): string
    {
        $짧게 = str_replace(DIRECTORY_SEPARATOR, '/', $경로);
        $짧게 = substr($짧게, strpos($짧게, 'views/') + 6);
        $짧게 = str_replace('.blade.php', '', $짧게);

        foreach (self::화면이름 as $조각 => $이름) {
            if (str_contains($짧게, $조각)) { return $이름; }
        }

        return $짧게;
    }

    /**
     * 화면 글을 뽑는다.
     *
     *   showToast('…')            토스트
     *   ceAlert('…') · ceConfirm  팝업
     *
     * 변수가 섞인 글(`${…}` 나 따옴표를 잇는 글)은 뽑지 않는다 — 코드 조각이 그대로
     * 들어와 고칠 수 없는 줄이 된다. 그런 자리는 손으로 등록한다.
     *
     * @return array<int, array{0:string,1:string,2:string}> [채널, 본문, 단계]
     */
    private function 문구뽑기(string $글): array
    {
        $나온것 = [];

        /* 글이 첫 인자인 것과, 판(form·단추)을 먼저 받는 도우미를 함께 본다.
           ceConfirmClick(this, '…')ㆍceConfirmSubmit(this, '…') 은 인라인
           onclickㆍonsubmit 에서 쓰는 꼴이라 글이 두 번째에 온다 (2026-09-23). */
        $잣대 = [
            ['toast', "/showToast\\(\\s*'((?:[^'\\\\]|\\\\.){4,300})'/u"],
            ['popup', "/ce(?:Alert|Confirm)\\(\\s*'((?:[^'\\\\]|\\\\.){4,300})'/u"],
            ['popup', "/ceConfirm(?:Click|Submit)\\(\\s*[^,]{1,60},\\s*'((?:[^'\\\\]|\\\\.){4,300})'/u"],
            /* 모바일 웹(H5)의 알림 — 앱의 SnackBar 자리 (2026-09-25 지시) */
            ['toast', "/mTell\\(\\s*'((?:[^'\\\\]|\\\\.){4,300})'/u"],
        ];

        foreach ($잣대 as [$채널, $정규]) {
            if (! preg_match_all($정규, $글, $m, PREG_OFFSET_CAPTURE)) { continue; }

            foreach ($m[1] as [$본문, $자리]) {
                /* 코드 조각이 섞인 글은 거른다 */
                if (str_contains($본문, '${') || str_contains($본문, '" +') || str_contains($본문, "' +")) {
                    continue;
                }

                $본문 = str_replace(["\\'", '\\n'], ["'", chr(10)], $본문);

                /* 줄바꿈은 **chr(10) 하나**로 담는다. 두 가지로 데었다 (2026-09-23).

                     "\\n"   역슬래시와 n 두 글자가 그대로 들어간다
                     PHP_EOL  윈도에서는 CRLF 라, 리눅스에서 담긴 줄과 글이 달라진다

                   본문이 한 글자라도 다르면 코드(sha1)가 달라져 같은 말이 두 줄로
                   선다. 화면이 띄우는 글과도 달라 고친 말이 뜨지 않는다. */

                /* 한글이 없는 글은 개발용이다 — 담당자가 고칠 말이 아니다 */
                if (! preg_match('/[가-힣]/u', $본문)) { continue; }

                $나온것[] = [$채널, $본문, $this->단계($글, $자리)];
            }
        }

        return $나온것;
    }

    /**
     * 이 말이 어느 단계에서 뜨는가 (2026-09-23 지시).
     *
     * 「전송하는 단계를 모두 등록」하라는 지시다. 화면 글은 함수 안에서 뜨므로,
     * 글이 적힌 자리에서 위로 올라가며 가장 가까운 함수 선언을 찾는다. 그 이름이
     * 단추 이름과 이어져 있어(예: ptCounsel → 상담하기) 담당자가 어느 자리인지 안다.
     *
     * 함수 밖(화면을 처음 그릴 때 바로 뜨는 글)이면 빈 값이다 — 없는 단계를
     * 지어내지 않는다.
     */
    private function 단계(string $글, int $자리): string
    {
        $앞 = substr($글, 0, $자리);

        /* function 이름( · const 이름 = ( · 이름: function( · window.이름 = function( */
        $잣대 = '/(?:function\\s+([A-Za-z_$가-힣][\\w$가-힣]*)|(?:const|let|var)\\s+([A-Za-z_$가-힣][\\w$가-힣]*)\\s*=\\s*(?:async\\s*)?\\(|([A-Za-z_$가-힣][\\w$가-힣]*)\\s*:\\s*(?:async\\s*)?function)/u';

        if (! preg_match_all($잣대, $앞, $m)) { return ''; }

        /* 가장 가까운 것 — 뒤에서부터 이름이 있는 것을 고른다 */
        for ($i = count($m[0]) - 1; $i >= 0; $i--) {
            $이름 = $m[1][$i] ?: ($m[2][$i] ?: $m[3][$i]);
            if ($이름 !== '') { return $이름 . '()'; }
        }

        return '';
    }

    /**
     * 함수 이름에서 **무엇을 하는 자리인지**를 우리말로 읽어 낸다 (2026-09-23 지시).
     *
     * 단계 칸에 `saveOrderTab()` 처럼 코드 이름이 그대로 섰다. 담당자가 보는
     * 자리에 코드 이름을 두지 않는다 — 대신 이름에 담긴 **동작**만 읽어 적는다.
     *
     * 지어내지 않는다. 읽어 낼 동작이 없으면 빈 값을 돌려주고, 코드 이름은
     * 설명 칸(description)으로 옮겨 자취를 남긴다.
     */
    private const 동작 = [
        'resend' => '다시 보낼 때',   'regenerate' => '다시 만들 때',
        'send' => '보낼 때',          'submit' => '제출할 때',
        'save' => '저장할 때',        'delete' => '삭제할 때',
        'remove' => '삭제할 때',      'cancel' => '취소할 때',
        'issue' => '발행할 때',       'copy' => '복사할 때',
        'download' => '내려받을 때',
        'sync' => '동기화할 때',      'create' => '등록할 때',
        'add' => '추가할 때',         'load' => '불러올 때',
        'open' => '열 때',            'select' => '선택할 때',
        'pick' => '선택할 때',        'picked' => '선택할 때',
        'find' => '찾을 때',          'search' => '찾을 때',
        'update' => '수정할 때',      'edit' => '수정할 때',
        'confirm' => '확인할 때',     'check' => '확인할 때',
        'approve' => '승인할 때',     'reset' => '되돌릴 때',
        'render' => '화면에 그릴 때', 'change' => '바꿀 때',
        'toggle' => '바꿀 때',        'request' => '요청할 때',
        'execute' => '실행할 때',     'print' => '인쇄할 때',
        'calc' => '계산할 때',        'gate' => '저장 전에 검사할 때',
        'deposit' => '입금 처리할 때','show' => '볼 때',
        'revoke' => '취소할 때',      'invite' => '초대할 때',
        'start' => '시작할 때',       'prev' => '앞 건으로 옮길 때',
        'next' => '뒤 건으로 옮길 때',
        /* 우리말로 지은 함수 — 뜻이 또렷한 것만 적는다 */
        '닫기' => '닫을 때',          '적기' => '입력할 때',
        '알림' => '알릴 때',          '동의확인' => '동의를 확인할 때',
        '메모확인' => '메모를 확인할 때',
        '미리보기' => '미리 볼 때',
        '빈건지우기' => '빈 건을 삭제할 때',
        '상세탭으로' => '상세 탭으로 넘어갈 때',
        '결제상태알림' => '결제 상태를 알릴 때',
        '확인하고한번만' => '확인하고 실행할 때',
        '전화번호겹침확인' => '전화번호가 겹치는지 볼 때',
        /* 이름만으로는 안 보이지만 그 자리에서 뜨는 말이 또렷한 것 */
        '까닭지움' => '업로드할 때',  '같은건' => '담당자를 배정할 때',
        '해당' => '주소를 불러올 때', 'q원문' => '찾을 때',
    ];

    private function 단계말(string $함수): string
    {
        $이름 = rtrim($함수, '()');
        if ($이름 === '') { return ''; }

        /* 긴 낱말부터 견준다 — send 가 resend 를 가로채지 않게 */
        $낱말들 = array_keys(self::동작);
        usort($낱말들, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));

        $작은것 = mb_strtolower($이름);

        foreach ($낱말들 as $낱말) {
            if (str_contains($작은것, mb_strtolower($낱말))) {
                return self::동작[$낱말];
            }
        }

        return '';
    }

    /** 본문으로 코드를 만든다 — 같은 말은 늘 같은 코드가 된다 */
    private function 코드(string $채널, string $본문): string
    {
        return $채널 . '_' . substr(sha1($본문), 0, 12);
    }

    /** 이 글이 쓰는 변수 — #{…} 꼴만 센다 */
    private function 변수들(string $본문): string
    {
        preg_match_all('/#\{[^}]+\}/u', $본문, $m);

        return implode(', ', array_unique($m[0] ?? []));
    }
}
