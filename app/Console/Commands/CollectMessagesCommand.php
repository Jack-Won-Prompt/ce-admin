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
    protected $signature = 'messages:collect {--dry : 무엇이 등록될지 보기만 한다}';
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
                $채울것 = array_filter([
                    /* 원문이 비어 있으면 채운다 — 이 값이 없으면 화면이 고친 글을 못 찾는다 */
                    'original'  => $있나->original  ?: $것['body'],
                    'screen'    => $있나->screen    ?: implode(' · ', array_keys($것['screens'])),
                    'step'      => $있나->step      ?: $것['step'],
                    'variables' => $있나->variables ?: $것['variables'],
                ], fn ($v) => $v !== '' && $v !== null);

                if ($채울것) { $있나->update($채울것); }
                $그대로++;
                continue;
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
            if (! preg_match_all($정규, $글, $m)) { continue; }

            foreach ($m[1] as $본문) {
                /* 코드 조각이 섞인 글은 거른다 */
                if (str_contains($본문, '${') || str_contains($본문, '" +') || str_contains($본문, "' +")) {
                    continue;
                }

                $본문 = str_replace(["\'", '\n'], ["'", "\n"], $본문);

                /* 한글이 없는 글은 개발용이다 — 담당자가 고칠 말이 아니다 */
                if (! preg_match('/[가-힣]/u', $본문)) { continue; }

                $나온것[] = [$채널, $본문, ''];
            }
        }

        return $나온것;
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
