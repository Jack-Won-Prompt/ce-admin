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
        'delegation-signs'      => '위임 서명',
        'webhooks/index'        => '웹훅 관리',
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
        'nhis/assist'           => '공단 지원',
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
                    'step'      => $단계,
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
                    'original'  => $있나->original ?: $것['body'],
                    'screen'    => $덮기 ? implode(' · ', array_keys($것['screens']))
                                         : ($있나->screen ?: implode(' · ', array_keys($것['screens']))),
                    'step'      => $덮기 ? $것['step']      : ($있나->step      ?: $것['step']),
                    'variables' => $덮기 ? $것['variables'] : ($있나->variables ?: $것['variables']),
                ], fn ($v) => $v !== '' && $v !== null);

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
                    'screen'     => implode(' · ', array_keys($것['screens'])),
                    'step'       => $것['step'],
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

        $잣대 = [
            'toast' => "/showToast\\(\\s*'((?:[^'\\\\]|\\\\.){4,300})'/u",
            'popup' => "/ce(?:Alert|Confirm)\\(\\s*'((?:[^'\\\\]|\\\\.){4,300})'/u",
        ];

        foreach ($잣대 as $채널 => $정규) {
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
