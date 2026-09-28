<?php

namespace App\Console\Commands;

use App\Models\Patient;
use App\Models\PatientDelegationSign;
use App\Support\ResidentNo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * 운영 고객 정보를 거래처 관리로 옮긴다 (2026-09-29 지시).
 *
 * 원천은 `ww_customers` — 위드웍스 운영 DB 에서 옮겨 담아 둔 자리다. **그 표는 읽기만
 * 한다.** 고치지도 지우지도 않는다(운영 데이터 메뉴의 자리).
 *
 * 옮길 것을 가르는 잣대는 이름의 `(E)` 접두사다. 위드웍스에서 환자 계정은 모두 그렇게
 * 서고, 대리점ㆍ기관은 「길의료기」ㆍ「소매(End User-…)」처럼 선다. 12,829명이 환자다.
 *
 * **다시 돌려도 덧쓰기다.** ww_account_id 에 유일 색인이 있어 같은 사람이 두 줄로 서지
 * 않는다. 시험을 다시 하려면 test-data:purge 로 묶음째 지우고 다시 돌리면 된다.
 *
 * 먼저 세어 보이고 그 다음에 옮긴다 — --force 가 없으면 아무것도 쓰지 않는다.
 */
class MigratePatientsFromWithworksCommand extends Command
{
    protected $signature = 'patients:migrate-from-ww
                            {--force : 실제로 옮긴다. 없으면 세어 보이기만 한다}
                            {--batch= : 묶음 이름. 비우면 오늘 날짜로 짓는다}
                            {--limit= : 몇 명만 시험 삼아}
                            {--with-signs : 위임장 서명도 거래처로 옮겨 담는다}';

    protected $description = '운영 고객 정보(ww_customers)를 거래처 관리로 옮긴다';

    /**
     * 읽기만 하는 표 — 이 명령은 여기에 한 칸도 쓰지 않는다.
     *
     * delegation_signs 도 여기 있다. 서명을 거래처로 옮기면서 그 표에 거래처 번호를
     * 적어 두면 편하지만, 그것도 운영 데이터를 고치는 일이다(2026-09-29 지시).
     * 옮겨 담은 사본(patient_delegation_signs)이 양쪽 열쇠를 들고 있어 그럴 까닭도 없다.
     */
    private const 읽기만 = [
        'ww_customers', 'ww_customer_addresses', 'ww_prescription_infos', 'delegation_signs',
    ];

    public function handle(): int
    {
        $정말 = (bool) $this->option('force');
        $묶음 = $this->option('batch') ?: ('ww-' . now()->format('Ymd-His'));

        $this->line('');
        $this->info('══ 운영 고객 → 거래처 관리 ' . ($정말 ? '(실제로 옮깁니다)' : '(세어 보이기만 합니다)') . ' ══');
        $this->line('  묶음 : ' . $묶음);
        $this->line('  원천 : ww_customers (읽기만 합니다 — 고치지도 지우지도 않습니다)');
        $this->line('');

        $질의 = DB::table('ww_customers')
            ->whereRaw("account_name LIKE '(E)%'")
            ->whereNull('deleted_at')
            ->orderBy('ww_id');

        if ($한도 = $this->option('limit')) {
            $질의->limit((int) $한도);
        }

        $셈 = ['모두' => 0, '새로' => 0, '덧씀' => 0, '주민번호' => 0, '생년월일' => 0,
               '성별' => 0, '주소' => 0, '전화이상' => 0, '이름겹침' => 0, '주민겹침' => 0];

        /* 겹치는 사람을 미리 센다 — 한 사람이 위드웍스에 두 계정으로 있는 일이 있다.
           이름 52명ㆍ주민번호 106명(2026-09-29 확인). 옮기기 자체는 막지 않는다 —
           ww_account_id 가 다르므로 두 줄로 서고, 사람이 보고 합친다. */
        $이름겹침 = DB::table('ww_customers')->whereRaw("account_name LIKE '(E)%'")
            ->whereNull('deleted_at')
            ->selectRaw('account_name')->groupBy('account_name')
            ->havingRaw('COUNT(*) > 1')->pluck('account_name')->flip();

        $주민겹침 = DB::table('ww_customers')->whereRaw("account_name LIKE '(E)%'")
            ->whereNull('deleted_at')
            ->whereRaw("resident_no REGEXP '^[0-9]{6}-?[0-9]{7}$'")
            ->selectRaw('resident_no')->groupBy('resident_no')
            ->havingRaw('COUNT(*) > 1')->pluck('resident_no')->flip();

        $보기 = [];

        /* 이미 옮겨 둔 거래처를 **한 번에** 읽어 둔다.

           줄마다 물으면 12,829번 오간다 — 서버가 멀면 그것만으로 몇 분이 걸려
           옮기는 일이 끝났는지 알 수 없다. 한 번에 읽어 손에 들고 견준다. */
        $이미 = DB::table('patients')->whereNotNull('ww_account_id')
            ->pluck('id', 'ww_account_id')->all();

        $질의->chunk(500, function ($줄들) use (&$셈, &$보기, &$이미, $정말, $묶음, $이름겹침, $주민겹침) {
            foreach ($줄들 as $w) {
                $셈['모두']++;

                $이름 = Patient::bare($w->account_name);          // (E) 를 뗀다
                $번호 = $this->전화($w->phone_1);
                $주소 = $this->주소($w->address_id);

                if (isset($이름겹침[$w->account_name])) { $셈['이름겹침']++; }
                if ($w->resident_no && isset($주민겹침[$w->resident_no])) { $셈['주민겹침']++; }
                if ($w->phone_1 && ! $번호) { $셈['전화이상']++; }
                if ($주소) { $셈['주소']++; }

                /* 주민번호도 **여기서** 센다. 담는 자리에서 세면 세어 보이기만 할 때
                   0 으로 나와, 몇 명의 주민번호가 옮겨지는지 미리 알 수 없었다. */
                $주민번호있나 = $w->resident_no && preg_match('/^\d{6}-?\d{7}$/', $w->resident_no);
                if ($주민번호있나) { $셈['주민번호']++; }

                /* 생년월일과 성별은 **가린 주민번호에서 읽는다** — 복호화하지 않는다.
                   뒷자리 첫 숫자가 세기와 성별을 다 말해 주기 때문이다.

                   비워 두면 나이는 보이는데(그것도 주민번호에서 세므로) 생년월일 칸이
                   빈다. 무엇보다 「생년」으로 찾는 거르개가 birth_date 를 보므로,
                   채우지 않으면 12,604명 가운데 한 명도 찾히지 않는다. */
                $생년월일 = null;
                $성별     = null;

                if ($주민번호있나) {
                    $가린것   = ResidentNo::mask($w->resident_no);
                    $생년월일 = ResidentNo::birthDateFromMasked($가린것)?->toDateString();
                    $성별     = ResidentNo::genderFromMasked($가린것);

                    if ($생년월일) { $셈['생년월일']++; }
                    if ($성별)     { $셈['성별']++; }
                }

                $있는번호 = $이미[$w->ww_id] ?? null;
                $있는번호 ? $셈['덧씀']++ : $셈['새로']++;

                if (count($보기) < 5) {
                    $보기[] = [$w->ww_id, $이름, $번호 ?: '-',
                        $w->resident_no ? '있음' : '-', $주소 ? mb_substr($주소['address'], 0, 20) : '-',
                        $있는번호 ? '덧씀' : '새로'];
                }

                if (! $정말) {
                    continue;
                }

                $값 = [
                    'name'            => $이름,
                    'mobile'          => $번호,
                    /* 사업부는 IC 다 — 옮기는 것이 모두 (E) 계정, 곧 카테터 환자다.
                       비워 두면 화면의 「사업부」 칸이 빈 채로 서고, 더 나쁘게는
                       저장할 때 이름 앞에 (E) 가 붙지 않는다. 위드웍스는 개인 거래처를
                       (E) 로 적으므로 그것이 빠지면 두 시스템이 같은 사람을 다른
                       이름으로 읽는다(Patient::nameWithCareTag). */
                    'care_type'       => 'IC',
                    'ww_account_code' => $w->account_code,
                    'data_origin'     => 'migration',
                    'data_batch'      => $묶음,
                    'updated_at'      => now(),
                ] + array_filter([
                    'birth_date' => $생년월일,
                    'gender'     => $성별,
                ]) + ($주소 ?: []);

                $거래처 = $있는번호
                    ? Patient::withTrashed()->find($있는번호)
                    : new Patient();

                $거래처->forceFill($값 + ['ww_account_id' => $w->ww_id]);

                /* 주민번호는 **평문으로 넣지 않는다.** resident_no 에 값을 주면 모델의
                   씌우개(setResidentNoAttribute)가 암호문ㆍ해시ㆍ별표를 갈라 담고
                   보유 근거(rrn_purpose)까지 적는다. 그 자리를 지나치면 암호화가 빠진다. */
                if ($주민번호있나) {
                    $거래처->resident_no = $w->resident_no;
                }

                $거래처->save();

                /* 손에 든 지도도 함께 채운다 — 같은 판에 서명을 옮길 때 이것을 본다 */
                $이미[$w->ww_id] = $거래처->id;
            }
        });

        $this->table(['ww_id', '이름', '휴대폰', '주민번호', '주소', '어떻게'], $보기);
        $this->line('');
        $this->table(['무엇', '몇'], collect($셈)->map(fn ($v, $k) => [$k, number_format($v)])->values()->all());

        if ($this->option('with-signs')) {
            $this->서명이관($정말, $묶음);
        }

        $this->line('');

        if (! $정말) {
            $this->warn('  세어 보이기만 했습니다. 실제로 옮기려면 --force 를 적어 주십시오.');
        } else {
            /* 되돌리는 길을 **정확히** 적는다. 앞서는 --batch 만 적었는데, 지우는
               명령은 기본으로 test 딱지만 보므로 그대로 쳐도 「지울 것이 없습니다」가
               나왔다 — 옮겨 온 줄은 migration 딱지다. 되돌릴 수 있다고 믿고 옮겼다가
               되돌리지 못하는 것이 가장 나쁘다. */
            $this->info('  옮겼습니다. 되돌리려면 —');
            $this->line('    php artisan test-data:purge --origin=migration --batch=' . $묶음 . ' --force');
        }

        $this->line('  ' . implode(' · ', self::읽기만) . ' 는 읽기만 했습니다.');
        $this->line('');

        return self::SUCCESS;
    }

    /**
     * 위임장 서명을 거래처로 옮겨 담는다 (2026-09-29 지시).
     *
     * **원본 표는 읽기만 한다.** delegation_signs 는 운영 데이터 메뉴의 자리다 —
     * 거래처 번호를 그쪽 칸에 적어 넣는 것도 그 표를 고치는 일이라 하지 않는다.
     * 읽어다 patient_delegation_signs 에 담는다. 서명 그림도 함께 옮긴다.
     *
     * **서명까지 받은 줄만 옮긴다** (status = signed · 193줄). signed_at 만 보고
     * 고르면 194줄이 되는데, 그 한 줄은 다시 보내어 「서명 대기」로 돌아간 건이다 —
     * 원본 화면도 그 줄의 지난 서명은 내주지 않는다(DelegationSign::서명그림).
     * 다시 받아야 하는 서명을 받아 둔 것처럼 거래처에 옮겨 놓아서는 안 된다.
     *
     * **짝짓는 잣대는 이름과 생년월일이다** (2026-09-29 지시).
     *
     *   하나  후보는 **환자((E)) 계정만** 둔다. 그러지 않으면 「(E)박민서」와
     *         「박민서」 두 계정이 함께 걸려 여섯 줄이 갈렸다.
     *   둘    이름은 (E) 를 떼고 견준다. 직접 발송한 줄은 담당자가 (E) 없이 적어
     *         두어 그대로 견주면 짝을 못 찾았다(「김선미」).
     *   셋    생년월일ㆍ성별은 **가린 주민등록번호**로 견준다. 복호화하지 않는다 —
     *         가린 값이 YYMMDD-S****** 라 그 둘이 이미 들어 있다.
     *   넷    어긋나면 **잇지 않는다.** 이름이 같아도 생년월일이 다르면 남이다.
     *
     * 전화번호는 잣대로 쓰지 않는다. 짝지어진 192줄 가운데 35줄이 어긋난다 —
     * 번호가 바뀌었거나 보호자 번호가 적혀 있다(2026-09-29 확인).
     */
    private function 서명이관(bool $정말, string $묶음): void
    {
        $this->line('');
        $this->info('  ── 위임장 서명 이관 ──');

        /* 후보 — 환자((E)) 계정만, (E) 뗀 이름으로 묶어 둔다 */
        $이름별 = [];

        DB::table('ww_customers')->whereRaw("account_name LIKE '(E)%'")->whereNull('deleted_at')
            ->select(['ww_id', 'account_name', 'resident_no'])
            ->orderBy('ww_id')
            ->chunk(2000, function ($줄들) use (&$이름별) {
                foreach ($줄들 as $c) {
                    $이름별[Patient::bare($c->account_name)][] = $c;
                }
            });

        /* 운영 고객 번호 → 거래처 번호. 한 번에 읽어 둔다 */
        $거래처지도 = DB::table('patients')->whereNotNull('ww_account_id')
            ->pluck('id', 'ww_account_id')->all();

        $셈 = ['서명완료' => 0, '이름생년월일' => 0, '이름만' => 0, '새로' => 0, '덧씀' => 0,
               '거래처없음' => 0, '짝없음' => 0, '생년월일어긋남' => 0, '여럿' => 0, '그림없음' => 0];
        $못한것 = [];

        DB::table('delegation_signs')->where('status', 'signed')->orderBy('id')
            ->chunk(200, function ($줄들) use (&$셈, &$못한것, $정말, $묶음, $이름별, $거래처지도) {
                foreach ($줄들 as $d) {
                    $셈['서명완료']++;

                    $후보 = $이름별[Patient::bare($d->customer_name)] ?? [];

                    if ($후보 === []) {
                        $셈['짝없음']++;
                        $못한것[] = [$d->id, $d->customer_name, '운영 고객에 그 이름이 없음'];
                        continue;
                    }

                    /* 생년월일ㆍ성별로 좁힌다. 원본에 주민등록번호가 없으면 견줄 수
                       없으니 이름만으로 잇고, 그 사실을 줄에 적어 둔다. */
                    $어떻게 = 'name';

                    if ($d->resident_no_masked) {
                        $좁힘 = array_values(array_filter(
                            $후보,
                            fn ($c) => ResidentNo::mask($c->resident_no) === $d->resident_no_masked
                        ));

                        if ($좁힘 === []) {
                            $셈['생년월일어긋남']++;
                            $못한것[] = [$d->id, $d->customer_name, '이름은 같으나 생년월일이 어긋남'];
                            continue;
                        }

                        $후보   = $좁힘;
                        $어떻게 = 'name_birth';
                    }

                    if (count($후보) > 1) {
                        $셈['여럿']++;
                        $못한것[] = [$d->id, $d->customer_name,
                            '같은 이름ㆍ생년월일이 ' . count($후보) . '명'];
                        continue;
                    }

                    $c = $후보[0];
                    $어떻게 === 'name_birth' ? $셈['이름생년월일']++ : $셈['이름만']++;

                    if (! $d->sign_base64 && ! $d->sign_path) {
                        $셈['그림없음']++;
                        $못한것[] = [$d->id, $d->customer_name, '서명 그림이 없음 — 옮기지 않았습니다'];
                        continue;
                    }

                    $거래처번호 = $거래처지도[$c->ww_id] ?? null;

                    if (! $거래처번호) {
                        $셈['거래처없음']++;
                        $못한것[] = [$d->id, $d->customer_name,
                            '운영 고객 #' . $c->ww_id . ' 이 아직 거래처로 옮겨지지 않음'];
                        continue;
                    }

                    $있나 = PatientDelegationSign::where('delegation_sign_id', $d->id)->first();
                    $있나 ? $셈['덧씀']++ : $셈['새로']++;

                    if (! $정말) {
                        continue;
                    }

                    ($있나 ?: new PatientDelegationSign())->forceFill([
                        'patient_id'         => $거래처번호,
                        'ww_account_id'      => $c->ww_id,
                        'delegation_sign_id' => $d->id,
                        'matched_by'         => $어떻게,

                        'customer_name'      => $d->customer_name,
                        'dealer_name'        => $d->dealer_name,
                        'phone'              => $d->phone,
                        'guardian_phone'     => $d->guardian_phone,
                        'main_contact'       => $d->main_contact,
                        'resident_no_masked' => $d->resident_no_masked,   // 가린 값만
                        'birth_date'         => $d->birth_date,

                        'guardian_name'       => $d->guardian_name,
                        'guardian_relation'   => $d->guardian_relation,
                        'guardian_birth_date' => $d->guardian_birth_date,

                        'agree_delegation'   => (bool) $d->agree_delegation,
                        'agree_privacy'      => (bool) $d->agree_privacy,
                        'agree_marketing'    => (bool) $d->agree_marketing,

                        'signed_at'          => $d->signed_at,
                        'sign_path'          => $d->sign_path,
                        'sign_filename'      => $d->sign_filename,
                        'sign_base64'        => $d->sign_base64,

                        'guardian_signature_data' => $d->guardian_signature_data,
                        'guardian_sign_path'      => $d->guardian_sign_path,
                        'guardian_id_path'        => $d->guardian_id_path,
                        'guardian_id_mime'        => $d->guardian_id_mime,

                        'nice_verified_at'   => $d->nice_verified_at,
                        'nice_name'          => $d->nice_name,
                        'nice_birthdate'     => $d->nice_birthdate,
                        'nice_gender'        => $d->nice_gender,
                        'nice_mobile'        => $d->nice_mobile,

                        'source'             => $d->source,
                        'sent_by_name'       => $d->sent_by_name,
                        'sent_at'            => $d->sent_at,
                        'ip'                 => $d->ip,
                        'user_agent'         => mb_substr((string) $d->user_agent, 0, 255),

                        'data_origin'        => 'migration',
                        'data_batch'         => $묶음,
                    ])->save();
                }
            });

        $this->line('');
        $this->table(['무엇', '몇'], collect($셈)->map(fn ($v, $k) => [$k, number_format($v)])->values()->all());

        if ($못한것 !== []) {
            $this->line('');
            $this->warn('  ── 옮기지 못한 줄 — 사람이 보아야 합니다 ──');
            $this->table(['원본 #', '이름', '왜'], $못한것);
        }

        $this->line('  delegation_signs 는 읽기만 했습니다 — 한 칸도 고치지 않았습니다.');
    }

    /**
     * 전화번호를 쓸 수 있는 꼴로.
     *
     * 원천에 「연락: 010-5687-0026」ㆍ「010-65610542-(아들)」처럼 글이 섞인 것이 204줄
     * 있다. 숫자만 뽑되 **자릿수가 맞을 때만** 쓴다 — 어긋난 것을 담으면 그 번호로
     * 문자가 나간다.
     */
    private function 전화(?string $값): ?string
    {
        $숫자 = preg_replace('/\D/', '', (string) $값);

        return preg_match('/^01[0-9]{8,9}$/', $숫자) ? $숫자 : null;
    }

    /** 대표 주소 — address_id 가 가리키는 줄 */
    private function 주소(?int $열쇠): ?array
    {
        if (! $열쇠) {
            return null;
        }

        $a = DB::table('ww_customer_addresses')->where('ww_id', $열쇠)->first();

        if (! $a) {
            return null;
        }

        $한줄 = trim((string) $a->address_line_1);

        return $한줄 === '' ? null : [
            'address'        => mb_substr($한줄, 0, 300),
            'address_detail' => mb_substr((string) $a->address_line_2, 0, 255) ?: null,
            'postcode'       => preg_replace('/\D/', '', (string) $a->zipcode) ?: null,
        ];
    }
}
