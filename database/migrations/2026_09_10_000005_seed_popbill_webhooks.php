<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 팝빌 웹훅 다섯을 웹훅 관리 표에 세운다 (2026-09-10 지시).
 *
 * 여태 팝빌 쪽 결과는 우리가 주기적으로 물어서 알았다 — 팩스 5분, 현금영수증 15분ㆍ
 * 1시간. 그 사이에 실패한 건은 늦게 드러난다. 저쪽이 알려 주면 그 자리에서 맞춘다.
 *
 * **파라미터 이름은 첫 알림을 보고 좁힌다.** 팝빌이 무엇을 어떤 이름으로 보내는지는
 * 갈래마다 다르고 문서마다 표기가 갈린다. 우리가 쓰는 것은 「어느 건인가」 하나뿐이라
 * (접수번호ㆍ문서번호) 그것만 적어 두고, 나머지는 로그에 쌓인 뒤에 채운다.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('webhooks')) {
            return;
        }

        if (DB::table('webhooks')->where('provider', 'popbill')->exists()) {
            return;
        }

        $이제 = now();
        $줄 = fn (array $v) => $v + [
            'provider' => 'popbill', 'direction' => 'inbound', 'http_method' => 'POST',
            'is_active' => false, 'created_at' => $이제, 'updated_at' => $이제,
        ];

        DB::table('webhooks')->insert([
            $줄([
                'name' => '세금계산서 상태 변경', 'event_code' => 'taxinvoice',
                'url' => '/popbill/webhook/taxinvoice', 'sort' => 110,
                'description' => '발행ㆍ국세청 전송 결과가 바뀌면 온다. 문서번호(mgtKey)로 그 한 건만 다시 읽는다.',
            ]),
            $줄([
                'name' => '현금영수증 상태 변경', 'event_code' => 'cashbill',
                'url' => '/popbill/webhook/cashbill', 'sort' => 120,
                'description' => '국세청 전송 결과가 바뀌면 온다. 문서번호(mgtKey)로 그 한 건만 다시 읽는다.',
            ]),
            $줄([
                'name' => '문자(SMS) 전송결과', 'event_code' => 'sms',
                'url' => '/popbill/webhook/sms', 'sort' => 130,
                'description' => '보낸 문자의 결과가 나오면 온다. 지금은 로그에만 적는다 — 받는 사람마다의 결과를 담을 칸이 아직 없다.',
            ]),
            $줄([
                'name' => '알림톡 전송결과', 'event_code' => 'kakao',
                'url' => '/popbill/webhook/kakao', 'sort' => 140,
                'description' => '보낸 알림톡의 결과가 나오면 온다. 지금은 로그에만 적는다.',
            ]),
            $줄([
                'name' => '팩스 전송결과', 'event_code' => 'fax',
                'url' => '/popbill/webhook/fax', 'sort' => 150,
                'description' => '팩스 전송이 끝나면 온다. 접수번호(receiptNum)로 우리 건을 찾아 팝빌에 다시 묻는다.',
            ]),
        ]);

        $칸 = [];
        $이름표 = fn (int $id, int $i, string $name, bool $req, string $desc) => [
            'webhook_id' => $id, 'position' => 'body', 'name' => $name, 'data_type' => 'string',
            'required' => $req, 'sample' => null, 'description' => $desc, 'sort' => $i * 10,
            'created_at' => $이제, 'updated_at' => $이제,
        ];

        foreach (DB::table('webhooks')->where('provider', 'popbill')->get() as $w) {
            $칸[] = $이름표($w->id, 1, 'corpNum', false, '팝빌 사업자번호 — 없으면 우리 설정값을 쓴다');

            if (in_array($w->event_code, ['taxinvoice', 'cashbill'], true)) {
                $칸[] = $이름표($w->id, 2, 'mgtKey', true, '문서번호 — 이것으로 그 한 건만 다시 읽는다');
                $칸[] = $이름표($w->id, 3, 'stateCode', false, '상태 코드 — 그대로 믿지 않고 팝빌에 다시 묻는다');
            } else {
                $칸[] = $이름표($w->id, 2, 'receiptNum', true, '접수번호 — 이것으로 우리 건을 찾는다');
                $칸[] = $이름표($w->id, 3, 'state', false, '전송 상태 — 그대로 믿지 않고 팝빌에 다시 묻는다');
            }
        }

        if ($칸) {
            DB::table('webhook_params')->insert($칸);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('webhooks')) {
            DB::table('webhooks')->where('provider', 'popbill')->delete();
        }
    }
};
